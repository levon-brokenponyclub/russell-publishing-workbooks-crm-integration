<?php
// TEMP: Confirm dispatcher file is loaded
if (defined('DTR_WORKBOOKS_LOG_DIR')) {
    $file = DTR_WORKBOOKS_LOG_DIR . 'live-webinar-debug.log';
    file_put_contents($file, date('c') . " -- form-submission-processors-ninjaform-hooks.php loaded\n", FILE_APPEND | LOCK_EX);
}
/**
 * DTR Ninja Forms Clean Hook - Unified form handler
 * 
 * (c) 2025 BrokenPonyClub
 */

/**
 * Get form data summary for logging
 *
 * @param array $form_data Form submission data
 * @return string Summary string
 */
function dtr_get_form_data_summary($form_data) {
    $summary = [];

    // Add form ID for easier debugging
    $form_id = $form_data['form_id'] ?? ($form_data['id'] ?? null);
    if ($form_id) {
        $summary[] = "Form ID: " . $form_id;
    }

    if (isset($form_data['fields']) && is_array($form_data['fields'])) {
        $summary[] = "Fields: " . count($form_data['fields']);
    }

    $email = dtr_extract_field_by_keys($form_data, ['email', 'email_address']);
    if ($email) {
        $summary[] = "Email: " . $email;
    }

    $name_parts = [];
    $first_name = dtr_extract_field_by_keys($form_data, ['first_name', 'fname']);
    $last_name = dtr_extract_field_by_keys($form_data, ['last_name', 'lname']);

    if ($first_name) $name_parts[] = $first_name;
    if ($last_name) $name_parts[] = $last_name;

    if (!empty($name_parts)) {
        $summary[] = "Name: " . implode(' ', $name_parts);
    }

    return implode(', ', $summary) ?: 'No identifiable data';
}

/**
 * Preprocess AJAX submissions
 *
 * @param string $debug_id Debug identifier
 * @return void
 */
function dtr_preprocess_ajax_submission($debug_id) {
    // Sanitize AJAX POST data
    $_POST = array_map('sanitize_text_field', $_POST);

    // Log AJAX submission details
    dtr_log_ninja_forms("AJAX POST data keys: " . implode(', ', array_keys($_POST)), $debug_id);
}

/**
 * Send processing notifications (email alerts, webhooks, etc.)
 *
 * @param array $form_data Form submission data
 * @param int $form_id Form ID
 * @param bool $success Processing success status
 * @param string $debug_id Debug identifier
 * @return void
 */
function dtr_send_processing_notifications($form_data, $form_id, $success, $debug_id) {
    $status = $success ? 'SUCCESS' : 'FAILURE';
    $email = dtr_extract_field_by_keys($form_data, ['email', 'email_address']);

    dtr_log_ninja_forms("Notification: Form {$form_id} processing {$status} for {$email}", $debug_id);

    // Send admin notification on failure
    if (!$success && function_exists('wp_mail')) {
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $subject = "DTR Form Processing Failed - Form ID {$form_id}";
            $message = "Form processing failed for form ID {$form_id}\n";
            $message .= "Debug ID: {$debug_id}\n";
            $message .= "User email: " . ($email ?: 'Unknown') . "\n";
            $message .= "Time: " . current_time('Y-m-d H:i:s') . "\n";

            wp_mail($admin_email, $subject, $message);
        }
    }

    // Hook for custom notifications
    do_action('dtr_ninja_forms_processing_complete', $form_data, $form_id, $success, $debug_id);
}

/**
 * Log Ninja Forms debug information
 *
 * @param string $message Debug message
 * @param string $debug_id Debug identifier
 * @return void
 */
function dtr_log_ninja_forms($message, $debug_id = '') {
    if (!function_exists('error_log')) {
        return;
    }

    $timestamp = current_time('Y-m-d H:i:s');
    $prefix = $debug_id ? "[{$debug_id}]" : '[DTR-NinjaForms]';
    $formatted_message = "{$timestamp} {$prefix} {$message}";

    error_log($formatted_message);

    // Also log to custom DTR log if function exists
    if (function_exists('dtr_custom_log')) {
        dtr_custom_log($formatted_message);
    }
}

/**
 * Extract field value by trying multiple possible keys
 *
 * @param array $form_data Form data
 * @param array $possible_keys Array of keys to try
 * @return string|null Field value or null if not found
 */
function dtr_extract_field_by_keys($form_data, $possible_keys) {
    // Check direct form data
    foreach ($possible_keys as $key) {
        if (isset($form_data[$key]) && !empty($form_data[$key])) {
            return trim($form_data[$key]);
        }
    }

    // Check fields array
    if (isset($form_data['fields']) && is_array($form_data['fields'])) {
        foreach ($form_data['fields'] as $field) {
            if (!is_array($field)) continue;

            $field_key = $field['key'] ?? '';
            $field_value = $field['value'] ?? '';

            if (in_array($field_key, $possible_keys) && !empty($field_value)) {
                return trim($field_value);
            }
        }
    }

    return null;
}

/**
 * Process user registration forms (Form ID 15)
 *
 * @param array $form_data Form submission data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Processing success status
 */
// Guard: avoid redefining enhanced membership handler (ninja-forms-membership-registration.php)
if (!function_exists('dtr_process_user_registration')) {
    function dtr_process_user_registration($form_data, $form_id, $debug_id) {
        dtr_log_ninja_forms("(Fallback) Processing user registration form", $debug_id);
        return false; // Enhanced handler expected; fallback does nothing
    }
}

/**
 * Process webinar registration forms (Form ID 2)
 *
 * @param array $form_data Form submission data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Processing success status
 */
if (!function_exists('dtr_process_webinar_registration')) {
    function dtr_process_webinar_registration($form_data, $form_id, $debug_id) {
        // Diagnostic: Log entry to confirm processor is called
        if (defined('DTR_WORKBOOKS_LOG_DIR')) {
            $diag_file = DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log';
            file_put_contents($diag_file, date('c') . " -- dtr_process_webinar_registration CALLED\nForm Data: " . print_r($form_data, true) . "\n", FILE_APPEND | LOCK_EX);
        }
        // Map Ninja Forms fields to expected handler fields
        $registration_data = [
            'post_id' => dtr_extract_field_by_keys($form_data, ['post_id', 'event_id', 'webinar_post_id']),
            'email' => dtr_extract_field_by_keys($form_data, ['email', 'user_email', 'email_address']),
            'first_name' => dtr_extract_field_by_keys($form_data, ['first_name', 'user_first_name', 'fname']),
            'last_name' => dtr_extract_field_by_keys($form_data, ['last_name', 'user_last_name', 'lname']),
            'speaker_question' => dtr_extract_field_by_keys($form_data, ['speaker_question', 'question_for_speaker']),
            'cf_mailing_list_member_sponsor_1_optin' => dtr_extract_field_by_keys($form_data, ['cf_mailing_list_member_sponsor_1_optin', 'sponsor_optin']),
            'event_id' => dtr_extract_field_by_keys($form_data, ['event_id', 'workbooks_reference']),
        ];
        if (!function_exists('dtr_handle_live_webinar_registration')) {
            require_once __DIR__ . '/form-handler-live-webinar-registration.php';
        }
        $result = dtr_handle_live_webinar_registration($registration_data);
        // Optionally log result or handle errors here
        return $result && !empty($result['success']);
    }
}

/**
 * Process lead generation forms (Form ID 31)
 *
 * @param array $form_data Form submission data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Processing success status
 */
if (!function_exists('dtr_process_lead_generation')) {
    function dtr_process_lead_generation($form_data, $form_id, $debug_id) {
        dtr_log_ninja_forms('(Fallback) Lead generation handler missing', $debug_id);
        return false;
    }
}

/**
 * Initialize Ninja Forms hooks.
 *
 * This is a placeholder. Implement your hooks here.
 */
function dtr_init_ninja_forms_hooks() {
    // Central dispatcher to route by form ID
    if (!has_action('ninja_forms_after_submission', 'dtr_dispatch_ninja_forms_submission')) {
        add_action('ninja_forms_after_submission', 'dtr_dispatch_ninja_forms_submission', 9, 1);
    }
}

function dtr_dispatch_ninja_forms_submission($form_data) {
    // TEMP: Confirm dispatcher is called
    error_log('dtr_dispatch_ninja_forms_submission called for form_id=' . ($form_data['form_id'] ?? ($form_data['id'] ?? '')));
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $file = DTR_WORKBOOKS_LOG_DIR . 'live-webinar-debug.log';
        file_put_contents($file, date('c') . " -- dtr_dispatch_ninja_forms_submission called for form_id=" . ($form_data['form_id'] ?? ($form_data['id'] ?? '')) . "\n", FILE_APPEND | LOCK_EX);
    }
    $debug_id = 'NF-' . uniqid();
    $form_id = $form_data['form_id'] ?? ($form_data['id'] ?? null);
    dtr_log_ninja_forms('Dispatch submission for form_id=' . $form_id, $debug_id);

    if (!$form_id) { return; }

    switch ((int)$form_id) {
        case 15: // Registration form
            if (function_exists('dtr_process_user_registration')) {
                dtr_process_user_registration($form_data, 15); // enhanced handler
            }
            break;
        case 2: // Webinar
            if (function_exists('dtr_process_webinar_registration')) {
                dtr_process_webinar_registration($form_data, 2, $debug_id);
            }
            break;
        case 31: // Lead gen
            if (!function_exists('dtr_handle_lead_generation_registration')) {
                require_once __DIR__ . '/form-handler-lead-generation-registration.php';
            }
            // Map fields for lead gen (mirroring webinar logic)
            $lead_data = [
                'post_id' => dtr_extract_field_by_keys($form_data, ['post_id', 'lead_post_id']),
                'email' => dtr_extract_field_by_keys($form_data, ['email', 'user_email', 'email_address']),
                'first_name' => dtr_extract_field_by_keys($form_data, ['first_name', 'user_first_name', 'fname']),
                'last_name' => dtr_extract_field_by_keys($form_data, ['last_name', 'user_last_name', 'lname']),
                'lead_question' => dtr_extract_field_by_keys($form_data, ['lead_question', 'question_for_lead']),
                'cf_mailing_list_member_sponsor_1_optin' => dtr_extract_field_by_keys($form_data, ['cf_mailing_list_member_sponsor_1_optin', 'sponsor_optin']),
                'person_id' => dtr_extract_field_by_keys($form_data, ['person_id']),
            ];
            dtr_handle_lead_generation_registration($lead_data);
            break;
        default:
            dtr_log_ninja_forms('No handler for form id ' . $form_id, $debug_id);
            break;
    }
}

// Initialize hooks when this file is loaded
dtr_init_ninja_forms_hooks();

// Console notification for successful loading (in footer)
add_action('wp_footer', function() {
    ?>
    <script>
        console.log('%c[DTR Ninja Forms Clean Hook] Successfully loaded unified form handler', 'color: green; font-weight: bold;');
        // TEMP: Alert on Ninja Forms submission success/fail
        document.addEventListener('nfFormSubmitResponse', function(e, data) {
            if (!data || !data.data || !data.data.form_id) return;
            if (data.data.form_id !== 2) return; // Only for Webinar form
            if (data.data.result && data.data.result.success) {
                alert('Webinar registration successful!');
            } else {
                alert('Webinar registration failed.');
            }
        });
    </script>
    <?php
});