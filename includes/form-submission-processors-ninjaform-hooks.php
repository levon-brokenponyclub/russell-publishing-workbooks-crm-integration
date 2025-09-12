<?php
// TEST: Confirm logging works and file is loaded
error_log('DTR TEST LOG: form-submission-processors-ninjaform-hooks.php loaded');
// FILE-LEVEL DEBUG: Confirm dispatcher file is loaded by WordPress
$debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
    ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
    : __DIR__ . '/../logs/live-webinar-registration-debug.log';
file_put_contents($debug_log_file, '[' . date('Y-m-d H:i:s') . "] FILE LOAD: form-submission-processors-ninjaform-hooks.php\n", FILE_APPEND | LOCK_EX);

// === DTR DEV: DISABLE ALL CUSTOM NINJA FORMS HANDLERS (PREVIEW MODE) ===
if (defined('DTR_NF_DISABLE_HANDLERS') && DTR_NF_DISABLE_HANDLERS) {
    // Remove all custom plugin handlers for Ninja Forms submissions, but allow Ninja Forms core to process as normal
    add_action('init', function() {
        // Remove our custom after_submission dispatcher if present
        remove_action('ninja_forms_after_submission', 'dtr_dispatch_ninja_forms_submission', 9);
        // Remove any other custom after_submission handlers here as needed
    }, 20);
    // Optionally log
    $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
        ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
        : __DIR__ . '/../logs/live-webinar-registration-debug.log';
    file_put_contents($debug_log_file, '[' . date('Y-m-d H:i:s') . "] HANDLERS DISABLED: Custom plugin handlers removed, Ninja Forms core will process as normal.\n", FILE_APPEND | LOCK_EX);
}
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
 * Extract ACF questions from form data
 */
function dtr_extract_acf_questions($form_data) {
    $acf_questions = [];
    
    // Check direct form data for ACF question fields
    foreach ($form_data as $key => $value) {
        if (strpos($key, 'acf_question_') === 0 && !empty($value)) {
            $acf_questions[$key] = trim($value);
        }
    }
    
    // Check fields array
    if (isset($form_data['fields']) && is_array($form_data['fields'])) {
        foreach ($form_data['fields'] as $field) {
            if (!is_array($field)) continue;
            
            $field_key = $field['key'] ?? '';
            $field_value = $field['value'] ?? '';
            
            if (strpos($field_key, 'acf_question_') === 0 && !empty($field_value)) {
                $acf_questions[$field_key] = trim($field_value);
            }
        }
    }
    
    return $acf_questions;
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
        // Enhanced diagnostic logging
        $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
            ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
            : __DIR__ . '/../logs/live-webinar-registration-debug.log';
            
        $debug_entry = "[" . date('Y-m-d H:i:s') . "] WEBINAR PROCESSOR CALLED\n";
        $debug_entry .= "Form ID: $form_id\n";
        $debug_entry .= "Debug ID: $debug_id\n";
        $debug_entry .= "Form Data: " . print_r($form_data, true) . "\n";
        $debug_entry .= "---\n";
        
        file_put_contents($debug_log_file, $debug_entry, FILE_APPEND | LOCK_EX);
            // Map Ninja Forms fields to expected handler fields
            $speaker_question = dtr_extract_field_by_keys($form_data, ['speaker_question', 'question_for_speaker']);
            $registration_data = [
                'post_id' => dtr_extract_field_by_keys($form_data, ['post_id', 'event_id', 'webinar_post_id']),
                'email' => dtr_extract_field_by_keys($form_data, ['email', 'user_email', 'email_address']),
                'speaker_question' => $speaker_question,
                'cf_mailing_list_member_sponsor_1_optin' => dtr_extract_field_by_keys($form_data, ['cf_mailing_list_member_sponsor_1_optin', 'sponsor_optin']),
                'first_name' => dtr_extract_field_by_keys($form_data, ['first_name', 'fname']),
                'last_name' => dtr_extract_field_by_keys($form_data, ['last_name', 'lname']),
                'event_id' => dtr_extract_field_by_keys($form_data, ['event_id', 'workbooks_reference']),
            ];

            // Log registration data in the old debug format
            $log_file = defined('DTR_WORKBOOKS_LOG_DIR')
                ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
                : __DIR__ . '/../logs/live-webinar-registration-debug.log';
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
            $log_entry = '';
            foreach ($result_info as $k => $v) {
                $log_entry .= "[$k] => $v\n";
            }
            $log_entry .= "[" . date('Y-m-d H:i:s') . "] ✅ STEP 1: Processing Webinar Form (ID " . ($registration_data['post_id'] ?? '') . ")\n\n";
            file_put_contents($log_file, $log_entry, FILE_APPEND);

        // Make available for ninja_forms_submit_response filter (for frontend debugging)
        global $dtr_last_webinar_registration_data;
        $dtr_last_webinar_registration_data = $registration_data;
        
        // Log mapped registration data
        $debug_entry = "[" . date('Y-m-d H:i:s') . "] MAPPED REGISTRATION DATA:\n";
        $debug_entry .= print_r($registration_data, true) . "\n";
        $debug_entry .= "---\n";
        file_put_contents($debug_log_file, $debug_entry, FILE_APPEND | LOCK_EX);
        if (!function_exists('dtr_handle_live_webinar_registration')) {
            file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Loading webinar handler file\n", FILE_APPEND | LOCK_EX);
            require_once __DIR__ . '/form-handler-live-webinar-registration.php';
        }
        
        file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Calling dtr_handle_live_webinar_registration\n", FILE_APPEND | LOCK_EX);
    $result = dtr_handle_live_webinar_registration($registration_data);

    // Log event ID, person ID, and email address
    $event_id = $registration_data['event_id'] ?? '';
    $person_id = is_array($result) && isset($result['person_id']) ? $result['person_id'] : '';
    $email = $registration_data['email'] ?? '';
    $log_ids = '[' . date('Y-m-d H:i:s') . "] EVENT/REGISTRATION IDS: event_id=$event_id, person_id=$person_id, email=$email\n";
    file_put_contents($debug_log_file, $log_ids, FILE_APPEND | LOCK_EX);

    // Log result
    $debug_entry = "[" . date('Y-m-d H:i:s') . "] HANDLER RESULT:\n";
    $debug_entry .= print_r($result, true) . "\n";
    $debug_entry .= "---\n";
    file_put_contents($debug_log_file, $debug_entry, FILE_APPEND | LOCK_EX);

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
    // Guaranteed debug log at dispatcher entry
    $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
        ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
        : __DIR__ . '/../logs/live-webinar-registration-debug.log';
    file_put_contents($debug_log_file, '[' . date('Y-m-d H:i:s') . "] DISPATCHER ENTRY\n", FILE_APPEND | LOCK_EX);
    $form_id = $form_data['form_id'] ?? $form_data['id'] ?? '';
    $debug_id = 'NF-' . uniqid();
    
    // Enhanced debug logging
    $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
        ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
        : __DIR__ . '/../logs/live-webinar-registration-debug.log';
    
    $debug_entry = "[" . date('Y-m-d H:i:s') . "] DISPATCHER CALLED\n";
    $debug_entry .= "Form ID: $form_id\n";
    $debug_entry .= "Debug ID: $debug_id\n";
    $debug_entry .= "Form Data: " . print_r($form_data, true) . "\n";
    $debug_entry .= "---\n";
    
    file_put_contents($debug_log_file, $debug_entry, FILE_APPEND | LOCK_EX);
    
    // Also log to error log
    error_log("DTR: Form submission dispatcher called for form_id=$form_id");
    dtr_log_ninja_forms('Dispatch submission for form_id=' . $form_id, $debug_id);

    if (!$form_id) { return; }

    switch ((int)$form_id) {
        case 15: // Registration form
            if (function_exists('dtr_process_user_registration')) {
                dtr_process_user_registration($form_data, 15); // enhanced handler
            }
            break;
        case 2: // Webinar
            file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] WEBINAR CASE TRIGGERED (Form ID 2)\n", FILE_APPEND | LOCK_EX);
            if (function_exists('dtr_process_webinar_registration')) {
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Calling dtr_process_webinar_registration\n", FILE_APPEND | LOCK_EX);
                $result = dtr_process_webinar_registration($form_data, 2, $debug_id);
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] dtr_process_webinar_registration result: " . print_r($result, true) . "\n", FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] ERROR: dtr_process_webinar_registration function not found\n", FILE_APPEND | LOCK_EX);
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

// Add a custom debug message to the Ninja Forms AJAX response for the webinar form (ID 2)
// Add mapped registration data (Workbooks payload) to the Ninja Forms AJAX response for the webinar form (ID 2)
add_filter('ninja_forms_submit_response', function($response, $form_id) {
    if ((int)$form_id === 2) {
        // Try to get mapped registration data from global or static var set in dtr_process_webinar_registration
        global $dtr_last_webinar_registration_data;
        if (!empty($dtr_last_webinar_registration_data) && is_array($dtr_last_webinar_registration_data)) {
            // Format as key => value pairs for alerting
            $lines = [];
            foreach ($dtr_last_webinar_registration_data as $k => $v) {
                $lines[] = "[$k] => $v";
            }
            $response['data']['debug'][] = implode("\n", $lines);
        }
        $response['data']['debug_message'] = 'Webinar handler executed!';
    }
    return $response;
}, 20, 2);

// Console notification for successful loading (in footer)
add_action('wp_footer', function() {
    ?>
    <script>
        console.log('%c[DTR Ninja Forms Clean Hook] Successfully loaded unified form handler', 'color: green; font-weight: bold;');
        // Display debug_message from Ninja Forms AJAX response for Webinar form (ID 2)
        document.addEventListener('nfFormSubmitResponse', function(e, data) {
            if (!data || !data.data || !data.data.form_id) return;
            if (data.data.form_id !== 2) return; // Only for Webinar form
            // Show debug_message if present
            if (data.data.debug_message) {
                var msg = document.createElement('div');
                msg.className = 'nf-debug-message nf-webinar-debug-message';
                msg.style = 'color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; font-weight: bold;';
                msg.innerText = data.data.debug_message;
                var form = document.getElementById('nf-form-2-cont');
                if (form) {
                    var msgArea = form.querySelector('.nf-response-msg') || form;
                    msgArea.prepend(msg);
                } else {
                    document.body.prepend(msg);
                }
            }
        });
    </script>
    <?php
});