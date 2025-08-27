<?php
/**
 * Lead Generation Handler for Sponsor Forms (ID 31)
 * Handles creation/update of mailing list entries in Workbooks.
 * Now with even more aggressive debugging: logs all API responses, exceptions, and tries to log raw HTTP responses from the SDK.
 */

if (!defined('ABSPATH')) exit;

// Unified debug logging function for lead generation (single log file, step format)
if (!function_exists('dtr_leadgen_debug')) {
    function dtr_leadgen_debug($message) {
        $debug_log_file = defined('WORKBOOKS_NF_PATH') ? WORKBOOKS_NF_PATH . 'logs/lead-generation-debug.log' : __DIR__ . '/lead-generation-debug.log';
        $logs_dir = dirname($debug_log_file);
        if (!file_exists($logs_dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($logs_dir);
            } else {
                mkdir($logs_dir, 0777, true);
            }
        }
        if (is_dir($logs_dir) && is_writable($logs_dir)) {
            $log_entry = "[" . date('Y-m-d H:i:s') . "] $message\n";
            error_log($log_entry, 3, $debug_log_file);
        }
    }
}

/**
 * Helper function to upsert mailing list entry for leadgen (like webinar-handler)
 */
function dtr_update_leadgen_mailing_list_member(
    $workbooks,
    $mailing_list_id,
    $email,
    $cf_mailing_list_member_sponsor_1_optin,
    $sponsor_questions,
    &$debug_report = null
) {
    dtr_leadgen_debug("ℹ️ Updating Mailing List Entry for mailing_list_id=$mailing_list_id, email=$email");
    $search_params = [
        '_start' => 0,
        '_limit' => 1,
        '_ff[]' => ['mailing_list_id', 'email'],
        '_ft[]' => ['eq', 'eq'],
        '_fc[]' => [$mailing_list_id, $email],
        '_select_columns[]' => ['id', 'lock_version']
    ];
    try {
        $entry_result = $workbooks->assertGet('email/mailing_list_entries.api', $search_params);
        dtr_leadgen_debug("Workbooks API ENTRY GET response: " . var_export($entry_result, true));
        $entry_id = $entry_result['data'][0]['id'] ?? null;
        $payload = [
            'mailing_list_id' => $mailing_list_id,
            'email' => $email,
            'cf_mailing_list_member_sponsor_1_opt_in' => $cf_mailing_list_member_sponsor_1_optin ? 1 : 0,
        ];
        // Add sponsor questions as individual fields
        foreach ($sponsor_questions as $num => $answer) {
            $payload["cf_mailing_list_member_sponsor_question_$num"] = $answer;
        }
        if ($entry_id) {
            $lock_version = $entry_result['data'][0]['lock_version'] ?? 0;
            $update_result = $workbooks->assertUpdate(
                'email/mailing_list_entries.api',
                [array_merge(['id' => $entry_id, 'lock_version' => $lock_version], $payload)]
            );
            dtr_leadgen_debug("ℹ️ Mailing List Entry updated for $email");
            return $update_result;
        } else {
            $create_result = $workbooks->assertCreate('email/mailing_list_entries.api', [$payload]);
            dtr_leadgen_debug("ℹ️ Mailing List Entry created for $email");
            return $create_result;
        }
    } catch (Exception $e) {
        dtr_leadgen_debug("❌ Exception in dtr_update_leadgen_mailing_list_member: " . $e->getMessage());
        if (is_array($debug_report)) $debug_report['mailing_list_entry_write_exception'] = $e->getMessage();
        return false;
    }
}

/**
 * Main Lead Registration Handler
 * $sponsor_questions is expected as an ordered array: [1 => answer1, 2 => answer2, ...]
 */
function dtr_register_workbooks_lead(
    $post_id,
    $email,
    $first_name = '',
    $last_name = '',
    $sponsor_questions = [],
    $cf_mailing_list_member_sponsor_1_optin = 0,
    &$debug_report = []
) {
    $step = 1;
    dtr_leadgen_debug("✅ STEP {$step}: Processing Lead Gen Form (ID $post_id)");
    $step++;

    if (!$post_id || !$email) {
        dtr_leadgen_debug("❌ STEP {$step}: Missing post_id or email for lead registration");
        $debug_report['error'] = 'missing post_id or email';
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
        return false;
    }

    // Use WP user if logged in for names (like webinar handler)
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

    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_leadgen_debug("❌ STEP {$step}: Workbooks instance not available.");
        $debug_report['error'] = 'no workbooks instance';
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
        return false;
    }
    dtr_leadgen_debug("✅ STEP {$step}: Workbooks API instance loaded");
    $step++;

    // Find or create the person
    $person_id = null;
    $person_step_success = false;
    $person_step_reason = '';
    try {
        $person_result = $workbooks->assertGet('crm/people.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'main_location[email]',
            '_ft[]' => 'eq',
            '_fc[]' => $email,
            '_select_columns[]' => ['id', 'object_ref']
        ]);
        dtr_leadgen_debug("Workbooks API PERSON GET response: " . var_export($person_result, true));
        if (!empty($person_result['data'][0]['id'])) {
            $person_id = $person_result['data'][0]['id'];
            $person_step_success = true;
        }
    } catch (Exception $e) {
        $person_step_reason = $e->getMessage();
        dtr_leadgen_debug("Exception during PERSON GET: " . $person_step_reason);
        if (property_exists($e, 'response')) {
            dtr_leadgen_debug("PERSON GET Exception response property: " . var_export($e->response, true));
        }
    }
    if (!$person_id) {
        try {
            $create_person_objs = [[
                'main_location[email]' => $email,
                'person_first_name' => $first_name,
                'person_last_name' => $last_name,
            ]];
            $create_person = $workbooks->assertCreate('crm/people.api', $create_person_objs);
            dtr_leadgen_debug("Workbooks API PERSON CREATE response: " . var_export($create_person, true));
            if (!empty($create_person['data'][0]['id'])) {
                $person_id = $create_person['data'][0]['id'];
                $person_step_success = true;
            } else {
                $person_step_reason = 'Person create returned no ID';
            }
        } catch (Exception $e) {
            $person_step_reason = $e->getMessage();
            dtr_leadgen_debug("Exception during PERSON CREATE: " . $person_step_reason);
            if (property_exists($e, 'response')) {
                dtr_leadgen_debug("PERSON CREATE Exception response property: " . var_export($e->response, true));
            }
        }
    }
    if ($person_step_success) {
        dtr_leadgen_debug("✅ STEP {$step}: Person created/updated (ID $person_id)");
    } else {
        dtr_leadgen_debug("❌ STEP {$step}: Person created/updated - $person_step_reason");
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
        return false;
    }
    $step++;

    // Try to get Workbooks event reference and campaign reference from group field
    $event_ref = null;
    if (function_exists('get_field')) {
        // Try direct fields first
        $event_ref = get_field('reference', $post_id)
            ?: get_post_meta($post_id, 'reference', true)
            ?: get_field('workbooks_reference', $post_id)
            ?: get_post_meta($post_id, 'workbooks_reference', true);

        $restricted = get_field('restricted_content_fields', $post_id);
        if (is_array($restricted)) {
            if (!$event_ref) {
                if (!empty($restricted['reference'])) {
                    $event_ref = $restricted['reference'];
                } elseif (!empty($restricted['workbooks_reference'])) {
                    $event_ref = $restricted['workbooks_reference'];
                }
            }
        }
    }

    // Debug log event reference
    dtr_leadgen_debug("[Gated Content Debug] - Back End");
    dtr_leadgen_debug("Post ID: $post_id");
    dtr_leadgen_debug("Reference: $event_ref");

    if (!$event_ref) {
        dtr_leadgen_debug("❌ STEP {$step}: Missing Workbooks event reference for post $post_id");
        $debug_report['error'] = 'missing event_ref';
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
        return false;
    }
    if (!preg_match('/(\d+)$/', $event_ref, $matches)) {
        dtr_leadgen_debug("❌ STEP {$step}: Could not extract event_id from reference: $event_ref");
        $debug_report['error'] = 'could not extract event_id';
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
        return false;
    }
    $event_id = $matches[1];
    dtr_leadgen_debug("✅ STEP {$step}: Event reference resolved: $event_id");
    $step++;

    // Get mailing_list_id
    try {
        $event = $workbooks->assertGet('event/events.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'id',
            '_ft[]' => 'eq',
            '_fc[]' => $event_id,
            '_select_columns[]' => ['id', 'mailing_list_id']
        ]);
        dtr_leadgen_debug("Workbooks API EVENT GET response: " . var_export($event, true));
        $mailing_list_id = $event['data'][0]['mailing_list_id'] ?? null;
    } catch (Exception $e) {
        $debug_report['event_get_exception'] = $e->getMessage();
        dtr_leadgen_debug("❌ STEP {$step}: Exception fetching event: " . $e->getMessage());
        if (property_exists($e, 'response')) {
            dtr_leadgen_debug("EVENT GET Exception response property: " . var_export($e->response, true));
        }
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
        return false;
    }
    if (!$mailing_list_id) {
        dtr_leadgen_debug("❌ STEP {$step}: No mailing_list_id found for event_id=$event_id");
        $debug_report['mailing_list_id'] = 'not found';
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
        return false;
    }
    dtr_leadgen_debug("✅ STEP {$step}: mailing_list_id resolved: $mailing_list_id");
    $step++;

    // Upsert mailing list entry using helper (like webinar-handler)
    $ml_result = dtr_update_leadgen_mailing_list_member(
        $workbooks,
        $mailing_list_id,
        $email,
        $cf_mailing_list_member_sponsor_1_optin,
        $sponsor_questions,
        $debug_report
    );
    if ($ml_result === true || is_array($ml_result)) {
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION SUCCESS!");
        // Log sponsor questions and opt-in after successful registration
        if (!empty($sponsor_questions) && is_array($sponsor_questions)) {
            foreach ($sponsor_questions as $num => $answer) {
                dtr_leadgen_debug("[cf_mailing_list_member_sponsor_question_{$num}] => $answer");
            }
        }
    $optin_str = ($cf_mailing_list_member_sponsor_1_optin) ? 'Yes' : 'No';
    dtr_leadgen_debug("[cf_mailing_list_member_sponsor_1_optin] => $cf_mailing_list_member_sponsor_1_optin");
    dtr_leadgen_debug("✅ Sponsor Optin: $optin_str");
        return true;
    } else {
        dtr_leadgen_debug("❌ STEP {$step}: Exception during mailing list entry upsert");
        dtr_leadgen_debug("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
        return false;
    }
}
?>