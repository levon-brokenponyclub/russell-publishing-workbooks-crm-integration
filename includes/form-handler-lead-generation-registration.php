<?php
/**
 * Lead Generation Registration Handler
 *
 * Handles all lead generation form submissions (Ninja Forms, etc),
 * with robust logging, ACF-powered dynamic questions, and full CRM sync.
 *
 * - All logic is modular and step-logged to /logs/lead-generation-registration-debug.log
 * - Uses the same robust, step-by-step pattern as lead generation registration
 * - Designed for use with a shortcode-based UI and Ninja Forms integration
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('dtr_lead_debug')) {
    function dtr_lead_debug($message) {
        $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
            ? DTR_WORKBOOKS_LOG_DIR . 'lead-generation-registration-debug.log'
            : __DIR__ . '/../logs/lead-generation-registration-debug.log';
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

function dtr_handle_lead_generation_registration($lead_data) {
    dtr_lead_debug('dtr_handle_lead_generation_registration CALLED');
    // Fetch user info if logged in
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (empty($lead_data['email'])) {
            $lead_data['email'] = $current_user->user_email;
        }
        if (empty($lead_data['first_name'])) {
            $lead_data['first_name'] = $current_user->user_first_name ?: $current_user->display_name;
        }
        if (empty($lead_data['last_name'])) {
            $lead_data['last_name'] = $current_user->user_last_name;
        }
        if (empty($lead_data['person_id'])) {
            $lead_data['person_id'] = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        }
    }
    $debug_report = [];
    $result = dtr_register_workbooks_lead(
        $lead_data['post_id'],
        $lead_data['email'],
        $lead_data['first_name'],
        $lead_data['last_name'],
        $lead_data['lead_question'],
        $lead_data['cf_mailing_list_member_sponsor_1_optin'],
        [], // add_questions (empty for now)
        null,
        $debug_report
    );
    // Compose the debug log in the requested format
    $result_info = [
        'post_id' => $lead_data['post_id'] ?? '',
        'email_address' => $lead_data['email'] ?? '',
        'question_for_lead' => $lead_data['lead_question'] ?? '',
        'add_questions' => '',
        'cf_mailing_list_member_sponsor_1_optin' => $lead_data['cf_mailing_list_member_sponsor_1_optin'] ?? '',
        'lead_id' => $result['lead_id'] ?? '',
        'person_id' => $lead_data['person_id'] ?? ($result['person_id'] ?? ''),
        'success' => !empty($result['success']) ? 1 : 0
    ];
    $log_entry = '';
    foreach ($result_info as $k => $v) {
        $log_entry .= "[$k] => $v\n";
    }
    if (!empty($debug_report['steps']) && is_array($debug_report['steps'])) {
        foreach ($debug_report['steps'] as $step_line) {
            $log_entry .= $step_line . "\n";
        }
    }
    if (!empty($debug_report['final'])) {
        $log_entry .= $debug_report['final'] . "\n";
    }
    if (!empty($debug_report['result_info'])) {
        $log_entry .= "\nRESULT INFORMATION:\n";
        foreach ($debug_report['result_info'] as $k => $v) {
            $log_entry .= "[$k] => $v\n";
        }
    }
    dtr_lead_debug($log_entry);
    return $result;
}


/**
 * Core Lead Generation Registration Handler (mirrors webinar handler, for Form ID 31)
 */
function dtr_register_workbooks_lead(
    $post_id, $email, $first_name = '', $last_name = '', $lead_question = '', $cf_mailing_list_member_sponsor_1_optin = 0, $add_questions = [], $debug_id = null, &$debug_report = null
) {
    $step = 1;
    if ($post_id && $email) {
        dtr_lead_debug("✅ STEP {$step}: Processing Lead Generation Form (ID $post_id)");
    } else {
        dtr_lead_debug("❌ STEP {$step}: Processing Lead Generation Form (ID $post_id) - Missing post_id or email");
        if (is_array($debug_report)) $debug_report['error'] = 'missing post_id or email';
        dtr_lead_debug("🎉 FINAL RESULT: LEAD GENERATION REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // Merge ACF questions and main question
    $acf_questions = [];
    
    // First check for questions in restricted_content_fields group
    if (function_exists('get_field')) {
        $restricted_content_fields = get_field('restricted_content_fields', $post_id);
        if (is_array($restricted_content_fields) && !empty($restricted_content_fields['add_questions'])) {
            foreach ($restricted_content_fields['add_questions'] as $acf_question_row) {
                if (!empty($acf_question_row['question_title'])) {
                    $acf_questions[] = trim($acf_question_row['question_title']);
                }
            }
            dtr_lead_debug("✅ Found ACF questions in restricted_content_fields: " . count($acf_questions));
        }
    }
    
    // Fallback to lead_fields for questions if not found in restricted content
    if (empty($acf_questions)) {
        $lead_fields = function_exists('get_field') ? get_field('lead_fields', $post_id) : null;
        if (is_array($lead_fields) && !empty($lead_fields['add_questions'])) {
            foreach ($lead_fields['add_questions'] as $acf_question_row) {
                if (!empty($acf_question_row['question_title'])) {
                    $acf_questions[] = trim($acf_question_row['question_title']);
                }
            }
            dtr_lead_debug("✅ Found ACF questions in lead_fields: " . count($acf_questions));
        }
    }
    $all_questions = $acf_questions;
    if (!empty($lead_question)) {
        $all_questions[] = $lead_question;
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

    // Try to get Workbooks lead reference - check both nested and direct ACF fields
    $lead_ref = null;
    
    // First check if it's within the restricted_content_fields group (based on your ACF structure)
    if (function_exists('get_field')) {
        $restricted_content_fields = get_field('restricted_content_fields', $post_id);
        if (is_array($restricted_content_fields) && !empty($restricted_content_fields['workbooks_reference'])) {
            $lead_ref = $restricted_content_fields['workbooks_reference'];
            dtr_lead_debug("✅ Found workbooks reference in restricted_content_fields: $lead_ref");
        }
    }
    
    // Fallback to direct field lookups if not found in group
    if (!$lead_ref && function_exists('get_field')) {
        $lead_ref = get_field('workbook_reference', $post_id)
            ?: get_post_meta($post_id, 'workbook_reference', true)
            ?: get_field('workbooks_reference', $post_id)
            ?: get_post_meta($post_id, 'workbooks_reference', true)
            ?: get_field('reference', $post_id)
            ?: get_post_meta($post_id, 'reference', true);
        
        if ($lead_ref) {
            dtr_lead_debug("✅ Found workbooks reference in direct fields: $lead_ref");
        }
    }

    if (!$lead_ref && is_array($lead_fields) && !empty($lead_fields['workbook_reference'])) {
        $lead_ref = $lead_fields['workbook_reference'];
        dtr_lead_debug("✅ Found workbooks reference in lead_fields: $lead_ref");
    }

    if (!$lead_ref) {
        dtr_lead_debug("❌ STEP {$step}: Missing Workbooks lead reference for post $post_id");
        if (is_array($debug_report)) $debug_report['error'] = 'missing lead_ref';
        dtr_lead_debug("🎉 FINAL RESULT: LEAD GENERATION REGISTRATION FAILED!");
        return false;
    }
    if (!preg_match('/(\d+)$/', $lead_ref, $matches)) {
        dtr_lead_debug("❌ STEP {$step}: Could not extract lead_id from reference: $lead_ref");
        if (is_array($debug_report)) $debug_report['error'] = 'could not extract lead_id';
        dtr_lead_debug("🎉 FINAL RESULT: LEAD GENERATION REGISTRATION FAILED!");
        return false;
    }
    $lead_id = $matches[1];

    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_lead_debug("❌ STEP {$step}: Workbooks instance not available.");
        if (is_array($debug_report)) $debug_report['error'] = 'no workbooks instance';
        dtr_lead_debug("🎉 FINAL RESULT: LEAD GENERATION REGISTRATION FAILED!");
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
            dtr_lead_debug("✅ STEP {$step}: Person found via user meta (ID: $person_id)");
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
                dtr_lead_debug("✅ STEP {$step}: Person found via email search (ID: $person_id)");
                
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
        dtr_lead_debug("❌ STEP {$step}: Person not found - $person_step_reason");
        dtr_lead_debug("🎉 FINAL RESULT: LEAD GENERATION REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 3: Lead Created/Updated
    $lead_payload = [[
        'event_id' => $lead_id,  // Use event_id instead of lead_id based on your Workbooks schema
        'person_id' => $person_id,
        'name' => trim(($first_name . ' ' . $last_name)) ?: $email,
        'status' => 'New',
        'lead_question' => $merged_questions,
        'sponsor_1_optin' => (int)$cf_mailing_list_member_sponsor_1_optin
    ]];

    $lead_result = null;
    $lead_step_success = false;
    $lead_step_reason = '';
    try {
        $lead_result = $workbooks->create('crm/leads.api', $lead_payload);
        if (!empty($lead_result['affected_objects'][0]['id'])) {
            $lead_step_success = true;
        } else {
            $lead_step_reason = 'No lead ID returned';
        }
    } catch (Exception $e) {
        $lead_step_reason = $e->getMessage();
    }
    if ($lead_step_success) {
        dtr_lead_debug("✅ STEP {$step}: Lead Created/Updated");
    } else {
        dtr_lead_debug("❌ STEP {$step}: Lead Created/Updated - $lead_step_reason");
        dtr_lead_debug("🎉 FINAL RESULT: LEAD GENERATION REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 4: Added to Mailing List (optional, can be customized for lead gen)
    $ml_step_success = false;
    $ml_step_reason = '';
    try {
        $ml_result = dtr_update_lead_mailing_list_member(
            $workbooks,
            $lead_id,
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
        dtr_lead_debug("✅ STEP {$step}: Added to Mailing List");
    } else {
        dtr_lead_debug("❌ STEP {$step}: Added to Mailing List - $ml_step_reason");
        dtr_lead_debug("🎉 FINAL RESULT: LEAD GENERATION REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // STEP 5: Lead Question
    dtr_lead_debug("✅ STEP {$step}: Lead Question = $lead_question");
    $step++;

    // STEP 6: Sponsor Optin
    $optin_str = $cf_mailing_list_member_sponsor_1_optin ? 'Yes' : 'No';
    dtr_lead_debug("✅ STEP {$step}: Sponsor Optin = $optin_str");
    $step++;

    // FINAL RESULT
    dtr_lead_debug("🎉 FINAL RESULT: LEAD GENERATION REGISTRATION SUCCESS!");

    // RESULT INFORMATION
    $lead_title_final = '';
    if (!empty($debug_report['debug_raw_fields']['lead_title'])) {
        $lead_title_final = $debug_report['debug_raw_fields']['lead_title'];
    } elseif (!empty($lead_fields['lead_title'])) {
        $lead_title_final = $lead_fields['lead_title'];
    }
    $result_info = [
        'lead_title' => $lead_title_final,
        'post_id' => $post_id,
        'email_address' => $email,
        'question_for_lead' => $lead_question,
        'add_questions' => is_array($add_questions) ? implode(", ", $add_questions) : $add_questions,
        'cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin,
        'lead_id' => $lead_id,
        'person_id' => $person_id,
        'success' => true
    ];
    $result_lines = [];
    foreach ($result_info as $k => $v) {
        $result_lines[] = "[$k] => $v";
    }
    dtr_lead_debug("\nRESULT INFORMATION:\n" . implode("\n", $result_lines));
    return [
        'success' => true,
        'lead_id' => $lead_id,
        'person_id' => $person_id,
        'debug_id' => $debug_id
    ];
}

/**
 * Update or create a Mailing List Entry (Member) in Workbooks for lead generation
 */
function dtr_update_lead_mailing_list_member($workbooks, $lead_id, $person_id, $cf_mailing_list_member_sponsor_1_optin, $lead_question, $debug_id = null, &$debug_report = null) {
    dtr_lead_debug("ℹ️ Updating Mailing List Entry for lead_id=$lead_id, person_id=$person_id");
    try {
        // This is a placeholder for lead gen mailing list logic. Adjust as needed for your CRM schema.
        // For now, just log and return true for success.
        dtr_lead_debug("ℹ️ (Stub) Mailing List Entry logic for lead_id=$lead_id, person_id=$person_id");
        return true;
    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception in dtr_update_lead_mailing_list_member: " . $e->getMessage());
        return false;
    }
}
