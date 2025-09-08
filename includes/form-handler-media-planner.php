<?php
/**
 * Media Planner 2025 Handler (Form ID 4)
 * Create leads within the DTR Media Planner - 2025
 */

if (!defined('ABSPATH')) exit;

// Include debug logger for Media Planner
require_once __DIR__ . '/media-planner-debug.php';

/* --------------------------------------------------------------------------
 * Logging (kept near top so we can emit a boot message immediately)
 * -------------------------------------------------------------------------- */
function dtr_reg_log($msg) {
    $timestamp = current_time('Y-m-d H:i:s');
    $line = "{$timestamp} [Membership-Reg] {$msg}\n";
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $file1 = DTR_WORKBOOKS_LOG_DIR . 'media-planner-debug.log';
        if (!file_exists(dirname($file1))) wp_mkdir_p(dirname($file1));
        file_put_contents($file1, $line, FILE_APPEND | LOCK_EX);
        $file2 = DTR_WORKBOOKS_LOG_DIR . 'membership-registration-debug.log';
        file_put_contents($file2, $line, FILE_APPEND | LOCK_EX);
    }
    if (defined('WP_DEBUG') && WP_DEBUG) error_log($line);
}

add_action('wp_ajax_media_planner_submit', 'dtr_media_planner_form_handler');
add_action('wp_ajax_nopriv_media_planner_submit', 'dtr_media_planner_form_handler');

function dtr_media_planner_form_handler() {
    // ====Media Planner 2025====
    // Log all hidden fields and values (if present)
    if (isset($_POST['hidden_fields']) && is_array($_POST['hidden_fields'])) {
        dtr_media_planner_debug_log('hidden', $_POST['hidden_fields']);
    }
    // Log all fields as they are entered (all submitted fields)
    dtr_media_planner_debug_log('entered', $_POST);
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'workbooks_nonce')) {
        dtr_reg_log('❌ Invalid nonce');
        wp_send_json_error('Security check failed (invalid nonce)');
    }

    $fields = [];
    $expected = [
        'first_name', 'last_name', 'email_address',
        'job_title', 'organisation', 'town', 'country', 'telephone'
    ];
    foreach ($expected as $field) {
        if (empty($_POST[$field])) {
            dtr_reg_log("❌ Missing required field: $field");
            wp_send_json_error("Missing required field: $field");
        }
        $fields[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
    }
    $name = trim($fields['first_name'] . ' ' . $fields['last_name']);
    if (empty($name)) $name = $fields['email_address'];

    // Hardcoded campaign/event info for this form context
    $parent_event_id = 472341;
    $child_event_id = 5137;
    $campaign_ref = 'CAMP-41496';

    dtr_reg_log("🔍 Checking if person exists with email: {$fields['email_address']}");

    // Workbooks API
    try {
        $workbooks = get_workbooks_instance();
        if (!$workbooks) throw new Exception('No Workbooks API instance');
    } catch (Exception $e) {
        dtr_reg_log("❌ Error instantiating Workbooks API: " . $e->getMessage());
        wp_send_json_error("Could not connect to CRM");
    }

    // Step 1: Check if person exists by email
    $person_id = null;
    try {
        $person_search = [
            '_ff[]' => 'main_location[email]',
            '_ft[]' => 'eq',
            '_fc[]' => $fields['email_address'],
            '_limit' => 1,
            '_select_columns[]' => ['id','object_ref'],
        ];
        $search = $workbooks->assertGet('crm/people.api', $person_search);
        if (!empty($search['data'][0]['id'])) {
            $person_id = $search['data'][0]['id'];
            dtr_reg_log("✅ Found existing person: ID $person_id");
        }
    } catch (Exception $e) {
        dtr_reg_log("❌ ERROR: Person lookup failed: " . $e->getMessage());
    }

    // Step 2: Always create a Sales Lead (do not create person if not found)
    dtr_reg_log("🎉 Creating event lead for campaign $campaign_ref");
    try {
        $queue_id = 1;
        $lead_payload = [[
            'assigned_to' => $queue_id,
            'person_lead_party[name]' => $name,
            'person_lead_party[person_first_name]' => $fields['first_name'],
            'person_lead_party[person_last_name]' => $fields['last_name'],
            'person_lead_party[email]' => $fields['email_address'],
            'org_lead_party[name]' => $fields['organisation'],
            'org_lead_party[main_location][town]' => $fields['town'],
            'org_lead_party[main_location][country]' => $fields['country'],
            'org_lead_party[main_location][telephone]' => $fields['telephone'],
            'cf_lead_data_source_detail' => 'DTR-MEDIA-PLANNER-2025',
            'cf_lead_campaign_reference' => $campaign_ref,
        ]];
        if ($person_id) {
            $lead_payload[0]['person_id'] = $person_id;
        }
        $lead_created = $workbooks->assertCreate('crm/sales_leads.api', $lead_payload);
        $lead_id = $lead_created['affected_objects'][0]['id'] ?? null;
        if ($lead_id) {
            dtr_reg_log("✅ Created lead for person $name {$fields['email_address']}");
        } else {
            dtr_reg_log("❌ Lead creation failed, no ID returned.");
            wp_send_json_error("Could not create CRM lead");
        }
    } catch (Exception $e) {
        dtr_reg_log("❌ Lead create failed: " . $e->getMessage());
        wp_send_json_error("Could not create CRM lead");
    }

    dtr_reg_log("🥳 MEDIA PLANNER LEAD SUBMISSION SUCCESSFUL");
    wp_send_json_success("Submission received and sent to CRM! (Lead created)");
}