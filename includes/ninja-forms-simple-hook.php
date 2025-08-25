<?php
/**
 * Ninja Forms Lead Generation & Webinar Registration Hook
 * Catches form submissions and registers leads/webinars in Workbooks
 */

if (!defined('ABSPATH')) exit;

// Debug logging function (unified)
if (!function_exists('dtr_lead_debug')) {
    function dtr_lead_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[DTR Lead Gen] $message");
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

// Console log for update notification (visible in browser console on every page load)
add_action('wp_footer', function() {
    ?>
    <script>
        console.log('%c[Ninja Forms Simple Hook] Update: dtr_register_workbooks_lead function existence check added - 2025-08-25', 'color: green; font-weight: bold;');
    </script>
    <?php
});

// Hook into Ninja Forms submission
add_action('ninja_forms_after_submission', 'dtr_ninja_forms_lead_generation_handler', 10, 1);

/**
 * Main Ninja Forms submission handler for lead generation and webinars
 */
function dtr_ninja_forms_lead_generation_handler($form_data) {
    try {
        // === DTR DEBUG START ===
        $submission_uuid = uniqid("ninjaforms-", true);
        dtr_lead_debug("=== NINJA FORMS SUBMISSION DETECTED [ID: $submission_uuid] ===");
        dtr_lead_debug("[RAW FORM DATA] " . print_r($form_data, true));
        // === DTR DEBUG END ===

        // Determine form ID
        $form_id = null;
        if (isset($form_data['form_id'])) {
            $form_id = $form_data['form_id'];
        } elseif (isset($form_data['id'])) {
            $form_id = $form_data['id'];
        }
        dtr_lead_debug("Detected form ID: " . $form_id);

        if ($form_id == 2 || $form_id === '2') {
            dtr_lead_debug("✅ Processing webinar form (ID 2) and exiting to prevent other handlers");
            dtr_handle_webinar_form_submission($form_data, $submission_uuid);
            return;
        }

        if ($form_id != 31 && $form_id !== '31') {
            dtr_lead_debug("ℹ️  Form ID $form_id not configured for lead generation processing");
            return;
        }
        dtr_lead_debug("✅ Processing lead generation form (ID 31)");

        // Extract fields for lead gen
        $current_user = wp_get_current_user();
        $email = $current_user && $current_user->user_email ? $current_user->user_email : '';
        $first_name = $current_user && $current_user->user_firstname ? $current_user->user_firstname : '';
        $last_name = $current_user && $current_user->user_lastname ? $current_user->user_lastname : '';
        $post_id = '';
        $cf_mailing_list_member_sponsor_1_optin = 0;
        $sponsor_questions = [];

        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            foreach ($form_data['fields'] as $field) {
                $key = strtolower($field['key']);
                $value = isset($field['value']) ? $field['value'] : '';
                if ($key === 'post_id') {
                    $post_id = $value;
                } elseif ($key === 'cf_mailing_list_member_sponsor_1_optin' || $key === 'sponsor_optin') {
                    $cf_mailing_list_member_sponsor_1_optin = ($value === '1' || $value === 1 || $value === true || $value === 'true' || $value === 'on') ? 1 : 0;
                }
                // Collect all sponsor questions as individual fields
                if (preg_match('/^cf_mailing_list_member_sponsor_question_(\d+)$/', $key, $matches)) {
                    $num = (int)$matches[1];
                    $sponsor_questions[$num] = $value;
                }
            }
        }

        ksort($sponsor_questions); // Ensure order

        dtr_lead_debug("[INFO] Extracted sponsor questions: " . json_encode($sponsor_questions));

        // --- ENSURE THE LEAD HANDLER FUNCTION EXISTS ---
        if (!function_exists('dtr_register_workbooks_lead')) {
            // Try to include the file, adjust path if needed
            $ajax_handlers_path = WP_CONTENT_DIR . '/plugins/dtr-workbooks-crm-integration/includes/ajax-handlers.php';
            if (file_exists($ajax_handlers_path)) {
                include_once $ajax_handlers_path;
                dtr_lead_debug("✅ Loaded ajax-handlers.php for lead registration function.");
            } else {
                dtr_lead_debug("❌ ERROR: ajax-handlers.php not found; cannot register lead.");
                // Extra debug dump if the error persists
                dtr_lead_debug("❌ [EXTRA DEBUG] Current include_path: " . get_include_path());
                dtr_lead_debug("❌ [EXTRA DEBUG] File exists? " . ($ajax_handlers_path) . ': ' . (file_exists($ajax_handlers_path) ? 'YES' : 'NO'));
                dtr_lead_debug("❌ [EXTRA DEBUG] CWD: " . getcwd());
                dtr_lead_debug("❌ [EXTRA DEBUG] Defined WP_CONTENT_DIR: " . (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : 'NOT DEFINED'));
                return;
            }
        } else {
            dtr_lead_debug("[INFO] dtr_register_workbooks_lead() is available before submission processing.");
        }

        // Call the lead gen handler
        $debug_report = [];
        $result = dtr_register_workbooks_lead(
            $post_id,
            $email,
            $first_name,
            $last_name,
            $sponsor_questions, // Pass the array of answers
            $cf_mailing_list_member_sponsor_1_optin,
            $debug_report
        );
        dtr_lead_debug("[INFO] Lead generation result: " . print_r($result, true));
        dtr_lead_debug("[INFO] Full debug report: " . print_r($debug_report, true));
    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception during lead registration: " . $e->getMessage());
        dtr_lead_debug("Exception details: " . print_r($e, true));
    } catch (Error $e) {
        dtr_lead_debug("❌ Error (PHP 7+) during lead registration: " . $e->getMessage());
        dtr_lead_debug("Error details: " . print_r($e, true));
    }
}

/**
 * Handle webinar form submissions (Form ID 2)
 */
function dtr_handle_webinar_form_submission($form_data, $submission_uuid = null) {
    if (!$submission_uuid) $submission_uuid = uniqid("webinar-", true);
    dtr_lead_debug("=== PROCESSING WEBINAR FORM SUBMISSION [ID: $submission_uuid] ===");
    dtr_lead_debug("[CONFIRM] Handler running for form ID: 2");

    // Get participant email from current user (webinar requires login)
    $current_user = wp_get_current_user();
    if (!$current_user || !$current_user->user_email) {
        dtr_lead_debug("❌ ERROR: No current user or user email - webinar registration requires login");
        return;
    }
    $participant_email = $current_user->user_email;
    dtr_lead_debug("✅ Using current user email | " . json_encode(['user_email' => $participant_email]));

    // Robust field extraction (case-insensitive, supports multiple key names)
    $webinar_title = '';
    $post_id = '';
    $speaker_question = '';
    $cf_mailing_list_member_sponsor_1_optin = 0;
    $add_questions = '';

    $extracted_fields = [];
    if (isset($form_data['fields']) && is_array($form_data['fields'])) {
        foreach ($form_data['fields'] as $field) {
            $key = strtolower($field['key']);
            $value = isset($field['value']) ? $field['value'] : '';
            $extracted_fields[$key] = $value;
            switch ($key) {
                case 'webinar_title':
                    $webinar_title = $value;
                    break;
                case 'post_id':
                    $post_id = $value;
                    break;
                case 'email_address':
                    break; // Always override with $participant_email
                case 'speaker_question':
                case 'question_for_speaker':
                    $speaker_question = $value;
                    break;
                case 'add_questions':
                    $add_questions = $value;
                    break;
                case 'cf_mailing_list_member_sponsor_1_optin':
                case 'sponsor_optin':
                    // Always treat as integer 1 if checked, 0 otherwise
                    $cf_mailing_list_member_sponsor_1_optin = ($value === '1' || $value === 1 || $value === true || $value === 'true' || $value === 'on') ? 1 : 0;
                    break;
            }
            dtr_lead_debug("[INFO] Field $key: | " . json_encode([$key => $value]));
        }
    }

    // Validate required data
    if (empty($webinar_title) || empty($post_id) || empty($participant_email)) {
        dtr_lead_debug("❌ ERROR: Missing required webinar data");
        dtr_lead_debug("  - Webinar Title: " . ($webinar_title ?: 'MISSING'));
        dtr_lead_debug("  - Post ID: " . ($post_id ?: 'MISSING'));
        dtr_lead_debug("  - Email: " . ($participant_email ?: 'MISSING'));
        return;
    }

    dtr_lead_debug("✅ All required webinar data present");
    dtr_lead_debug("  - Email Address: $participant_email");
    dtr_lead_debug("  - Question for Speaker: $speaker_question");
    dtr_lead_debug("  - Add Questions: " . ($add_questions !== '' ? $add_questions : 'MISSING'));
    dtr_lead_debug("  - Sponsor Opt-in 1: $cf_mailing_list_member_sponsor_1_optin");

    // Prepare registration data payload (use consistent keys)
    $registration_data = array(
        'webinar_title'     => $webinar_title,
        'webinar_post_id'   => $post_id,
        'participant_email' => $participant_email,
        'speaker_question'  => $speaker_question,
        'cf_mailing_list_member_sponsor_1_optin'   => $cf_mailing_list_member_sponsor_1_optin,
        'add_questions'     => $add_questions,
        'debug_submission_id' => $submission_uuid,
        'debug_raw_fields' => $extracted_fields
    );

    dtr_lead_debug("[CONFIRM] Submitting payload to Workbooks: " . print_r($registration_data, true));
    dtr_call_webinar_registration($registration_data, $submission_uuid);
}

/**
 * Call the core webinar registration function
 */
function dtr_call_webinar_registration($registration_data, $submission_uuid = null) {
    dtr_lead_debug("=== CALLING CORE WEBINAR REGISTRATION [ID: {$registration_data['debug_submission_id']}] ===");

    if (!function_exists('dtr_register_workbooks_webinar')) {
        dtr_lead_debug("❌ ERROR: Core webinar registration function not found");
        $ajax_handlers_path = WP_CONTENT_DIR . '/plugins/dtr-workbooks-crm-integration/includes/ajax-handlers.php';
        if (file_exists($ajax_handlers_path)) {
            include_once $ajax_handlers_path;
            dtr_lead_debug("✅ Loaded ajax-handlers.php");
        } else {
            dtr_lead_debug("❌ ERROR: ajax-handlers.php not found");
            return;
        }
    }

    dtr_lead_debug("✅ Prepared webinar registration data:");
    foreach ($registration_data as $k => $v) {
        dtr_lead_debug("$k: | " . json_encode([$k => $v]));
    }

    try {
        dtr_lead_debug("🚀 Calling dtr_register_workbooks_webinar()...");
        $result = dtr_register_workbooks_webinar(
            $registration_data['webinar_post_id'],
            $registration_data['participant_email'],
            $registration_data['first_name'] ?? '',
            $registration_data['last_name'] ?? '',
            $registration_data['speaker_question'] ?? '',
            $registration_data['cf_mailing_list_member_sponsor_1_optin'] ?? false,
            $registration_data['add_questions'] ?? [],
            $registration_data['debug_submission_id'] ?? null
        );
        dtr_lead_debug("✅ Core webinar registration completed");
        dtr_lead_debug("✅ Registration result: " . print_r($result, true));
        dtr_lead_debug("🎉 Webinar registration successful via Ninja Forms hook!");
        // === DTR DEBUG REPORT SUMMARY ===
        dtr_lead_debug("=== FULL SUBMISSION DEBUG REPORT [ID: {$registration_data['debug_submission_id']}] ===");
        dtr_lead_debug("RAW FORM DATA: " . print_r($registration_data['debug_raw_fields'], true));
        dtr_lead_debug("FINAL WORKBOOKS RESULT: " . print_r($result, true));
        dtr_lead_debug("=== END SUBMISSION DEBUG REPORT ===");
        // === END DTR DEBUG REPORT SUMMARY ===
    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception during webinar registration: " . $e->getMessage());
    } catch (Error $e) {
        dtr_lead_debug("❌ Error during webinar registration: " . $e->getMessage());
    }
}

dtr_lead_debug("🔄 Ninja Forms Lead Generation Hook loaded and ready");
?>