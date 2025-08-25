<?php
/**
 * Core Lead Generation Handler & Debug Logger
 */

if (!defined('ABSPATH')) exit;

// Debug logger for lead generation
if (!function_exists('dtr_leadgen_debug')) {
    function dtr_leadgen_debug($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = "[$timestamp] [$level] ";
        $log_entry = $prefix . $message . "\n";
        // Always log to PHP error log when WP_DEBUG is on
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log_entry);
        }
        // Build the log file path
        $debug_log_file = defined('WORKBOOKS_NF_PATH')
            ? WORKBOOKS_NF_PATH . 'logs/lead-generation-debug.log'
            : __DIR__ . '/../logs/lead-generation-debug.log';
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
 * Core Lead Generation Handler
 *
 * @param int $post_id
 * @param string $email
 * @param string $first_name
 * @param string $last_name
 * @param array $acf_questions
 * @param int $cf_mailing_list_member_sponsor_1_optin
 * @param array $debug_report
 * @return array|false
 */
function dtr_register_workbooks_lead(
    $post_id, $email, $first_name = '', $last_name = '', $acf_questions = [], $cf_mailing_list_member_sponsor_1_optin = 0, &$debug_report = null
) {
    // STEP 1: Processing Lead Gen Form
    if ($post_id && $email) {
        dtr_leadgen_debug("✅ All required lead gen data present");
    } else {
        dtr_leadgen_debug("❌ ERROR: Missing post_id or email for lead generation");
        $debug_report['error'] = 'missing post_id or email';
        dtr_leadgen_debug("=== FULL DEBUG REPORT ===\n" . print_r($debug_report, true));
        return false;
    }

    dtr_leadgen_debug("=== CALLING CORE LEAD GENERATION ===");
    dtr_leadgen_debug("✅ Prepared lead gen data:");
    dtr_leadgen_debug("lead_post_id: | " . json_encode(['lead_post_id' => $post_id]));
    dtr_leadgen_debug("lead_email: | " . json_encode(['lead_email' => $email]));
    dtr_leadgen_debug("acf_questions: | " . json_encode(['acf_questions' => $acf_questions]));
    dtr_leadgen_debug("cf_mailing_list_member_sponsor_1_optin: | " . json_encode(['cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin]));
    dtr_leadgen_debug("🚀 Calling dtr_register_workbooks_lead()...");

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

    // Get Workbooks API instance
    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_leadgen_debug("❌ ERROR: Workbooks instance not available.");
        $debug_report['error'] = 'no workbooks instance';
        dtr_leadgen_debug("=== FULL DEBUG REPORT ===\n" . print_r($debug_report, true));
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
            dtr_leadgen_debug("[CONFIRM] Found existing person: $person_id");
            $person_step_success = true;
        }
    } catch (Exception $e) {
        $debug_report['person_search_exception'] = $e->getMessage();
        $person_step_reason = $e->getMessage();
        dtr_leadgen_debug("❌ Exception during person search: " . $e->getMessage());
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
                dtr_leadgen_debug("Person created for lead | " . json_encode(['person_id' => $person_id, 'email' => $email]), '✅');
                $person_step_success = true;
            } else {
                $person_step_reason = 'Person create returned no ID';
            }
        } catch (Exception $e) {
            $debug_report['person_create_exception'] = $e->getMessage();
            $person_step_reason = $e->getMessage();
            dtr_leadgen_debug("❌ Exception during person create: " . $e->getMessage());
        }
    }
    if ($person_step_success) {
        dtr_leadgen_debug("✅ STEP 2: Person created/updated");
    } else {
        dtr_leadgen_debug("❌ STEP 2: Person created/updated - $person_step_reason");
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD GENERATION FAILED!");
        return false;
    }

    // STEP 3: Create Lead (not registrant)
    $lead_payload = [[
        'person_id' => $person_id,
        'lead_source' => 'Website',
        'lead_status' => 'New',
        'cf_mailing_list_member_sponsor_1_optin' => (int)$cf_mailing_list_member_sponsor_1_optin
    ]];
    // Add ACF questions as custom fields
    if (!empty($acf_questions)) {
        foreach ($acf_questions as $idx => $val) {
            $lead_payload[0]['cf_mailing_list_member_sponsor_question_' . ($idx+1)] = $val;
        }
    }
    $debug_report['lead_payload'] = $lead_payload;
    dtr_leadgen_debug("[CONFIRM] Creating lead in Workbooks for person $person_id");
    $lead_result = null;
    $lead_step_success = false;
    $lead_step_reason = '';
    try {
        $lead_result = $workbooks->assertCreate('crm/leads', $lead_payload);
        dtr_leadgen_debug("[CONFIRM] Lead creation result: " . print_r($lead_result, true));
        $debug_report['lead_result'] = $lead_result;
        if (!empty($lead_result['data'][0]['id'])) {
            $lead_step_success = true;
        } else {
            $lead_step_reason = 'No lead ID returned';
        }
    } catch (Exception $e) {
        dtr_leadgen_debug("❌ Exception during lead create: " . $e->getMessage());
        $debug_report['lead_create_exception'] = $e->getMessage();
        $lead_step_reason = $e->getMessage();
    }
    if ($lead_step_success) {
        dtr_leadgen_debug("✅ STEP 3: Lead Created");
    } else {
        dtr_leadgen_debug("❌ STEP 3: Lead Created - $lead_step_reason");
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD GENERATION FAILED!");
        return false;
    }

    // FINAL RESULT
    dtr_leadgen_debug("🎉 FINAL RESULT: LEAD GENERATION SUCCESSFUL!");
    $result_info = [
        'post_id' => $post_id,
        'email_address' => $email,
        'acf_questions' => $acf_questions,
        'cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin,
        'lead_id' => $lead_result['data'][0]['id'] ?? null,
        'person_id' => $person_id,
        'success' => true
    ];
    $result_lines = [];
    foreach ($result_info as $k => $v) {
        $result_lines[] = "[$k] => " . (is_array($v) ? json_encode($v) : $v);
    }
    dtr_leadgen_debug("\nRESULT INFORMATION:\n" . implode("\n", $result_lines));
    dtr_leadgen_debug("✅ Core lead generation completed");
    $return_arr = [
        'success' => true,
        'lead_id' => $lead_result['data'][0]['id'] ?? null,
        'person_id' => $person_id
    ];
    dtr_leadgen_debug("✅ Lead registration result: | " . json_encode($return_arr));
    dtr_leadgen_debug("🎉 Lead generation successful via Ninja Forms hook!");
    dtr_leadgen_debug("=== FULL DEBUG REPORT ===\n" . print_r($debug_report, true));
    return $return_arr;
}
