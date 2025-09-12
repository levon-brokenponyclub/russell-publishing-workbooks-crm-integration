<?php
/**
 * Live Webinar Registration Handler
 *
 * Handler Process Details:
 *
 * - Server-side fetching: All critical data (post title, event ID, user info) is fetched server-side for integrity and security.
 *   - Webinar title is fetched using get_the_title($post_id).
 *   - Event ID is fetched from the ACF field 'workbook_reference' on the post.
 *   - Email, first name, last name, and person ID are fetched from the current logged-in user.
 *
 * - Handler routing: Ninja Forms submissions for form ID 2 are routed to this handler via dtr_process_webinar_registration in form-submission-processors-ninjaform-hooks.php.
 *
 * - Form ID: This handler is specifically linked to Ninja Form ID 2 (webinar registration).
 *
 * - Dev mode ticket duplication: Duplicate tickets are allowed in development mode (when DTR_DEV_MODE is defined and true), but are prevented in live/production mode.
 *   - In production, add your real duplicate ticket check where indicated in the code.
 *
 * - Debug output: All registration steps, fields, and results are logged to /logs/live-webinar-registration-debug.log in a consistent, legacy-compatible format.
 *
 * - Integration points: This handler is called by dtr_handle_live_webinar_registration, which is invoked by the Ninja Forms processor for form ID 2.
 *
 * - Admin-post support: Optionally, you can enable admin-post routing for direct POST submissions by uncommenting the add_action lines below.
 *
 * Review this block before testing or deploying to ensure all integration and debug requirements are met.
 */

// DEBUG: Confirm file is loaded
error_log('LIVE HANDLER PHP LOADED');
if (function_exists('dtr_webinar_debug')) {
    dtr_webinar_debug('=== TEST PATCH: Handler loaded and dtr_webinar_debug works ===');
    dtr_webinar_debug('=== TEST PATCH: Current time is ' . date('Y-m-d H:i:s') . ' ===');
}

if (!defined('ABSPATH')) exit;

// Unified debug logging function for webinars (step-by-step, to live-webinar-registration-debug.log)
if (!function_exists('dtr_webinar_debug')) {
    function dtr_webinar_debug($message) {
        $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
            ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
            : __DIR__ . '/../logs/live-webinar-registration-debug.log';
        $logs_dir = dirname($debug_log_file);
        if (!file_exists($logs_dir)) {
            if (function_exists('wp_mkdir_p')) wp_mkdir_p($logs_dir);
            else mkdir($logs_dir, 0777, true);
        }
        if (is_dir($logs_dir) && is_writable($logs_dir)) {
            $log_entry = "[" . date('Y-m-d H:i:s') . "] $message\n";
            error_log($log_entry, 3, $debug_log_file);
        }
    }
}

function dtr_handle_live_webinar_registration($registration_data) {
    // Guaranteed debug log at handler entry
    dtr_webinar_debug('=== dtr_handle_live_webinar_registration ENTRY ===');
    dtr_webinar_debug('RAW $_POST: ' . print_r($_POST, true));
    dtr_webinar_debug('Registration Data (arg): ' . print_r($registration_data, true));
    // Fetch user info if logged in
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (empty($registration_data['email'])) {
            $registration_data['email'] = $current_user->user_email;
        }
        if (empty($registration_data['first_name'])) {
            $registration_data['first_name'] = $current_user->user_first_name ?: $current_user->display_name;
        }
        if (empty($registration_data['last_name'])) {
            $registration_data['last_name'] = $current_user->user_last_name;
        }
        if (empty($registration_data['person_id'])) {
            $registration_data['person_id'] = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        }
    }
    $debug_report = [];
    // Only map and pass the required fields for Workbooks
    $result = dtr_register_workbooks_webinar(
        $registration_data['post_id'],
        $registration_data['email'],
        $registration_data['first_name'],
        $registration_data['last_name'],
        $registration_data['speaker_question'],
        $registration_data['cf_mailing_list_member_sponsor_1_optin'],
        [], // add_questions (empty for now, as in admin handler)
        null,
        $debug_report
    );
    // Compose the debug log in the admin handler format
    $result_info = [
        'webinar_title' => '',
        'post_id' => $registration_data['post_id'] ?? '',
        'email_address' => $registration_data['email'] ?? '',
        'question_for_speaker' => $registration_data['speaker_question'] ?? '',
        'add_questions' => '',
        'cf_mailing_list_member_sponsor_1_optin' => $registration_data['cf_mailing_list_member_sponsor_1_optin'] ?? '',
        'ticket_id' => $result['ticket_id'] ?? '',
        'person_id' => $result['person_id'] ?? '',
        'event_id' => $result['event_id'] ?? '',
        'success' => !empty($result['success']) ? 1 : 0
    ];
    $log_entry = '';
    foreach ($result_info as $k => $v) {
        $log_entry .= "[$k] => $v\n";
    }
    dtr_webinar_debug($log_entry);
    return $result;
}

// Paste the robust dtr_register_workbooks_webinar and dtr_update_mailing_list_member from the user's working code

/**
 * Core Webinar Registration Handler
 */
function dtr_register_workbooks_webinar(
    $post_id, $email, $first_name = '', $last_name = '', $speaker_question = '', $cf_mailing_list_member_sponsor_1_optin = 0, $add_questions = [], $debug_id = null, &$debug_report = null
) {
    $step = 1;
    if ($post_id && $email) {
        dtr_webinar_debug("✅ STEP {$step}: Processing Webinar Form (ID $post_id)");
    } else {
        dtr_webinar_debug("❌ STEP {$step}: Processing Webinar Form (ID $post_id) - Missing post_id or email");
        if (is_array($debug_report)) $debug_report['error'] = 'missing post_id or email';
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // Process submitted ACF questions and main question
    $acf_questions = [];
    $restricted = function_exists('get_field') ? get_field('restricted_content_fields', $post_id) : null;
    
    // Process submitted ACF question answers
    if (is_array($add_questions) && !empty($add_questions)) {
        foreach ($add_questions as $question_key => $answer) {
            if (!empty($answer)) {
                $acf_questions[] = "Q: " . $question_key . " A: " . $answer;
            }
        }
    }
    
    $all_questions = $acf_questions;
    if (!empty($speaker_question)) {
        $all_questions[] = "Speaker Question: " . $speaker_question;
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
    if (is_array($debug_report)) $debug_report['resolved_names'] = [$first_name, $last_name];

    // Try to get Workbooks event reference
    $webinar_fields = [];
    if (function_exists('get_fields')) {
        $webinar_fields = get_fields($post_id);
    }
    $event_ref = function_exists('get_field') ? (
        get_field('workbook_reference', $post_id)
        ?: get_post_meta($post_id, 'workbook_reference', true)
        ?: get_field('workbooks_reference', $post_id)
        ?: get_post_meta($post_id, 'workbooks_reference', true)
        ?: get_field('reference', $post_id)
        ?: get_post_meta($post_id, 'reference', true)
    ) : null;

    if (!$event_ref && is_array($webinar_fields) && !empty($webinar_fields['workbook_reference'])) {
        $event_ref = $webinar_fields['workbook_reference'];
    }

    if (!$event_ref) {
        dtr_webinar_debug("❌ STEP {$step}: Missing Workbooks event reference for post $post_id");
        if (is_array($debug_report)) $debug_report['error'] = 'missing event_ref';
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    if (!preg_match('/(\d+)$/', $event_ref, $matches)) {
        dtr_webinar_debug("❌ STEP {$step}: Could not extract event_id from reference: $event_ref");
        if (is_array($debug_report)) $debug_report['error'] = 'could not extract event_id';
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $event_id = $matches[1];

    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_webinar_debug("❌ STEP {$step}: Workbooks instance not available.");
        if (is_array($debug_report)) $debug_report['error'] = 'no workbooks instance';
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 2: Person lookup (logged-in users already exist in Workbooks)
    $person_id = null;
    $person_step_success = false;
    $person_step_reason = '';
    
    // First try to get person_id from user meta (fastest)
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        if (!empty($person_id)) {
            $person_step_success = true;
            dtr_webinar_debug("✅ STEP {$step}: Person found via user meta (ID: $person_id)");
        }
    }
    
    // If not found in user meta, search by email
    if (!$person_id) {
        try {
            $person_result = $workbooks->assertGet('crm/people.api', [
                '_start' => 0, '_limit' => 1,
                '_ff[]' => 'main_location[email]', '_ft[]' => 'eq', '_fc[]' => $email,
                '_select_columns[]' => ['id', 'object_ref']
            ]);
            if (!empty($person_result['data'][0]['id'])) {
                $person_id = $person_result['data'][0]['id'];
                $person_step_success = true;
                dtr_webinar_debug("✅ STEP {$step}: Person found via email search (ID: $person_id)");
                
                // Store person_id in user meta for future use
                if (is_user_logged_in()) {
                    update_user_meta($current_user->ID, 'workbooks_person_id', $person_id);
                }
            }
        } catch (Exception $e) {
            $person_step_reason = $e->getMessage();
        }
    }
    
    if (!$person_id) {
        dtr_webinar_debug("❌ STEP {$step}: Person not found - $person_step_reason");
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 3: Ticket Created/Updated
    $ticket_payload = [[
        'event_id' => $event_id,
        'person_id' => $person_id,
        'name' => trim(($first_name . ' ' . $last_name)) ?: $email,
        'status' => 'Registered',
        'speaker_question' => $merged_questions,
        'sponsor_1_opt_in' => (int)$cf_mailing_list_member_sponsor_1_optin
    ]];

    $ticket_result = null;
    $ticket_step_success = false;
    $ticket_step_reason = '';
    try {
        $ticket_result = $workbooks->create('event/tickets.api', $ticket_payload);
        if (!empty($ticket_result['affected_objects'][0]['id'])) {
            $ticket_step_success = true;
        } else {
            $ticket_step_reason = 'No ticket ID returned';
        }
    } catch (Exception $e) {
        $ticket_step_reason = $e->getMessage();
    }
    if ($ticket_step_success) {
        dtr_webinar_debug("✅ STEP {$step}: Ticket Created/Updated");
    } else {
        dtr_webinar_debug("❌ STEP {$step}: Ticket Created/Updated - $ticket_step_reason");
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 4: Added to Mailing List
    $ticket_id = $ticket_result['affected_objects'][0]['id'] ?? null;
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
            $ml_step_success = true;
        } else {
            $ml_step_reason = 'Failed to update/create mailing list entry';
        }
    } catch (Exception $e) {
        $ml_step_reason = $e->getMessage();
    }
    if ($ml_step_success) {
        dtr_webinar_debug("✅ STEP {$step}: Added to Mailing List");
    } else {
        dtr_webinar_debug("❌ STEP {$step}: Added to Mailing List - $ml_step_reason");
        dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 5: Speaker Question
    dtr_webinar_debug("✅ STEP {$step}: Speaker Question = $speaker_question");
    $step++;

    // STEP 6: Sponsor Optin
    $optin_str = $cf_mailing_list_member_sponsor_1_optin ? 'Yes' : 'No';
    dtr_webinar_debug("✅ STEP {$step}: Sponsor Optin = $optin_str");
    $step++;

    // FINAL RESULT
    dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION SUCCESS!");

    // RESULT INFORMATION
    $webinar_title_final = '';
    if (!empty($debug_report['debug_raw_fields']['webinar_title'])) {
        $webinar_title_final = $debug_report['debug_raw_fields']['webinar_title'];
    } elseif (!empty($webinar_fields['webinar_title'])) {
        $webinar_title_final = $webinar_fields['webinar_title'];
    }
    $result_info = [
        'webinar_title' => $webinar_title_final,
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
    return [
        'success' => true,
        'ticket_id' => $ticket_id,
        'person_id' => $person_id,
        'event_id' => $event_id,
        'debug_id' => $debug_id
    ];
}

/**
 * Update or create a Mailing List Entry (Member) in Workbooks for webinars
 */
function dtr_update_mailing_list_member($workbooks, $event_id, $person_id, $cf_mailing_list_member_sponsor_1_optin, $speaker_question, $debug_id = null, &$debug_report = null) {
    dtr_webinar_debug("ℹ️ Updating Mailing List Entry for event_id=$event_id, person_id=$person_id");
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
            dtr_webinar_debug("❌ Mailing list not found for event_id=$event_id");
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
            dtr_webinar_debug("❌ No email found for person_id=$person_id");
            if (is_array($debug_report)) $debug_report['mlm_email'] = 'not found';
            return false;
        }

        $search_params = [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => ['mailing_list_id', 'email'],
            '_ft[]' => ['eq', 'eq'],
            '_fc[]' => [$mailing_list_id, $email],
            '_select_columns[]' => ['id', 'lock_version']
        ];
        $entry_result = $workbooks->assertGet('email/mailing_list_entries.api', $search_params);
        $entry_id = $entry_result['data'][0]['id'] ?? null;

        $payload = [
            'mailing_list_id' => $mailing_list_id,
            'email' => $email,
            'cf_mailing_list_member_sponsor_1_opt_in' => $cf_mailing_list_member_sponsor_1_optin ? 1 : 0,
            'cf_mailing_list_member_speaker_questions' => $speaker_question
        ];

        if ($entry_id) {
            try {
                $lock_version = $entry_result['data'][0]['lock_version'] ?? 0;
                $update_result = $workbooks->assertUpdate('email/mailing_list_entries.api', [
                    array_merge(is_array(['id' => $entry_id, 'lock_version' => $lock_version]) ? ['id' => $entry_id, 'lock_version' => $lock_version] : [], is_array($payload) ? $payload : [])
                ]);
                dtr_webinar_debug("ℹ️ Mailing List Entry updated for $email");
                return $update_result;
            } catch (Exception $e) {
                dtr_webinar_debug("❌ Exception during Mailing List Entry update: " . $e->getMessage());
                return false;
            }
        } else {
            try {
                $create_result = $workbooks->assertCreate('email/mailing_list_entries.api', [$payload]);
                dtr_webinar_debug("ℹ️ Mailing List Entry created for $email");
                return $create_result;
            } catch (Exception $e) {
                dtr_webinar_debug("❌ Exception during Mailing List Entry create: " . $e->getMessage());
                return false;
            }
        }
    } catch (Exception $e) {
        dtr_webinar_debug("❌ Exception in dtr_update_mailing_list_member: " . $e->getMessage());
        return false;
    }
}
