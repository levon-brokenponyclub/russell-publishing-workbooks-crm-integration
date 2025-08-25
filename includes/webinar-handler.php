<?php
/**
 * Core Webinar Registration Handler & Mailing List Updater
 */


if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/webinar-debug-logger.php';

// Include the new debug logger
require_once __DIR__ . '/webinar-debug-logger.php';

// Unified debug logging function (define only if not already defined)
if (!function_exists('dtr_lead_debug')) {
    function dtr_lead_debug($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = "[$timestamp] [$level] ";
        $log_entry = $prefix . $message . "\n";
            // Always log to PHP error log when WP_DEBUG is on
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($log_entry);
            }

            // Build the log file path
            $debug_log_file = defined('WORKBOOKS_NF_PATH')
                ? WORKBOOKS_NF_PATH . 'logs/gated-post-submissions-debug.log'
                : __DIR__ . '/../logs/gated-post-submissions-debug.log';

            $logs_dir = dirname($debug_log_file);

            // Make sure logs directory exists
            if (!file_exists($logs_dir)) {
                if (function_exists('wp_mkdir_p')) {
                    wp_mkdir_p($logs_dir);
                } else {
                    mkdir($logs_dir, 0755, true);
                }
            }

            // Write to the log file
            @file_put_contents($debug_log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Core Webinar Registration Handler
 */
function dtr_register_workbooks_webinar(
    $post_id, $email, $first_name = '', $last_name = '', $speaker_question = '', $cf_mailing_list_member_sponsor_1_optin = 0, $add_questions = [], $debug_id = null, &$debug_report = null
) {

    // STEP 1: Processing Webinar Form
    if ($post_id && $email) {
        dtr_lead_debug("✅ All required webinar data present");
        dtr_webinar_debug("✅ STEP 1: Processing Webinar Form (ID $post_id)");
    } else {
        dtr_lead_debug("❌ ERROR: Missing post_id or email for webinar registration");
        dtr_webinar_debug("❌ STEP 1: Processing Webinar Form (ID $post_id) - Missing post_id or email");
        $debug_report['error'] = 'missing post_id or email';
        dtr_lead_debug("=== FULL DEBUG REPORT [DebugID: $debug_id] ===\n" . print_r($debug_report, true));
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }

    dtr_lead_debug("=== CALLING CORE WEBINAR REGISTRATION ===");
    dtr_lead_debug("✅ Prepared webinar registration data:");
    dtr_lead_debug("webinar_post_id: | " . json_encode(['webinar_post_id' => $post_id]));
    dtr_lead_debug("participant_email: | " . json_encode(['participant_email' => $email]));
    dtr_lead_debug("speaker_question: | " . json_encode(['speaker_question' => $speaker_question]));
    dtr_lead_debug("cf_mailing_list_member_sponsor_1_optin: | " . json_encode(['cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin]));
    dtr_lead_debug("add_questions: | " . json_encode(['add_questions' => $add_questions]));
    dtr_lead_debug("🚀 Calling dtr_register_workbooks_webinar()...");

    // Merge ACF questions and main question
    $acf_questions = [];
    $webinar_fields = function_exists('get_field') ? get_field('webinar_fields', $post_id) : null;
    if (is_array($webinar_fields) && !empty($webinar_fields['add_questions'])) {
        foreach ($webinar_fields['add_questions'] as $acf_question_row) {
            if (!empty($acf_question_row['question_title'])) {
                $acf_questions[] = trim($acf_question_row['question_title']);
            }
        }
    }
    $all_questions = $acf_questions;
    if (!empty($speaker_question)) {
        $all_questions[] = $speaker_question;
    }
    $merged_questions = implode("\n", $all_questions);

    // Use WP user if logged in for names
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (empty($email)) {
            $email = $current_user->user_email;
        }
        if (empty($first_name)) {
            $first_name = $current_user->user_firstname ?: $current_user->display_name;
        }
        if (empty($last_name)) {
            $last_name = $current_user->user_lastname;
        }
    }
    $debug_report['resolved_names'] = [$first_name, $last_name];

    // Try to get Workbooks event reference
    $event_ref = function_exists('get_field') ? (
        get_field('workbooks_reference', $post_id)
        ?: get_post_meta($post_id, 'workbooks_reference', true)
        ?: get_field('reference', $post_id)
        ?: get_post_meta($post_id, 'reference', true)
    ) : null;

    if (!$event_ref && is_array($webinar_fields) && !empty($webinar_fields['workbook_reference'])) {
        $event_ref = $webinar_fields['workbook_reference'];
        dtr_lead_debug("Found event ref inside webinar_fields group | " . json_encode(['event_ref' => $event_ref]));
    }

    if (!$event_ref) {
        dtr_lead_debug("❌ ERROR: Missing Workbooks event reference for post $post_id");
        $debug_report['error'] = 'missing event_ref';
        dtr_lead_debug("=== FULL DEBUG REPORT [DebugID: $debug_id] ===\n" . print_r($debug_report, true));
        return false;
    }

    // Extract numeric ID from reference
    if (!preg_match('/(\d+)$/', $event_ref, $matches)) {
        dtr_lead_debug("❌ ERROR: Could not extract event_id from reference: $event_ref");
        $debug_report['error'] = 'could not extract event_id';
        dtr_lead_debug("=== FULL DEBUG REPORT [DebugID: $debug_id] ===\n" . print_r($debug_report, true));
        return false;
    }
    $event_id = $matches[1];
    dtr_lead_debug("[CONFIRM] Event reference resolved: $event_id");

    // Get Workbooks API instance
    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_lead_debug("❌ ERROR: Workbooks instance not available.");
        $debug_report['error'] = 'no workbooks instance';
        dtr_lead_debug("=== FULL DEBUG REPORT [DebugID: $debug_id] ===\n" . print_r($debug_report, true));
        return false;
    }


    // STEP 2: Person created/updated
    $person_id = null;
    $person_step_success = false;
    $person_step_reason = '';
    try {
        $person_result = $workbooks->assertGet('crm/people.api', [
            '_start' => 0, '_limit' => 1,
            '_ff[]' => 'main_location[email]', '_ft[]' => 'eq', '_fc[]' => $email,
            '_select_columns[]' => ['id', 'object_ref']
        ]);
        $debug_report['person_search'] = $person_result;
        if (!empty($person_result['data'][0]['id'])) {
            $person_id = $person_result['data'][0]['id'];
            dtr_lead_debug("[CONFIRM] Found existing person: $person_id");
            $person_step_success = true;
        }
    } catch (Exception $e) {
        $debug_report['person_search_exception'] = $e->getMessage();
        $person_step_reason = $e->getMessage();
        dtr_lead_debug("❌ Exception during person search: " . $e->getMessage());
    }
    if (!$person_id) {
        try {
            $create_person = $workbooks->assertCreate('crm/people', [[
                'main_location[email]' => $email,
                'person_first_name' => $first_name,
                'person_last_name' => $last_name,
            ]]);
            $debug_report['person_create'] = $create_person;
            if (!empty($create_person['data'][0]['id'])) {
                $person_id = $create_person['data'][0]['id'];
                dtr_lead_debug("Person created for webinar | " . json_encode(['person_id' => $person_id, 'email' => $email]), '✅');
                $person_step_success = true;
            } else {
                $person_step_reason = 'Person create returned no ID';
            }
        } catch (Exception $e) {
            $debug_report['person_create_exception'] = $e->getMessage();
            $person_step_reason = $e->getMessage();
            dtr_lead_debug("❌ Exception during person create: " . $e->getMessage());
        }
    }
    if ($person_step_success) {
        dtr_webinar_debug("✅ STEP 2: Person created/updated");
    } else {
        dtr_webinar_debug("❌ STEP 2: Person created/updated - $person_step_reason");
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }


    // STEP 3: Ticket Created/Updated
    $ticket_payload = [[
        'event_id' => $event_id,
        'person_id' => $person_id,
        'name' => trim(($first_name . ' ' . $last_name)) ?: $email,
        'status' => 'Registered',
        'speaker_question' => $merged_questions,
        'sponsor_1_opt_in' => (int)$cf_mailing_list_member_sponsor_1_optin
    ]];
    $debug_report['ticket_payload'] = $ticket_payload;

    dtr_lead_debug("[CONFIRM] Creating event ticket in Workbooks for event $event_id and person $person_id");
    $ticket_result = null;
    $ticket_step_success = false;
    $ticket_step_reason = '';
    try {
        $ticket_result = $workbooks->create('event/tickets.api', $ticket_payload);
        dtr_lead_debug("[CONFIRM] Event ticket creation result: " . print_r($ticket_result, true));
        $debug_report['ticket_result'] = $ticket_result;
        if (!empty($ticket_result['affected_objects'][0]['id'])) {
            $ticket_step_success = true;
        } else {
            $ticket_step_reason = 'No ticket ID returned';
        }
    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception during ticket create: " . $e->getMessage());
        $debug_report['ticket_create_exception'] = $e->getMessage();
        $ticket_step_reason = $e->getMessage();
    }
    if ($ticket_step_success) {
        dtr_webinar_debug("✅ STEP 3: Ticket Created/Updated");
    } else {
        dtr_webinar_debug("❌ STEP 3: Ticket Created/Updated - $ticket_step_reason");
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }


    // STEP 4: Added to Mailing List
    $ticket_id = $ticket_result['affected_objects'][0]['id'] ?? null;
    dtr_lead_debug("[CONFIRM] Adding to mailing list: event_id=$event_id, person_id=$person_id, cf_mailing_list_member_sponsor_1_optin=$cf_mailing_list_member_sponsor_1_optin, speaker_question=\"" . addslashes($merged_questions) . "\"");
    $ml_step_success = false;
    $ml_step_reason = '';
    try {
        $ml_result = dtr_update_mailing_list_member(
            $workbooks,
            $event_id,
            $person_id,
            (int)$cf_mailing_list_member_sponsor_1_optin,
            $merged_questions,
            $debug_id,
            $debug_report
        );
        if ($ml_result === true || is_array($ml_result)) {
            dtr_lead_debug("[CONFIRM] Mailing list entry updated/created successfully for email: $email");
            $ml_step_success = true;
        } else {
            dtr_lead_debug("[CONFIRM] Failed to update/create mailing list entry for email: $email");
            $ml_step_reason = 'Failed to update/create mailing list entry';
        }
        $debug_report['mailing_list_member_result'] = $ml_result;
    } catch (Exception $e) {
        dtr_lead_debug("[CONFIRM] Exception in dtr_update_mailing_list_member: " . $e->getMessage());
        $ml_step_reason = $e->getMessage();
        $debug_report['mailing_list_member_exception'] = $e->getMessage();
    }
    if ($ml_step_success) {
        dtr_webinar_debug("✅ STEP 4: Added to Mailing List");
    } else {
        dtr_webinar_debug("❌ STEP 4: Added to Mailing List - $ml_step_reason");
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }

    // STEP 5: Speaker Question
    dtr_webinar_debug("✅ STEP 5: Speaker Question = $speaker_question");

    // STEP 6: Sponsor Optin
    $optin_str = $cf_mailing_list_member_sponsor_1_optin ? 'Yes' : 'No';
    dtr_webinar_debug("✅ STEP 6: Sponsor Optin = $optin_str");

    // FINAL RESULT
    dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION SUCCESSFUL!");

    // RESULT INFORMATION
    $result_info = [
        'webinar_title' => $webinar_fields['webinar_title'] ?? '',
        'post_id' => $post_id,
        'email_address' => $email,
        'question_for_speaker' => $speaker_question,
        'add_questions' => is_array($add_questions) ? implode(", ", $add_questions) : $add_questions,
        'cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin,
        'ticket_id' => $ticket_id,
        'person_id' => $person_id,
        'event_id' => $event_id,
        'success' => true
    ];
    $result_lines = [];
    foreach ($result_info as $k => $v) {
        $result_lines[] = "[$k] => $v";
    }
    dtr_webinar_debug("\nRESULT INFORMATION:\n" . implode("\n", $result_lines));
    dtr_lead_debug("✅ Core webinar registration completed");
    $return_arr = [
        'success' => true,
        'ticket_id' => $ticket_id,
        'person_id' => $person_id,
        'event_id' => $event_id,
        'debug_id' => $debug_id
    ];
    dtr_lead_debug("✅ Registration result: | " . json_encode($return_arr));
    dtr_lead_debug("🎉 Webinar registration successful via Ninja Forms hook!");
    dtr_lead_debug("=== FULL DEBUG REPORT [DebugID: $debug_id] ===\n" . print_r($debug_report, true));
    return $return_arr;
}

/**
 * Update or create a Mailing List Entry (Member) in Workbooks for webinars
 */
function dtr_update_mailing_list_member($workbooks, $event_id, $person_id, $cf_mailing_list_member_sponsor_1_optin, $speaker_question, $debug_id = null, &$debug_report = null) {
    dtr_lead_debug("[CONFIRM] Updating Mailing List Entry for event_id=$event_id, person_id=$person_id");
    try {
        $event = $workbooks->assertGet('event/events.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'id',
            '_ft[]' => 'eq',
            '_fc[]' => $event_id,
            '_select_columns[]' => ['id', 'mailing_list_id']
        ]);
        $mailing_list_id = $event['data'][0]['mailing_list_id'] ?? null;
        if (!$mailing_list_id) {
            dtr_lead_debug("[CONFIRM] No mailing_list_id found for event_id=$event_id");
            if (is_array($debug_report)) $debug_report['mailing_list_id'] = 'not found';
            return false;
        }

        $person = $workbooks->assertGet('crm/people.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'id',
            '_ft[]' => 'eq',
            '_fc[]' => $person_id,
            '_select_columns[]' => ['id', 'main_location[email]']
        ]);
        $email = $person['data'][0]['main_location[email]'] ?? null;
        if (!$email) {
            dtr_lead_debug("[CONFIRM] No email found for person_id=$person_id");
            if (is_array($debug_report)) $debug_report['mlm_email'] = 'not found';
            return false;
        }

        $search_params = [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => ['mailing_list_id', 'email'],
            '_ft[]' => ['eq', 'eq'],
            '_fc[]' => [$mailing_list_id, $email],
            '_select_columns[]' => ['id', 'cf_mailing_list_member_sponsor_1_opt_in', 'cf_mailing_list_member_speaker_questions']
        ];
        $entry_result = $workbooks->assertGet('email/mailing_list_entries.api', $search_params);
        $entry_id = $entry_result['data'][0]['id'] ?? null;

        $payload = [
            'mailing_list_id' => $mailing_list_id,
            'email' => $email,
            'cf_mailing_list_member_sponsor_1_opt_in' => $cf_mailing_list_member_sponsor_1_optin ? 1 : 0,
            'cf_mailing_list_member_speaker_questions' => $speaker_question
        ];

        if (is_array($debug_report)) {
            $debug_report['mlm_payload'] = $payload;
            $debug_report['mlm_search'] = $entry_result;
        }

        // PRODUCTION: Update if entry exists, otherwise create
        if ($entry_id) {
            // Update existing entry with lock_version
            try {
                $lock_version = $entry_result['data'][0]['lock_version'] ?? 0;
                $update_result = $workbooks->assertUpdate('email/mailing_list_entries.api', [
                    [
                        'id' => $entry_id,
                        'lock_version' => $lock_version,
                        'cf_mailing_list_member_sponsor_1_opt_in' => $cf_mailing_list_member_sponsor_1_optin ? 1 : 0,
                        'cf_mailing_list_member_speaker_questions' => $speaker_question
                    ]
                ]);
                dtr_lead_debug("[CONFIRM] Mailing List Entry update result: " . print_r($update_result, true));
                if (is_array($debug_report)) $debug_report['mlm_update_result'] = $update_result;
                return $update_result;
            } catch (Exception $e) {
                dtr_lead_debug("[CONFIRM] Exception during Mailing List Entry update: " . $e->getMessage());
                if (is_array($debug_report)) $debug_report['mlm_update_exception'] = $e->getMessage();
                return false;
            }
        } else {
            // Create new entry
            try {
                $objs = [$payload];
                $create_result = $workbooks->assertCreate('email/mailing_list_entries.api', $objs);
                dtr_lead_debug("[CONFIRM] Mailing List Entry create result: " . print_r($create_result, true));
                if (is_array($debug_report)) $debug_report['mlm_create_result'] = $create_result;
                return $create_result;
            } catch (Exception $e) {
                dtr_lead_debug("[CONFIRM] Exception during Mailing List Entry create: " . $e->getMessage());
                if (is_array($debug_report)) $debug_report['mlm_create_exception'] = $e->getMessage();
                return false;
            }
        }
    } catch (Exception $e) {
        dtr_lead_debug("[CONFIRM] Exception in dtr_update_mailing_list_member: " . $e->getMessage());
        dtr_lead_debug("[CONFIRM] Exception class: " . get_class($e));
        dtr_lead_debug("[CONFIRM] Exception methods: " . print_r(get_class_methods($e), true));
        dtr_lead_debug("[CONFIRM] Exception string: " . $e);
        if (method_exists($e, 'getResponse')) {
            $response = $e->getResponse();
            if (is_object($response) && method_exists($response, 'getBody')) {
                dtr_lead_debug("[CONFIRM] Workbooks API response body: " . (string)$response->getBody());
            }
        }
        if (property_exists($e, 'response')) {
            dtr_lead_debug("[CONFIRM] Exception response property: " . print_r($e->response, true));
        }
        if (method_exists($e, 'getPrevious') && $e->getPrevious()) {
            $prev = $e->getPrevious();
            dtr_lead_debug("[CONFIRM] Previous exception: " . $prev->getMessage());
            if (method_exists($prev, 'getResponse')) {
                $response = $prev->getResponse();
                if (is_object($response) && method_exists($response, 'getBody')) {
                    dtr_lead_debug("[CONFIRM] Previous exception response body: " . (string)$response->getBody());
                }
            }
        }
        if (is_array($debug_report)) $debug_report['mlm_outer_exception'] = $e->getMessage();
        return false;
    }
}
?>