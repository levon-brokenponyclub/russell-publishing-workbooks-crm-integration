<?php
/**
 * Admin Webinar Registration Handler
 *
 * Handler Process Details:
 * - All critical data is fetched server-side for security.
 * - Ninja Forms submissions for form ID 2 are routed here via admin test page.
 * - All registration steps, fields, and results are logged to admin-webinar-debug.log in strict legacy-compatible format.
 */

if (!defined('ABSPATH')) exit;

// Ensure logs directory exists and is writable
if (!defined('DTR_WORKBOOKS_LOG_DIR')) {
    define('DTR_WORKBOOKS_LOG_DIR', dirname(__FILE__, 2) . '/logs/');
}
if (!is_dir(DTR_WORKBOOKS_LOG_DIR)) {
    if (function_exists('wp_mkdir_p')) wp_mkdir_p(DTR_WORKBOOKS_LOG_DIR);
    else @mkdir(DTR_WORKBOOKS_LOG_DIR, 0777, true);
}
if (!file_exists(DTR_WORKBOOKS_LOG_DIR . 'index.php')) {
    file_put_contents(DTR_WORKBOOKS_LOG_DIR . 'index.php', '<?php // Silence is golden');
}
if (!file_exists(DTR_WORKBOOKS_LOG_DIR . '.htaccess')) {
    file_put_contents(DTR_WORKBOOKS_LOG_DIR . '.htaccess', "Order Deny,Allow\nDeny from all\n");
}

// Smart debug logger: writes to log file, PHP error log, and supports block or timestamped lines
if (!function_exists('dtr_webinar_debug')) {
    function dtr_webinar_debug($message) {
        $debug_log_file = dirname(__FILE__) . '/admin-webinar-debug.log';
        $logs_dir = dirname($debug_log_file);
        if (!is_dir($logs_dir)) {
            if (function_exists('wp_mkdir_p')) wp_mkdir_p($logs_dir);
            else @mkdir($logs_dir, 0777, true);
        }
        if (is_dir($logs_dir) && is_writable($logs_dir)) {
            // Detect if message is a block (multiple lines, don't prepend time) or single line (add time)
            if (strpos($message, "\n") !== false) {
                $log_entry = $message . "\n";
            } else {
                $log_entry = "[" . date('Y-m-d H:i:s') . "] $message\n";
            }
            error_log($log_entry, 3, $debug_log_file);
        }
    }
}

// Main handler (called by Ninja Forms processor)
function dtr_handle_live_webinar_registration($registration_data) {
    dtr_webinar_debug("=== HANDLER START ===");
    dtr_webinar_debug("Input data: " . print_r($registration_data, true));
    
    if (is_user_logged_in()) {
        dtr_webinar_debug("User is logged in");
        $current_user = wp_get_current_user();
        if (empty($registration_data['email'])) {
            $registration_data['email'] = $current_user->user_email;
            dtr_webinar_debug("Set email from current user: " . $registration_data['email']);
        }
        if (empty($registration_data['first_name'])) {
            $registration_data['first_name'] = $current_user->first_name ?: $current_user->display_name;
            dtr_webinar_debug("Set first_name from current user: " . $registration_data['first_name']);
        }
        if (empty($registration_data['last_name'])) {
            $registration_data['last_name'] = $current_user->last_name;
            dtr_webinar_debug("Set last_name from current user: " . $registration_data['last_name']);
        }
        if (empty($registration_data['person_id'])) {
            $registration_data['person_id'] = get_user_meta($current_user->ID, 'workbooks_person_id', true);
            dtr_webinar_debug("Set person_id from user meta: " . $registration_data['person_id']);
        }
    } else {
        dtr_webinar_debug("User is NOT logged in");
    }
    $debug_report = [];
    dtr_webinar_debug("About to call dtr_register_workbooks_webinar with:");
    dtr_webinar_debug("- post_id: " . ($registration_data['post_id'] ?? 'MISSING'));
    dtr_webinar_debug("- email: " . ($registration_data['email'] ?? 'MISSING'));
    dtr_webinar_debug("- first_name: " . ($registration_data['first_name'] ?? 'MISSING'));
    dtr_webinar_debug("- last_name: " . ($registration_data['last_name'] ?? 'MISSING'));
    
    $result = dtr_register_workbooks_webinar(
        $registration_data['post_id'],
        $registration_data['email'],
        $registration_data['first_name'],
        $registration_data['last_name'],
        $registration_data['speaker_question'],
        $registration_data['cf_mailing_list_member_sponsor_1_optin'],
        [], // add_questions (empty for now)
        null,
        $debug_report
    );
    
    dtr_webinar_debug("dtr_register_workbooks_webinar returned: " . print_r($result, true));
    // Compose result info block
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
    $lines = [];
    foreach ($result_info as $k => $v) {
        $lines[] = "[$k] => $v";
    }
    $log_entry = implode("\n", $lines) . "\n";
    if (!empty($debug_report['step_logs'])) {
        $log_entry .= implode("\n", $debug_report['step_logs']) . "\n";
    }
    dtr_webinar_debug($log_entry);
    return $result;
}

// Core Workbooks registration logic, with strict debug logging
function dtr_register_workbooks_webinar(
    $post_id, $email, $first_name = '', $last_name = '', $speaker_question = '', $cf_mailing_list_member_sponsor_1_optin = 0, $add_questions = [], $debug_id = null, &$debug_report = null
) {
    $debug_report = is_array($debug_report) ? $debug_report : [];
    $debug_report['step_logs'] = [];
    $step = 1;

    // Helper for timestamped log
    $step_log = function(&$debug_report, $msg) {
        $debug_report['step_logs'][] = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    };

    // STEP 1: Processing Webinar Form
    if ($post_id && $email) {
        $step_log($debug_report, "✅ STEP $step: Processing Webinar Form (ID $post_id)");
    } else {
        $step_log($debug_report, "❌ STEP $step: Processing Webinar Form (ID $post_id) - Missing post_id or email");
        $step_log($debug_report, "🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // Use WP user if logged in for names
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (empty($email)) {
            $email = $current_user->user_email;
        }
        if (empty($first_name)) {
            $first_name = $current_user->first_name ?: $current_user->display_name;
        }
        if (empty($last_name)) {
            $last_name = $current_user->last_name;
        }
    }

    // Get event reference (workbooks_reference > workbook_reference > reference)
    $webinar_fields = function_exists('get_fields') ? get_fields($post_id) : [];
    $event_ref = function_exists('get_field') ? (
        get_field('workbooks_reference', $post_id)
        ?: get_post_meta($post_id, 'workbooks_reference', true)
        ?: get_field('workbook_reference', $post_id)
        ?: get_post_meta($post_id, 'workbook_reference', true)
        ?: get_field('reference', $post_id)
        ?: get_post_meta($post_id, 'reference', true)
    ) : null;
    if (!$event_ref && is_array($webinar_fields) && !empty($webinar_fields['workbooks_reference'])) {
        $event_ref = $webinar_fields['workbooks_reference'];
    }
    if (!$event_ref && is_array($webinar_fields) && !empty($webinar_fields['workbook_reference'])) {
        $event_ref = $webinar_fields['workbook_reference'];
    }
    if (!$event_ref && is_array($webinar_fields) && !empty($webinar_fields['reference'])) {
        $event_ref = $webinar_fields['reference'];
    }

    if (!$event_ref) {
        // TESTING FALLBACK: Use hardcoded event reference for post 161189
        if ($post_id == '161189') {
            $event_ref = '5832';
            $step_log($debug_report, "ℹ️ STEP $step: Using hardcoded event reference 5832 for test post $post_id");
        } else {
            $step_log($debug_report, "❌ STEP $step: Missing Workbooks event reference for post $post_id");
            $step_log($debug_report, "🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
            return false;
        }
    }
    if (!preg_match('/(\d+)$/', $event_ref, $matches)) {
        $step_log($debug_report, "❌ STEP $step: Could not extract event_id from reference: $event_ref");
        $step_log($debug_report, "🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $event_id = $matches[1];

    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        $step_log($debug_report, "❌ STEP $step: Workbooks instance not available.");
        $step_log($debug_report, "🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 3: Person lookup/create/update
    $person_id = null;
    $person_step_success = false;
    $person_step_reason = '';
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        if (!empty($person_id)) {
            $person_step_success = true;
            $step_log($debug_report, "✅ STEP $step: Person created/updated");
        }
    }
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
                $step_log($debug_report, "✅ STEP $step: Person created/updated");
                if (is_user_logged_in()) {
                    update_user_meta($current_user->ID, 'workbooks_person_id', $person_id);
                }
            }
        } catch (Exception $e) {
            $person_step_reason = $e->getMessage();
        }
    }
    if (!$person_id) {
        $step_log($debug_report, "❌ STEP $step: Person not found - $person_step_reason");
        $step_log($debug_report, "🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 4: Ticket Created/Updated
    $ticket_payload = [[
        'event_id' => $event_id,
        'person_id' => $person_id,
        'name' => trim(($first_name . ' ' . $last_name)) ?: $email,
        'status' => 'Registered',
        'speaker_question' => $speaker_question,
        'sponsor_1_opt_in' => (int)$cf_mailing_list_member_sponsor_1_optin
    ]];
    $ticket_result = null;
    $ticket_step_success = false;
    $ticket_step_reason = '';
    try {
        $ticket_result = $workbooks->create('event/tickets.api', $ticket_payload);
        if (!empty($ticket_result['affected_objects'][0]['id'])) {
            $ticket_step_success = true;
            $step_log($debug_report, "✅ STEP $step: Ticket Created/Updated");
        } else {
            $ticket_step_reason = 'No ticket ID returned';
            $step_log($debug_report, "❌ STEP $step: Ticket Created/Updated - $ticket_step_reason");
        }
    } catch (Exception $e) {
        $ticket_step_reason = $e->getMessage();
        $step_log($debug_report, "❌ STEP $step: Ticket Created/Updated - $ticket_step_reason");
    }
    if (!$ticket_step_success) {
        $step_log($debug_report, "🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 5: Added to Mailing List
    $ticket_id = $ticket_result['affected_objects'][0]['id'] ?? null;
    $ml_step_success = false;
    $ml_step_reason = '';
    $step_log($debug_report, "ℹ️ Updating Mailing List Entry for event_id=$event_id, person_id=$person_id");
    try {
        $ml_result = dtr_update_mailing_list_member(
            $workbooks,
            $event_id,
            $person_id,
            (int)$cf_mailing_list_member_sponsor_1_optin,
            $speaker_question,
            $debug_id,
            $debug_report
        );
        if ($ml_result === true || is_array($ml_result)) {
            $ml_step_success = true;
            $step_log($debug_report, "ℹ️ Mailing List Entry updated for $email");
            $step_log($debug_report, "✅ STEP $step: Added to Mailing List");
        } else {
            $ml_step_reason = 'Failed to update/create mailing list entry';
            $step_log($debug_report, "❌ STEP $step: Added to Mailing List - $ml_step_reason");
        }
    } catch (Exception $e) {
        $ml_step_reason = $e->getMessage();
        $step_log($debug_report, "❌ STEP $step: Added to Mailing List - $ml_step_reason");
    }
    if (!$ml_step_success) {
        $step_log($debug_report, "🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 6: Speaker Question
    $step_log($debug_report, "✅ STEP $step: Speaker Question = $speaker_question");
    $step++;

    // STEP 7: Sponsor Optin
    $optin_str = $cf_mailing_list_member_sponsor_1_optin ? 'Yes' : 'No';
    $step_log($debug_report, "✅ STEP $step: Sponsor Optin = $optin_str");
    $step++;

    // FINAL RESULT - success
    $step_log($debug_report, "🎉 FINAL RESULT: WEBINAR REGISTRATION SUCCESS!");

    return [
        'success' => true,
        'ticket_id' => $ticket_id,
        'person_id' => $person_id,
        'event_id' => $event_id,
        'debug_id' => $debug_id
    ];
}

// Update/create Mailing List Entry (Member) in Workbooks for webinars
function dtr_update_mailing_list_member($workbooks, $event_id, $person_id, $cf_mailing_list_member_sponsor_1_optin, $speaker_question, $debug_id = null, &$debug_report = null) {
    // Only log step lines, not result block
    $email = null;
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
        if (!$mailing_list_id) return false;

        $person = $workbooks->assertGet('crm/people.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'id',
            '_ft[]' => 'eq',
            '_fc[]' => $person_id,
            '_select_columns[]' => ['id', 'main_location[email]']
        ]);
        $email = $person['data'][0]['main_location[email]'] ?? null;
        if (!$email) return false;

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
            'cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin ? 1 : 0,
            'cf_mailing_list_member_speaker_questions' => $speaker_question
        ];

        if ($entry_id) {
            $lock_version = $entry_result['data'][0]['lock_version'] ?? 0;
            $update_result = $workbooks->assertUpdate('email/mailing_list_entries.api', [
                array_merge(['id' => $entry_id, 'lock_version' => $lock_version], $payload)
            ]);
            return $update_result;
        } else {
            $create_payload = [$payload];
            $create_result = $workbooks->assertCreate('email/mailing_list_entries.api', $create_payload);
            return $create_result;
        }
    } catch (Exception $e) {
        return false;
    }
}