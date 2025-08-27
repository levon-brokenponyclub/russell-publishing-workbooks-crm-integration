<?php
/**
 * Ninja Forms Lead Generation & Webinar Registration Hook
 * Catches form submissions and registers leads/webinars in Workbooks
 */

if (!defined('ABSPATH')) exit;

// Always require the business logic handlers (modular, robust)
require_once __DIR__ . '/lead-generation-handler.php';
require_once __DIR__ . '/webinar-handler.php';

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

// Checkmarked step-by-step log for lead gen (form 31)
if (!function_exists('dtr_step_lead_log')) {
    function dtr_step_lead_log($message) {
        $simple_log_file = __DIR__ . '/../logs/lead-generation-debug.log';
        $logs_dir = dirname($simple_log_file);
        if (!file_exists($logs_dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($logs_dir);
            } else {
                mkdir($logs_dir, 0777, true);
            }
        }
        $log_entry = "[" . date('Y-m-d H:i:s') . "] $message\n";
        error_log($log_entry, 3, $simple_log_file);
    }
}

// Console log for update notification (visible in browser console on every page load)
add_action('wp_footer', function() {
    ?>
    <script>
        console.log('%c[Ninja Forms Simple Hook] Ready: Modular handlers loaded - 2025-08-26', 'color: green; font-weight: bold;');
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

        // === WEBINAR FORM ===
        if ($form_id == 2 || $form_id === '2') {
            dtr_lead_debug("✅ Processing webinar form (ID 2) and exiting to prevent other handlers");
            dtr_handle_webinar_form_submission($form_data, $submission_uuid);
            return;
        }

        // === LEAD GEN FORM ===
        if ($form_id == 31 || $form_id === '31') {
            $step = 1;
            dtr_step_lead_log("✅ STEP {$step}: Processing Lead Gen Form (ID $form_id)");
            $step++;

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
                    if (preg_match('/^cf_mailing_list_member_sponsor_question_(\d+)$/', $key, $matches)) {
                        $num = (int)$matches[1];
                        $sponsor_questions[$num] = $value;
                    }
                    // Handle ACF questions array (if present)
                    if ($key === 'acf_questions' && is_array($value)) {
                        $acf_idx = 2; // Start at 2 for cf_mailing_list_member_sponsor_question_2
                        foreach ($value as $acf_q) {
                            $sponsor_questions[$acf_idx] = $acf_q;
                            $acf_idx++;
                        }
                    }
                }
            }
            ksort($sponsor_questions);
            // Log sponsor questions as cf_mailing_list_member_sponsor_question_N: ...
            foreach ($sponsor_questions as $num => $answer) {
                dtr_step_lead_log("cf_mailing_list_member_sponsor_question_{$num}: $answer");
            }

            dtr_step_lead_log("✅ STEP {$step}: Fetched form values (Email: $email, PostID: $post_id)");
            $step++;

            // Call the lead gen handler
            $debug_report = [];
            dtr_step_lead_log("✅ STEP {$step}: Attempting to register lead in CRM...");
            $step++;

            $result = false;
            try {
                $result = dtr_register_workbooks_lead(
                    $post_id,
                    $email,
                    $first_name,
                    $last_name,
                    $sponsor_questions,
                    $cf_mailing_list_member_sponsor_1_optin,
                    $debug_report
                );
            } catch (\Throwable $e) {
                dtr_step_lead_log("❌ STEP {$step}: Exception in registration: {$e->getMessage()}");
                dtr_step_lead_log("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
                dtr_step_lead_log("\nRESULT INFORMATION:\n" . print_r([
                    'email' => $email,
                    'post_id' => $post_id,
                    'error' => $e->getMessage(),
                    'success' => 0,
                ], true));
                return;
            }

            foreach ($debug_report as $dbg) {
                dtr_step_lead_log($dbg);
            }

            if ($result === false || (is_array($result) && isset($result['success']) && !$result['success'])) {
                $err = '';
                if (is_array($result) && isset($result['error'])) {
                    $err = $result['error'];
                }
                dtr_step_lead_log("❌ STEP {$step}: Registration failed in CRM. Error: $err");
                dtr_step_lead_log("🎉 FINAL RESULT: LEAD REGISTRATION FAILED!");
                dtr_step_lead_log("\nRESULT INFORMATION:\n" . print_r([
                    'email' => $email,
                    'post_id' => $post_id,
                    'error' => $err,
                    'success' => 0,
                ], true));
            } else {
                dtr_step_lead_log("✅ STEP {$step}: Lead registered in CRM");
                dtr_step_lead_log("🎉 FINAL RESULT: LEAD REGISTRATION SUCCESSFUL!");
                dtr_step_lead_log("\nRESULT INFORMATION:\n" . print_r([
                    'email' => $email,
                    'post_id' => $post_id,
                    'success' => 1,
                ], true));
            }
            return;
        }

        // Fallback: log that form is not recognized
        dtr_lead_debug("ℹ️  Form ID $form_id not configured for lead generation or webinar processing");
        return;

    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception during lead/webinar registration: " . $e->getMessage());
        dtr_lead_debug("Exception details: " . print_r($e, true));
    } catch (Error $e) {
        dtr_lead_debug("❌ Error (PHP 7+) during lead/webinar registration: " . $e->getMessage());
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

    // Prepare registration data payload
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
        dtr_lead_debug("=== FULL SUBMISSION DEBUG REPORT [ID: {$registration_data['debug_submission_id']}] ===");
        dtr_lead_debug("RAW FORM DATA: " . print_r($registration_data['debug_raw_fields'], true));
        dtr_lead_debug("FINAL WORKBOOKS RESULT: " . print_r($result, true));
        dtr_lead_debug("=== END SUBMISSION DEBUG REPORT ===");
    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception during webinar registration: " . $e->getMessage());
    } catch (Error $e) {
        dtr_lead_debug("❌ Error during webinar registration: " . $e->getMessage());
    }
}

dtr_lead_debug("🔄 Ninja Forms Lead Generation Hook loaded and ready");
?>