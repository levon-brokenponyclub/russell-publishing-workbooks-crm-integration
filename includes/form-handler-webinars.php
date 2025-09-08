<?php
// Register admin-post handler for test form submissions
add_action('admin_post_dtr_test_webinar_registration', 'dtr_handle_test_webinar_registration');
add_action('admin_post_nopriv_dtr_test_webinar_registration', 'dtr_handle_test_webinar_registration');

function dtr_handle_test_webinar_registration() {
    // Collect POST data using old field names and structure
    $registration_data = [
        'post_id' => isset($_POST['post_id']) ? intval($_POST['post_id']) : 0,
        'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
        'speaker_question' => isset($_POST['speaker_question']) ? sanitize_text_field($_POST['speaker_question']) : '',
        'cf_mailing_list_member_sponsor_1_optin' => !empty($_POST['cf_mailing_list_member_sponsor_1_optin']) ? 1 : 0,
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '',
        'last_name' => isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '',
        'event_id' => isset($_POST['event_id']) ? intval($_POST['event_id']) : '',
    ];

    // Log the registration data and steps in the requested format
    $log_file = __DIR__ . '/../admin/admin-webinar-debug.log';
    $log_entry = '';
    $result_info = [
        'webinar_title' => '',
        'post_id' => $registration_data['post_id'] ?? '',
        'email_address' => $registration_data['email'] ?? '',
        'question_for_speaker' => $registration_data['speaker_question'] ?? '',
        'add_questions' => '',
        'cf_mailing_list_member_sponsor_1_optin' => $registration_data['cf_mailing_list_member_sponsor_1_optin'] ?? '',
        'ticket_id' => '',
        'person_id' => '',
        'event_id' => $registration_data['event_id'] ?? '',
        'success' => ''
    ];
    $step_lines = [];
    $step_lines[] = '[' . date('Y-m-d H:i:s') . '] ✅ STEP 1: Processing Webinar Form (ID ' . ($registration_data['post_id'] ?? '') . ')';
    $log_entry .= "[webinar_title] => {$result_info['webinar_title']}\n";
    $log_entry .= "[post_id] => {$result_info['post_id']}\n";
    $log_entry .= "[email_address] => {$result_info['email_address']}\n";
    $log_entry .= "[question_for_speaker] => {$result_info['question_for_speaker']}\n";
    $log_entry .= "[add_questions] => {$result_info['add_questions']}\n";
    $log_entry .= "[cf_mailing_list_member_sponsor_1_optin] => {$result_info['cf_mailing_list_member_sponsor_1_optin']}\n";
    $log_entry .= "[ticket_id] => {$result_info['ticket_id']}\n";
    $log_entry .= "[person_id] => {$result_info['person_id']}\n";
    $log_entry .= "[event_id] => {$result_info['event_id']}\n";
    $log_entry .= "[success] => {$result_info['success']}\n";
    $log_entry .= "\n";
    $log_entry .= $step_lines[0] . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    $debug_report = [];

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

    // Output a simple result page
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Webinar Registration Result</title></head><body>';
    if (!empty($result['success'])) {
        echo '<h2 style="color:green;">Registration Successful!</h2>';
    } else {
        echo '<h2 style="color:red;">Registration Failed</h2>';
        if (!empty($debug_report['error'])) {
            echo '<p>Error: ' . esc_html($debug_report['error']) . '</p>';
        }
    }
    echo '<pre style="background:#f8f8f8;padding:1em;">';
    print_r($debug_report);
    echo '</pre>';
    echo '<p><a href="javascript:history.back()">Back to form</a></p>';
    echo '</body></html>';
    exit;
}
/**
 * Core Webinar Registration Handler & Mailing List Updater
 */

if (!defined('ABSPATH')) exit;

// Unified debug logging function for webinars (step-by-step, to webinar-debug.log)
if (!function_exists('dtr_webinar_debug')) {
    function dtr_webinar_debug($message) {
        $debug_log_file = defined('WORKBOOKS_NF_PATH')
            ? WORKBOOKS_NF_PATH . 'logs/webinar-debug.log'
            : __DIR__ . '/webinar-debug.log';
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
    if (is_array($debug_report)) $debug_report['resolved_names'] = [$first_name, $last_name];

    // Try to get Workbooks event reference
    $event_ref = function_exists('get_field') ? (
        get_field('workbooks_reference', $post_id)
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

    // STEP 2: Person created/updated (use submitted person_id if present)
    $person_id = null;
    $person_step_success = false;
    $person_step_reason = '';
    if (!empty($registration_data['person_id'])) {
        $person_id = intval($registration_data['person_id']);
        $person_step_success = true;
        dtr_webinar_debug("✅ STEP {$step}: Used submitted person_id $person_id");
    } else {
        try {
            $person_result = $workbooks->assertGet('crm/people.api', [
                '_start' => 0, '_limit' => 1,
                '_ff[]' => 'main_location[email]', '_ft[]' => 'eq', '_fc[]' => $email,
                '_select_columns[]' => ['id', 'object_ref']
            ]);
            if (!empty($person_result['data'][0]['id'])) {
                $person_id = $person_result['data'][0]['id'];
                $person_step_success = true;
            }
        } catch (Exception $e) {
            $person_step_reason = $e->getMessage();
        }
        if (!$person_id) {
            try {
                $payload = [
                    'main_location[email]' => $email,
                    'person_first_name' => $first_name,
                    'person_last_name' => $last_name,
                ];
                $payloads = [$payload];
                $create_person = $workbooks->assertCreate('crm/people', $payloads);
                if (!empty($create_person['data'][0]['id'])) {
                    $person_id = $create_person['data'][0]['id'];
                    $person_step_success = true;
                } else {
                    $person_step_reason = 'Person create returned no ID';
                }
            } catch (Exception $e) {
                $person_step_reason = $e->getMessage();
            }
        }
        if ($person_step_success) {
            dtr_webinar_debug("✅ STEP {$step}: Person created/updated");
        } else {
            dtr_webinar_debug("❌ STEP {$step}: Person created/updated - $person_step_reason");
            dtr_webinar_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION FAILED!");
            return false;
        }
    }
    $step++;

    // STEP 3: Ticket Created/Updated (fields match successful submission reference)
    $ticket_payload = [[
        'event_id' => $event_id,
        'person_id' => $person_id,
        'name' => trim(($first_name . ' ' . $last_name)) ?: $email,
        'status' => 'Registered',
        // Use correct Workbooks custom fields as per your successful debug
        'cf_event_ticket_speaker_questions' => $speaker_question,
        'cf_event_ticket_sponsor_optin' => (bool)$cf_mailing_list_member_sponsor_1_optin
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
                    array_merge(['id' => $entry_id, 'lock_version' => $lock_version], $payload)
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
?>