<?php
/**
 * Lead Generation Handler for Sponsor Forms (ID 31)
 * Handles creation/update of mailing list entries in Workbooks.
 */

if (!defined('ABSPATH')) exit;

// Debug logging function (unified)
if (!function_exists('dtr_leadgen_debug')) {
    function dtr_leadgen_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[DTR LeadGen] $message");
        }
        $debug_log_file = defined('WORKBOOKS_NF_PATH') ? WORKBOOKS_NF_PATH . 'logs/gated-post-submissions-debug.log' : __DIR__ . '/gated-post-submissions-debug.log';
        $logs_dir = dirname($debug_log_file);
        if (!file_exists($logs_dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($logs_dir);
            } else {
                mkdir($logs_dir, 0777, true);
            }
        }
        $log_entry = "[" . date('Y-m-d H:i:s') . "] $message\n";
        error_log($log_entry, 3, $debug_log_file);
    }
}

/**
 * Main Lead Registration Handler
 * $sponsor_questions is expected as an ordered array: [1 => answer1, 2 => answer2, ...]
 */
function dtr_register_workbooks_lead(
    $post_id, $email, $first_name = '', $last_name = '', $sponsor_questions = [], $cf_mailing_list_member_sponsor_1_optin = 0, &$debug_report = null
) {
    dtr_leadgen_debug("=== CALLING dtr_register_workbooks_lead ===");

    if (!$post_id || !$email) {
        dtr_leadgen_debug("❌ ERROR: Missing post_id or email for lead registration");
        $debug_report['error'] = 'missing post_id or email';
        return false;
    }

    // Get Workbooks API instance
    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_leadgen_debug("❌ ERROR: Workbooks instance not available.");
        $debug_report['error'] = 'no workbooks instance';
        return false;
    }

    // Find or create the person
    $person_id = null;
    try {
        $person_result = $workbooks->assertGet('crm/people.api', [
            '_start' => 0, '_limit' => 1,
            '_ff[]' => 'main_location[email]', '_ft[]' => 'eq', '_fc[]' => $email,
            '_select_columns[]' => ['id', 'object_ref']
        ]);
        if (!empty($person_result['data'][0]['id'])) {
            $person_id = $person_result['data'][0]['id'];
            dtr_leadgen_debug("[CONFIRM] Found existing person: $person_id");
        }
    } catch (Exception $e) {
        $debug_report['person_search_exception'] = $e->getMessage();
        dtr_leadgen_debug("❌ Exception during person search: " . $e->getMessage());
    }
    if (!$person_id) {
        try {
            $create_person = $workbooks->assertCreate('crm/people', [[
                'main_location[email]' => $email,
                'person_first_name' => $first_name,
                'person_last_name' => $last_name,
            ]]);
            if (!empty($create_person['data'][0]['id'])) {
                $person_id = $create_person['data'][0]['id'];
                dtr_leadgen_debug("Person created for lead | " . json_encode(['person_id' => $person_id, 'email' => $email]));
            }
        } catch (Exception $e) {
            $debug_report['person_create_exception'] = $e->getMessage();
            dtr_leadgen_debug("❌ Exception during person create: " . $e->getMessage());
        }
    }
    if (!$person_id) {
        dtr_leadgen_debug("❌ STEP: Person could not be created or found");
        return false;
    }

    // Try to get Workbooks event reference from post
    $event_ref = function_exists('get_field') ? (
        get_field('workbooks_reference', $post_id)
        ?: get_post_meta($post_id, 'workbooks_reference', true)
        ?: get_field('reference', $post_id)
        ?: get_post_meta($post_id, 'reference', true)
    ) : null;

    if (!$event_ref) {
        dtr_leadgen_debug("❌ ERROR: Missing Workbooks event reference for post $post_id");
        $debug_report['error'] = 'missing event_ref';
        return false;
    }
    if (!preg_match('/(\d+)$/', $event_ref, $matches)) {
        dtr_leadgen_debug("❌ ERROR: Could not extract event_id from reference: $event_ref");
        $debug_report['error'] = 'could not extract event_id';
        return false;
    }
    $event_id = $matches[1];

    // Get mailing_list_id
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
        dtr_leadgen_debug("[CONFIRM] No mailing_list_id found for event_id=$event_id");
        $debug_report['mailing_list_id'] = 'not found';
        return false;
    }

    // Prepare payload with all sponsor questions as individual fields
    $payload = [
        'mailing_list_id' => $mailing_list_id,
        'email' => $email,
        'cf_mailing_list_member_sponsor_1_opt_in' => $cf_mailing_list_member_sponsor_1_optin ? 1 : 0,
    ];
    foreach ($sponsor_questions as $num => $answer) {
        $payload["cf_mailing_list_member_sponsor_question_$num"] = $answer;
    }
    dtr_leadgen_debug("[INFO] Mail payload: " . json_encode($payload));

    // Find existing mailing list entry
    $search_params = [
        '_start' => 0,
        '_limit' => 1,
        '_ff[]' => ['mailing_list_id', 'email'],
        '_ft[]' => ['eq', 'eq'],
        '_fc[]' => [$mailing_list_id, $email],
        '_select_columns[]' => array_merge(['id', 'lock_version'], array_keys($payload))
    ];
    $entry_result = $workbooks->assertGet('email/mailing_list_entries.api', $search_params);
    $entry_id = $entry_result['data'][0]['id'] ?? null;

    // Update or create
    if ($entry_id) {
        $lock_version = $entry_result['data'][0]['lock_version'] ?? 0;
        $update_payload = array_merge(['id' => $entry_id, 'lock_version' => $lock_version], $payload);
        $update_result = $workbooks->assertUpdate('email/mailing_list_entries.api', [$update_payload]);
        dtr_leadgen_debug("[CONFIRM] Mailing list entry updated: " . print_r($update_result, true));
        $debug_report['mailing_list_entry_update'] = $update_result;
        return $update_result;
    } else {
        $create_result = $workbooks->assertCreate('email/mailing_list_entries.api', [$payload]);
        dtr_leadgen_debug("[CONFIRM] Mailing list entry created: " . print_r($create_result, true));
        $debug_report['mailing_list_entry_create'] = $create_result;
        return $create_result;
    }
}
?>