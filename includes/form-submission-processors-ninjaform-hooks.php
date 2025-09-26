<?php
// TEST: Confirm logging works and file is loaded
error_log('DTR TEST LOG: form-submission-processors-ninjaform-hooks.php loaded');
// IMMEDIATE FILE LOAD LOG
$debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
    ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
    : __DIR__ . '/../logs/live-webinar-registration-debug.log';
file_put_contents($debug_log_file, '[' . date('Y-m-d H:i:s') . "] HOOKS FILE LOADED\n", FILE_APPEND | LOCK_EX);
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
            
        $debug_entry = "[" . date('Y-m-d H:i:s') . "] WEBINAR PROCESSOR CALLED - FORM ID 2 FROM WEBINAR PAGE\n";
        $debug_entry .= "Form ID: $form_id\n";
        $debug_entry .= "Debug ID: $debug_id\n";
        $debug_entry .= "Raw Form Data: " . print_r($form_data, true) . "\n";
        
        // Log current user info
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $debug_entry .= "Current User ID: {$current_user->ID}\n";
            $debug_entry .= "Current User Email: {$current_user->user_email}\n";
            $debug_entry .= "Person ID: " . get_user_meta($current_user->ID, 'workbooks_person_id', true) . "\n";
        } else {
            $debug_entry .= "User not logged in!\n";
        }
        
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
            
            // Fill missing user data from logged-in WordPress user
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                
                if (empty($registration_data['email'])) {
                    $registration_data['email'] = $current_user->user_email;
                    file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Filled email from logged-in user: " . $registration_data['email'] . "\n", FILE_APPEND | LOCK_EX);
                }
                
                if (empty($registration_data['first_name'])) {
                    $registration_data['first_name'] = get_user_meta($current_user->ID, 'first_name', true) ?: $current_user->display_name;
                    file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Filled first_name from logged-in user: " . $registration_data['first_name'] . "\n", FILE_APPEND | LOCK_EX);
                }
                
                if (empty($registration_data['last_name'])) {
                    $registration_data['last_name'] = get_user_meta($current_user->ID, 'last_name', true);
                    file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Filled last_name from logged-in user: " . $registration_data['last_name'] . "\n", FILE_APPEND | LOCK_EX);
                }
            } else {
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] User not logged in - cannot fill missing contact data\n", FILE_APPEND | LOCK_EX);
            }

        // Log the field extraction results
        $extraction_log = "[" . date('Y-m-d H:i:s') . "] FIELD EXTRACTION RESULTS:\n";
        $extraction_log .= "post_id: " . ($registration_data['post_id'] ?: 'NOT_FOUND') . "\n";
        $extraction_log .= "email: " . ($registration_data['email'] ?: 'NOT_FOUND') . "\n";
        $extraction_log .= "speaker_question: " . ($registration_data['speaker_question'] ?: 'NOT_FOUND') . "\n";
        $extraction_log .= "sponsor_optin: " . ($registration_data['cf_mailing_list_member_sponsor_1_optin'] ?: 'NOT_FOUND') . "\n";
        $extraction_log .= "first_name: " . ($registration_data['first_name'] ?: 'NOT_FOUND') . "\n";
        $extraction_log .= "last_name: " . ($registration_data['last_name'] ?: 'NOT_FOUND') . "\n";
        $extraction_log .= "event_id: " . ($registration_data['event_id'] ?: 'NOT_FOUND') . "\n";
        $extraction_log .= "---\n";
        file_put_contents($debug_log_file, $extraction_log, FILE_APPEND | LOCK_EX);

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

    // If registration was successful, register user locally for button state updates
    if ($result && !empty($result['success']) && is_user_logged_in()) {
        $post_id = $registration_data['post_id'];
        $user_id = get_current_user_id();
        
        // Include functions-events.php if needed
        if (!function_exists('register_user_for_event')) {
            $functions_events_file = get_template_directory() . '/functions/functions-events.php';
            if (file_exists($functions_events_file)) {
                include_once $functions_events_file;
            }
        }
        
        if (function_exists('register_user_for_event') && $post_id) {
            $local_registration_result = register_user_for_event($user_id, $post_id);
            $debug_local = "[" . date('Y-m-d H:i:s') . "] LOCAL REGISTRATION: user_id=$user_id, post_id=$post_id, result=" . ($local_registration_result ? 'SUCCESS' : 'ALREADY_REGISTERED') . "\n";
            file_put_contents($debug_log_file, $debug_local, FILE_APPEND | LOCK_EX);
            
            // Log success message that matches admin test format
            $success_msg = "[" . date('Y-m-d H:i:s') . "] ✅ WEBINAR REGISTRATION SUCCESS - User is now registered for event $post_id\n";
            file_put_contents($debug_log_file, $success_msg, FILE_APPEND | LOCK_EX);
        }
    }

    // Set global flag for ninja_forms_submit_response filter
    global $dtr_webinar_registration_success;
    $dtr_webinar_registration_success = $result && !empty($result['success']);

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
    // Debug: Log initialization
    $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
        ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
        : __DIR__ . '/../logs/live-webinar-registration-debug.log';
    file_put_contents($debug_log_file, '[' . date('Y-m-d H:i:s') . "] HOOKS INITIALIZED\n", FILE_APPEND | LOCK_EX);
    
    // Add a simple test hook to see if Ninja Forms is working
    add_action('ninja_forms_after_submission', function($form_data) {
        $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
            ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
            : __DIR__ . '/../logs/live-webinar-registration-debug.log';
        $form_id = $form_data['form_id'] ?? $form_data['id'] ?? 'unknown';
        file_put_contents($debug_log_file, '[' . date('Y-m-d H:i:s') . "] TEST HOOK FIRED - Form ID: $form_id\n", FILE_APPEND | LOCK_EX);
    }, 5); // Higher priority to fire first
    
    // Central dispatcher to route by form ID
    if (!has_action('ninja_forms_after_submission', 'dtr_dispatch_ninja_forms_submission')) {
        add_action('ninja_forms_after_submission', 'dtr_dispatch_ninja_forms_submission', 5, 1); // Higher priority (lower number)
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
        // case 2: // Webinar - REMOVED: Now using HTML form with AJAX submission
        //     Webinar registration is now handled by dtr_handle_webinar_submission() AJAX action
        //     Form ID 2 processing removed as requested
        case 31: // Lead gen
            file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] LEAD GEN CASE TRIGGERED (Form ID 31)\n", FILE_APPEND | LOCK_EX);
            
            if (!function_exists('dtr_handle_lead_generation_registration')) {
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Loading lead generation handler file\n", FILE_APPEND | LOCK_EX);
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
            
            // Log mapped lead data
            $debug_entry = "[" . date('Y-m-d H:i:s') . "] MAPPED LEAD DATA:\n";
            $debug_entry .= print_r($lead_data, true) . "\n";
            $debug_entry .= "---\n";
            file_put_contents($debug_log_file, $debug_entry, FILE_APPEND | LOCK_EX);
            
            // Create unique request ID to prevent duplicate form processing
            static $processed_forms = [];
            $request_id = md5(json_encode([
                'post_id' => $lead_data['post_id'] ?? '',
                'email' => $lead_data['email'] ?? '',
                'time_window' => floor(time() / 5) // 5-second window
            ]));
            
            // Check if we've already processed this exact request in this page load
            if (isset($processed_forms[$request_id])) {
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] DUPLICATE FORM SUBMISSION DETECTED - SKIPPING\n", FILE_APPEND | LOCK_EX);
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Original request already processed\n", FILE_APPEND | LOCK_EX);
                return;
            }
            
            // Mark this request as processed
            $processed_forms[$request_id] = true;
            
            file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Calling dtr_handle_lead_generation_registration\n", FILE_APPEND | LOCK_EX);
            $result = dtr_handle_lead_generation_registration($lead_data);
            
            // Log result
            $debug_entry = "[" . date('Y-m-d H:i:s') . "] LEAD HANDLER RESULT:\n";
            $debug_entry .= print_r($result, true) . "\n";
            $debug_entry .= "---\n";
            file_put_contents($debug_log_file, $debug_entry, FILE_APPEND | LOCK_EX);
            break;
        default:
            dtr_log_ninja_forms('No handler for form id ' . $form_id, $debug_id);
            break;
    }
}


// Initialize hooks when this file is loaded
dtr_init_ninja_forms_hooks();

// Add a custom debug message to the Ninja Forms AJAX response for the webinar form (ID 2) and lead gen form (ID 31)
// Add mapped registration data (Workbooks payload) to the Ninja Forms AJAX response
add_filter('ninja_forms_submit_response', function($response, $form_id) {
    // Log the original response structure for debugging
    error_log('[DEBUG] Original Response Structure for form ' . $form_id . ': ' . print_r($response, true));
    
    // Process lead gen form (31) only - webinar form (2) removed
    if ((int)$form_id === 31) {
        // Ensure data structure exists
        if (!isset($response['data'])) {
            $response['data'] = [];
        }
        
        // Fix the nonce error by providing proper response structure
        // The nonce error occurs because Ninja Forms expects errors.nonce to be a string, not an array
        if (!isset($response['errors'])) {
            $response['errors'] = [
                'form' => [],
                'fields' => []
            ];
        }
        
        // Ensure fields is an object, not array to prevent JS errors
        if (!isset($response['errors']['fields']) || !is_array($response['errors']['fields'])) {
            $response['errors']['fields'] = [];
        }
        
        // Explicitly set the correct nonce to prevent TypeError in JavaScript
        if (!isset($response['errors']['nonce'])) {
            $response['errors']['nonce'] = '';
        }
        
        // Set common success data
        $response['data']['success'] = true;
        
        // Add form-specific data
        if ((int)$form_id === 31) {
            // Lead generation form specific data
            $response['data']['debug_message'] = 'Lead generation form submitted successfully!';
        }
        
        // Log the final AJAX response structure before returning
        error_log('[DEBUG] FINAL AJAX RESPONSE SENT TO BROWSER: ' . print_r($response, true));
    }
    return $response;
}, 5, 2); // Higher priority to run first

// Console notification for successful loading (in footer)
add_action('wp_footer', function() {
    ?>
    <script>
        console.log('%c[DTR] All JavaScript interference disabled - testing native Ninja Forms behavior', 'color: blue; font-weight: bold;');
        // All custom form handling disabled to test if the issue is JavaScript interference
    </script>
    <?php
});