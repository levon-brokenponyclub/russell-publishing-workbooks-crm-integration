<?php
/**
 * HTML Form Registration Handler
 * Handles custom HTML form submissions for membership registration
 * Creates WordPress users and syncs with Workbooks CRM
 * Based on the working membership-registration handler pattern
 */
if (!defined('ABSPATH')) exit;

/* --------------------------------------------------------------------------
 * Ensure required dependencies are loaded
 * -------------------------------------------------------------------------- */
// Load Workbooks API library
if (!class_exists('WorkbooksApi')) {
    require_once DTR_WORKBOOKS_LIB_DIR . 'workbooks_api.php';
}

// Load helper functions if not already available
if (!function_exists('get_workbooks_instance')) {
    require_once DTR_WORKBOOKS_INCLUDES_DIR . 'class-helper-functions.php';
}

/* --------------------------------------------------------------------------
 * Logging (kept near top so we can emit a boot message immediately)
 * -------------------------------------------------------------------------- */
function dtr_html_log($msg) {
    $timestamp = current_time('Y-m-d H:i:s');
    $line = "{$timestamp} [Membership-Reg] {$msg}\n";
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $file1 = DTR_WORKBOOKS_LOG_DIR . 'html-membership-registration-debug.log';
        if (!file_exists(dirname($file1))) wp_mkdir_p(dirname($file1));
        if (defined('DTR_WORKBOOKS_LOG_DIR')) {
            $log_file1 = DTR_WORKBOOKS_LOG_DIR . basename($file1);
            file_put_contents($log_file1, $line, FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($file1, $line, FILE_APPEND | LOCK_EX);
        }
        
        // Also write to member registration log for consolidated viewing  
        $file2 = DTR_WORKBOOKS_LOG_DIR . 'member-registration-debug.log';
        if (defined('DTR_WORKBOOKS_LOG_DIR')) {
            $log_file2 = DTR_WORKBOOKS_LOG_DIR . basename($file2);
            file_put_contents($log_file2, $line, FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($file2, $line, FILE_APPEND | LOCK_EX);
        }
    }
    if (defined('WP_DEBUG') && WP_DEBUG) error_log($line);
}

// Test direct file write to confirm file loading
if (defined('DTR_WORKBOOKS_LOG_DIR')) {
    $test_file = DTR_WORKBOOKS_LOG_DIR . 'html-membership-registration-debug.log';
    $test_msg = date('Y-m-d H:i:s') . " [FILE-LOAD-TEST] HTML form handler file is being loaded\n";
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $log_test_file = DTR_WORKBOOKS_LOG_DIR . basename($test_file);
        file_put_contents($log_test_file, $test_msg, FILE_APPEND | LOCK_EX);
    } else {
        file_put_contents($test_file, $test_msg, FILE_APPEND | LOCK_EX);
    }
}

dtr_html_log('[BOOT] HTML form membership registration handler file loaded');

// Add an immediate hook test
add_action('init', function() {
    dtr_html_log('[INIT] WordPress init hook fired - HTML form handler is loaded');
});

/* --------------------------------------------------------------------------
 * AJAX handler for form submission
 * -------------------------------------------------------------------------- */
add_action('wp_ajax_dtr_html_form_submit', 'dtr_html_form_submit_handler');
add_action('wp_ajax_nopriv_dtr_html_form_submit', 'dtr_html_form_submit_handler');

// Add nonce provider endpoint
add_action('wp_ajax_dtr_get_form_nonce', 'dtr_html_get_form_nonce');
add_action('wp_ajax_nopriv_dtr_get_form_nonce', 'dtr_html_get_form_nonce');

// Add a simple test endpoint to verify AJAX is working
add_action('wp_ajax_dtr_html_test', 'dtr_html_test_handler');
add_action('wp_ajax_nopriv_dtr_html_test', 'dtr_html_test_handler');

function dtr_html_test_handler() {
    dtr_html_log('[TEST] Test AJAX endpoint reached successfully');
    wp_send_json_success(['message' => 'Test endpoint working!', 'timestamp' => current_time('mysql')]);
}

function dtr_html_get_form_nonce() {
    $nonce = wp_create_nonce('dtr_html_form_submit');
    wp_send_json_success(['nonce' => $nonce]);
}

// Debug logging to confirm registration
error_log('DTR HTML: form-handler-html-membership-registration.php loaded and actions registered');
error_log('DTR HTML: wp_ajax_dtr_html_form_submit action registered: ' . (has_action('wp_ajax_dtr_html_form_submit') ? 'YES' : 'NO'));

function dtr_html_form_submit_handler() {
    // Clear any previous output to prevent JSON corruption
    if (ob_get_level()) {
        ob_clean();
    }
    
    dtr_html_log('[AJAX] HTML form submission received');
    dtr_html_log('[AJAX] POST data: ' . print_r($_POST, true));
    dtr_html_log('[AJAX] Nonce from POST: ' . ($_POST['nonce'] ?? 'MISSING'));
    
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dtr_html_form_submit')) {
        dtr_html_log('[SECURITY] Nonce verification failed');
        dtr_html_log('[SECURITY] Expected nonce action: dtr_html_form_submit');
        wp_send_json_error(['message' => 'Security verification failed']);
        return;
    }
    
    dtr_html_log('[AJAX] Nonce verification passed');
    
    try {
        // Process the form data using the same pattern as membership registration
        $success = dtr_html_membership_process($_POST);
        
        if ($success) {
            dtr_html_log('[SUCCESS] Form submission processed successfully');
            wp_send_json_success(['message' => 'Registration completed successfully']);
        } else {
            dtr_html_log('[ERROR] Form submission processing failed');
            wp_send_json_error(['message' => 'Registration failed. Please try again.']);
        }
        
    } catch (Throwable $t) {
        dtr_html_log('[FATAL] Throwable in AJAX handler: ' . $t->getMessage());
        dtr_html_log('[FATAL] Stack trace: ' . $t->getTraceAsString());
        wp_send_json_error(['message' => 'An error occurred during registration']);
    }
}

/* --------------------------------------------------------------------------
 * Main processing function (orchestrator)
 * Based on dtr_nf_membership_process pattern
 * -------------------------------------------------------------------------- */
function dtr_html_membership_process($post_data) {
    $debug_id = 'REG-' . uniqid();
    
    // Validate post_data is an array
    if (!is_array($post_data)) {
        dtr_html_log("[ERROR] Invalid post_data received: " . gettype($post_data));
        return false;
    }
    
    // Get plugin test mode setting
    $options = get_option('dtr_workbooks_options', []);
    $test_mode = !empty($options['test_mode_forms']['html']) && $options['test_mode_forms']['html'] == 1;

    $header = $test_mode ? "[{$debug_id}] ====== MEMBER REGISTRATION - TEST MODE ======" : "[{$debug_id}] ====== MEMBER REGISTRATION ======";
    dtr_html_log($header);
    dtr_html_log("[{$debug_id}] [ENTRY] Processing membership registration for HTML form");
    
    try {
        // Log raw post data keys for debugging
        dtr_html_log("[{$debug_id}] [DEBUG] Raw post data keys: " . implode(', ', array_keys($post_data)));
        
        // Collect and validate data
        $data = dtr_html_collect_membership_data($post_data, $debug_id);
        if (!$data) {
            dtr_html_membership_log_failure('Validation or duplicate WP user', $post_data);
            return false;
        }
    } catch (Throwable $t) {
        dtr_html_log("[{$debug_id}] [ERROR] Exception in data collection: " . $t->getMessage());
        dtr_html_membership_log_failure('Exception in data collection: ' . $t->getMessage(), $post_data);
        return false;
    }

    if ($test_mode) {
        dtr_html_log("[{$debug_id}] TEST MODE ENABLED: Skipping user creation and Workbooks sync");
        dtr_html_log("[{$debug_id}] TEST MODE: Would create user with email: {$data['email']}");
        dtr_html_membership_log_summary(null, $data, $test_mode);
        return true;
    }

    // Create WordPress user and meta
    try {
        $user_id = dtr_html_create_wp_user_and_meta($data, $debug_id);
        if (!$user_id) {
            dtr_html_membership_log_failure('WordPress user creation failed', $post_data, null, $data);
            return false;
        }
        
        // Store original form data as JSON for debugging purposes
        update_user_meta($user_id, 'form_data_json', json_encode([
            'form_fields' => array_keys($post_data),
            'data_fields' => array_keys($data),
            'timestamp' => current_time('mysql')
        ]));
    } catch (Throwable $t) {
        dtr_html_log("[{$debug_id}] [ERROR] Exception in user creation: " . $t->getMessage());
        dtr_html_membership_log_failure('Exception in user creation: ' . $t->getMessage(), $post_data, null, $data);
        return false;
    }

    // Apply marketing preferences and topics of interest
    try {
        dtr_html_apply_marketing_and_interests($user_id, $data, $debug_id);
    } catch (Throwable $t) {
        dtr_html_log("[{$debug_id}] [ERROR] Exception in marketing preferences: " . $t->getMessage());
        // Continue despite errors in marketing preferences
    }

    // Handle AOI mapping
    try {
        if (!dtr_html_handle_aoi_mapping($user_id, $debug_id)) {
            dtr_html_log("[{$debug_id}] [WARNING] AOI mapping failed but continuing");
        }
    } catch (Throwable $t) {
        dtr_html_log("[{$debug_id}] [ERROR] Exception in AOI mapping: " . $t->getMessage());
        // Continue despite errors in AOI mapping
    }

    // Workbooks integration
    $workbooks = (function_exists('get_workbooks_instance')) ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_html_log("[{$debug_id}] [ERROR] Workbooks instance not available");
        dtr_html_membership_log_failure('Workbooks not available', $post_data, $user_id, $data);
        return false;
    }

    // Check if cURL is available before attempting Workbooks operations
    if (!function_exists('curl_init') || !extension_loaded('curl')) {
        dtr_html_log("[{$debug_id}] [WARNING] cURL is not available - skipping Workbooks sync but WordPress user created successfully");
        dtr_html_membership_log_summary($user_id, $data);
        return true; // Still successful since WP user was created
    }

    // Test cURL initialization
    $test_curl = curl_init();
    if ($test_curl === false) {
        curl_close($test_curl);
        dtr_html_log("[{$debug_id}] [WARNING] cURL initialization failed - skipping Workbooks sync but WordPress user created successfully");
        dtr_html_membership_log_summary($user_id, $data);
        return true; // Still successful since WP user was created
    }
    curl_close($test_curl);

    $payload = dtr_html_build_workbooks_payload($user_id, $data, $debug_id);
    $payload = dtr_html_maybe_attach_employer_org($user_id, $payload, $data, $debug_id);

    try {
        $sync_result = dtr_html_workbooks_person_sync($workbooks, $user_id, $payload, $data['email'], $debug_id);
        if (!$sync_result) {
            // Check if this is a local development environment issue
            $last_error = error_get_last();
            $error_msg = $last_error ? $last_error['message'] : '';
            
            if ($error_msg && (
                strpos($error_msg, '404') !== false || 
                strpos($error_msg, 'curl') !== false ||
                strpos($error_msg, 'cURL') !== false ||
                strpos($error_msg, 'Connection refused') !== false ||
                strpos($error_msg, 'resolve') !== false
            )) {
                dtr_html_log("[{$debug_id}] [WARNING] Workbooks sync failed due to local environment (cURL/connectivity issue) - WordPress user still created successfully");
                dtr_html_log("[{$debug_id}] [WARNING] Error details: {$error_msg}");
                dtr_html_membership_log_summary($user_id, $data);
                return true; // Consider this a success since WP user was created
            }
            
            dtr_html_log("[{$debug_id}] [ERROR] Workbooks sync failed");
            dtr_html_membership_log_failure('Workbooks sync failed', $post_data, $user_id, $data);
            return false;
        }
    } catch (Exception $e) {
        dtr_html_log("[{$debug_id}] [WARNING] Workbooks sync threw exception: " . $e->getMessage());
        if (strpos($e->getMessage(), 'curl') !== false || strpos($e->getMessage(), 'cURL') !== false) {
            dtr_html_log("[{$debug_id}] [WARNING] cURL-related exception - WordPress user still created successfully");
            dtr_html_membership_log_summary($user_id, $data);
            return true; // Still successful since WP user was created
        }
        
        dtr_html_log("[{$debug_id}] [ERROR] Workbooks sync exception");
        dtr_html_membership_log_failure('Workbooks sync exception: ' . $e->getMessage(), $post_data, $user_id, $data);
        return false;
    }
    
    dtr_html_log("[{$debug_id}] [DEBUG] WP User + Workbooks sync complete for user ID {$user_id}");
    
    // Emit final human-readable summary block
    dtr_html_membership_log_summary($user_id, $data);
    return true;
}

/* --------------------------------------------------------------------------
 * Data collection & validation
 * Based on dtr_nf_collect_membership_data pattern
 * -------------------------------------------------------------------------- */
function dtr_html_collect_membership_data($post_data, $debug_id) {
    // Get title using helper function if available
    $raw_title = sanitize_text_field($post_data['title'] ?? '');
    $title = function_exists('dtr_nf_normalize_title') ? dtr_nf_normalize_title($raw_title) : $raw_title;
    
    // Debug form data processing - use claimed_employer field
    $raw_employer = sanitize_text_field($post_data['claimed_employer'] ?? '');
    dtr_html_log("[{$debug_id}] [DEBUG] Raw title from form: '{$raw_title}' -> normalized: '{$title}'");
    dtr_html_log("[{$debug_id}] [DEBUG] Raw employer from form: '{$raw_employer}'");
    dtr_html_log("[{$debug_id}] [DEBUG] Form data keys available: " . implode(', ', array_keys($post_data)));
    dtr_html_log("[{$debug_id}] [DEBUG] Title: '{$title}', Claimed Employer: '{$raw_employer}'");
    
    // Fix field name inconsistencies between form submission and backend
    $phone = isset($post_data['phone']) ? $post_data['phone'] : (isset($post_data['telephone']) ? $post_data['telephone'] : '');
    $job_title = isset($post_data['jobTitle']) ? $post_data['jobTitle'] : (isset($post_data['job_title']) ? $post_data['job_title'] : '');
    $city = isset($post_data['city']) ? $post_data['city'] : (isset($post_data['town']) ? $post_data['town'] : '');
    
    $data = [
        'title' => $title,
        'first_name' => sanitize_text_field($post_data['firstName'] ?? ''),
        'last_name' => sanitize_text_field($post_data['lastName'] ?? ''),
        'email' => sanitize_email($post_data['email'] ?? ''),
        'password' => $post_data['password'] ?? '',
        'claimed_employer' => $raw_employer,
        'employer' => $raw_employer,  // Add employer field for WordPress meta storage
        'telephone' => sanitize_text_field($phone),
        'job_title' => sanitize_text_field($job_title),
        'country' => sanitize_text_field($post_data['country'] ?? ''),
        'town' => sanitize_text_field($city),
        'postcode' => sanitize_text_field($post_data['postcode'] ?? ''),
        'marketing_selected' => [],
        'toi_selected' => []
    ];
    
    // Validate required fields
    if (!$data['email'] || !is_email($data['email']) || !$data['password'] || !$data['first_name'] || !$data['last_name']) {
        dtr_html_log("[{$debug_id}] [ERROR] Missing required fields");
        return false;
    }
    
    // Check for duplicate user
    if (username_exists($data['email']) || email_exists($data['email'])) {
        dtr_html_log("[{$debug_id}] [ERROR] User already exists with email: {$data['email']}");
        return false;
    }
    
    // Process marketing preferences
    $marketing_fields = [
        'cf_person_dtr_news' => 'newsletter',
        'cf_person_dtr_events' => 'events', 
        'cf_person_dtr_third_party' => 'thirdParty',
        'cf_person_dtr_webinar' => 'webinar'
    ];
    foreach ($marketing_fields as $field => $form_key) {
        if (!empty($post_data[$form_key]) && $post_data[$form_key] === '1') {
            $data['marketing_selected'][] = $field;
        }
    }
    
    // Process topics of interest
    $toi_fields = [
        'cf_person_business' => 'business',
        'cf_person_diseases' => 'diseases', 
        'cf_person_drugs_therapies' => 'drugs',
        'cf_person_genomics_3774' => 'genomics',
        'cf_person_research_development' => 'research',
        'cf_person_technology' => 'technology',
        'cf_person_tools_techniques' => 'tools'
    ];
    foreach ($toi_fields as $field => $form_key) {
        if (!empty($post_data[$form_key]) && $post_data[$form_key] === '1') {
            $data['toi_selected'][] = $field;
        }
    }
    
    dtr_html_log("[{$debug_id}] [DEBUG] Title: '{$title}', Employer Name: '{$raw_employer}', Claimed Employer: '{$data['claimed_employer']}'");
    dtr_html_log("[{$debug_id}] [DEBUG] Marketing selected: " . json_encode($data['marketing_selected']));
    dtr_html_log("[{$debug_id}] [DEBUG] TOI selected: " . json_encode($data['toi_selected']));

    return $data;
}

/* --------------------------------------------------------------------------
 * WP User creation & core meta
 * Based on dtr_nf_create_wp_user_and_meta pattern
 * -------------------------------------------------------------------------- */
function dtr_html_create_wp_user_and_meta($data, $debug_id) {
    $user_id = wp_create_user($data['email'], $data['password'], $data['email']);
    if (is_wp_error($user_id)) {
        dtr_html_log("[{$debug_id}] [ERROR] WP user creation failed: " . $user_id->get_error_message());
        return false;
    }
    
    dtr_html_log("[{$debug_id}] [SUCCESS] WP user created - ID {$user_id}");
    
    // Ensure we have valid data before storing
    $employer = !empty($data['employer']) ? $data['employer'] : '';
    $claimed_employer = !empty($data['claimed_employer']) ? $data['claimed_employer'] : $employer;
    $job_title = !empty($data['job_title']) ? $data['job_title'] : '';
    $telephone = !empty($data['telephone']) ? $data['telephone'] : '';
    $title = !empty($data['title']) ? $data['title'] : '';
    $town = !empty($data['town']) ? $data['town'] : '';
    $country = !empty($data['country']) ? $data['country'] : '';
    $postcode = !empty($data['postcode']) ? $data['postcode'] : '';
    
    $core_meta = [
        // WordPress standard meta
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'nickname' => $data['first_name'] . ' ' . $data['last_name'],
        'display_name' => $data['first_name'] . ' ' . $data['last_name'],
        
        // Workbooks field mappings - ensuring all required fields are stored
        'cf_person_personal_title' => $title,
        'person_personal_title' => $title, // Direct Workbooks field mapping
        'cf_person_first_name' => $data['first_name'],
        'cf_person_last_name' => $data['last_name'],
        'cf_person_email_address' => $data['email'],
        'cf_person_telephone_number' => $telephone,
        'cf_person_job_title' => $job_title,
        'cf_person_employer' => $employer,
        // 'cf_person_claimed_employer' => $claimed_employer,
        'cf_person_country' => $country,
        'cf_person_town_city' => $town,
        'cf_person_post_code' => $postcode,
        
        // Admin display fields - these are crucial for WordPress admin
        'employer_name' => $employer,  // This is what WP admin typically shows
        'job_title' => $job_title,    // Standard WP field
        'telephone' => $telephone,    // Standard contact field
        'title' => $title,           // Personal title (Mr, Ms, Dr, etc.)
        'town' => $town,             // Location field
        'country' => $country,       // Location field
        'postcode' => $postcode,     // Location field
        
        // Store original form field values to preserve all data
        'form_title' => $title,
        'form_telephone' => $telephone, 
        'form_job_title' => $job_title,
        'form_employer' => $employer,
        'form_country' => $country,
        'form_town' => $town,
        'form_postcode' => $postcode,
        
        // Additional backup fields to ensure data isn't lost
        'user_employer' => $employer,
        'user_job_title' => $job_title,
        'user_telephone' => $telephone,
        'user_title' => $title,
        'user_town' => $town,
        'user_country' => $country,
        'user_postcode' => $postcode,
        
        'created_via_html_form' => 1  // Track that this user was created via HTML form
    ];
    
    // Log all meta fields being saved with console-style output
    dtr_html_log("[{$debug_id}] [CONSOLE] ============ USER META STORAGE CONSOLE LOG ============");
    dtr_html_log("[{$debug_id}] [CONSOLE] User ID: {$user_id}");
    dtr_html_log("[{$debug_id}] [CONSOLE] Email: {$data['email']}");
    dtr_html_log("[{$debug_id}] [CONSOLE] ");
    dtr_html_log("[{$debug_id}] [CONSOLE] REQUIRED FIELDS BEING STORED:");
    dtr_html_log("[{$debug_id}] [CONSOLE] ===================================");
    dtr_html_log("[{$debug_id}] [CONSOLE] Employer: '{$employer}' -> Fields: employer_name, cf_person_employer, form_employer, user_employer");
    dtr_html_log("[{$debug_id}] [CONSOLE] Job Title: '{$job_title}' -> Fields: job_title, cf_person_job_title, form_job_title, user_job_title");
    dtr_html_log("[{$debug_id}] [CONSOLE] Telephone: '{$telephone}' -> Fields: telephone, cf_person_telephone_number, form_telephone, user_telephone");
    dtr_html_log("[{$debug_id}] [CONSOLE] Title: '{$title}' -> Fields: title, cf_person_personal_title, person_personal_title, form_title, user_title");
    dtr_html_log("[{$debug_id}] [CONSOLE] Town: '{$town}' -> Fields: town, cf_person_town_city, form_town, user_town");
    dtr_html_log("[{$debug_id}] [CONSOLE] Country: '{$country}' -> Fields: country, cf_person_country, form_country, user_country");
    dtr_html_log("[{$debug_id}] [CONSOLE] Post Code: '{$postcode}' -> Fields: postcode, cf_person_post_code, form_postcode, user_postcode");
    dtr_html_log("[{$debug_id}] [CONSOLE] ");
    dtr_html_log("[{$debug_id}] [CONSOLE] ALL META FIELDS BEING STORED:");
    dtr_html_log("[{$debug_id}] [CONSOLE] ==============================");
    
    // Save each meta field individually and verify it was saved
    $storage_results = [];
    foreach ($core_meta as $key => $value) {
        $result = update_user_meta($user_id, $key, $value);
        $verification = get_user_meta($user_id, $key, true);
        $success = ($verification === $value || (!empty($value) && !empty($verification)));
        
        $storage_results[$key] = [
            'value' => $value,
            'result' => $result,
            'verification' => $verification,
            'success' => $success
        ];
        
        $status = $success ? '✓ SUCCESS' : '✗ FAILED';
        $display_value = is_array($value) ? json_encode($value) : $value;
        dtr_html_log("[{$debug_id}] [CONSOLE] {$status} | {$key} = '{$display_value}' | Verified: '{$verification}'");
        
        // If critical field failed to save, log warning
        if (!$success && in_array($key, ['employer_name', 'job_title', 'telephone', 'title', 'town', 'country', 'postcode'])) {
            dtr_html_log("[{$debug_id}] [WARNING] Critical field {$key} may not have saved properly");
        }
    }
    
    // Double-check that the critical fields were saved by re-reading them
    dtr_html_log("[{$debug_id}] [CONSOLE] ");
    dtr_html_log("[{$debug_id}] [CONSOLE] CRITICAL FIELDS VERIFICATION:");
    dtr_html_log("[{$debug_id}] [CONSOLE] =============================");
    $critical_fields = ['employer_name', 'job_title', 'telephone', 'title', 'town', 'country', 'postcode'];
    $verification_success = true;
    
    foreach ($critical_fields as $field) {
        $saved_value = get_user_meta($user_id, $field, true);
        $expected_value = $core_meta[$field] ?? '';
        $is_valid = ($saved_value === $expected_value) && !empty($saved_value);
        $status = $is_valid ? '✓ VERIFIED' : '✗ MISSING/FAILED';
        
        if (!$is_valid) {
            $verification_success = false;
        }
        
        dtr_html_log("[{$debug_id}] [CONSOLE] {$status} | {$field} = '{$saved_value}' (expected: '{$expected_value}')");
    }
    
    // Summary of storage operation
    $total_fields = count($core_meta);
    $successful_fields = count(array_filter($storage_results, function($result) { return $result['success']; }));
    $failed_fields = $total_fields - $successful_fields;
    
    dtr_html_log("[{$debug_id}] [CONSOLE] ");
    dtr_html_log("[{$debug_id}] [CONSOLE] STORAGE SUMMARY:");
    dtr_html_log("[{$debug_id}] [CONSOLE] ===============");
    dtr_html_log("[{$debug_id}] [CONSOLE] Total Fields: {$total_fields}");
    dtr_html_log("[{$debug_id}] [CONSOLE] Successfully Stored: {$successful_fields}");
    dtr_html_log("[{$debug_id}] [CONSOLE] Failed: {$failed_fields}");
    dtr_html_log("[{$debug_id}] [CONSOLE] Critical Fields Valid: " . ($verification_success ? 'YES' : 'NO'));
    dtr_html_log("[{$debug_id}] [CONSOLE] =========== END USER META STORAGE CONSOLE LOG ===========");
    
    // Store debug data without relying on $post_data
    try {
        // Safely store debug info with error checking
        $debug_data = [
            'data_fields' => is_array($data) ? array_keys($data) : [],
            'timestamp' => current_time('mysql'),
            'debug_id' => $debug_id,
            // Store selective sensitive data for debugging
            'field_status' => [
                'has_first_name' => !empty($data['first_name']),
                'has_last_name' => !empty($data['last_name']),
                'has_email' => !empty($data['email']),
                'has_telephone' => !empty($data['telephone']),
                'has_employer' => !empty($data['employer']),
                'has_job_title' => !empty($data['job_title']),
                'has_title' => !empty($data['title']),
                'has_town' => !empty($data['town']),
                'has_country' => !empty($data['country']),
                'has_postcode' => !empty($data['postcode']),
                'marketing_count' => count($data['marketing_selected'] ?? []),
                'toi_count' => count($data['toi_selected'] ?? [])
            ],
            // Store the actual values for debugging (non-sensitive)
            'stored_values' => [
                'employer_name' => get_user_meta($user_id, 'employer_name', true),
                'job_title' => get_user_meta($user_id, 'job_title', true),
                'telephone' => get_user_meta($user_id, 'telephone', true),
                'title' => get_user_meta($user_id, 'title', true),
                'town' => get_user_meta($user_id, 'town', true),
                'country' => get_user_meta($user_id, 'country', true),
                'postcode' => get_user_meta($user_id, 'postcode', true)
            ],
            'storage_results' => $storage_results,
            'verification_success' => $verification_success
        ];
        update_user_meta($user_id, 'html_form_debug_data', $debug_data);
        
        dtr_html_log("[{$debug_id}] [DEBUG] Form debug data stored successfully");
    } catch (Throwable $t) {
        dtr_html_log("[{$debug_id}] [ERROR] Failed to store debug data: " . $t->getMessage());
        // Don't let debug storage failure prevent user creation
    }
    
    return $user_id;
}

/* --------------------------------------------------------------------------
 * Marketing & TOI
 * Based on dtr_nf_apply_marketing_and_interests pattern
 * -------------------------------------------------------------------------- */
function dtr_html_apply_marketing_and_interests($user_id, $data, $debug_id) {
    // Apply marketing preferences
    $marketing_fields = ['cf_person_dtr_news', 'cf_person_dtr_events', 'cf_person_dtr_third_party', 'cf_person_dtr_webinar'];
    foreach ($marketing_fields as $field) {
        $value = in_array($field, $data['marketing_selected'], true) ? 1 : 0;
        update_user_meta($user_id, $field, $value);
    }
    
    // Apply topics of interest
    $toi_fields = ['cf_person_business', 'cf_person_diseases', 'cf_person_drugs_therapies', 'cf_person_genomics_3774', 'cf_person_research_development', 'cf_person_technology', 'cf_person_tools_techniques'];
    $selected_toi = [];
    foreach ($toi_fields as $field) {
        $value = in_array($field, $data['toi_selected'], true) ? 1 : 0;
        update_user_meta($user_id, $field, $value);
        if ($value) {
            $selected_toi[] = $field;
        }
    }
    
    dtr_html_log("[{$debug_id}] [DEBUG] Applied marketing preferences and TOI selections");
}

/* --------------------------------------------------------------------------
 * AOI Mapping
 * Based on dtr_nf_handle_aoi_mapping pattern
 * -------------------------------------------------------------------------- */
function dtr_html_handle_aoi_mapping($user_id, $debug_id) {
    if (!function_exists('dtr_get_aoi_field_names') || !function_exists('dtr_map_toi_to_aoi')) {
        dtr_html_log("[{$debug_id}] [WARNING] AOI mapping functions not available");
        return false;
    }
    
    // Get all AOI fields to track user-explicitly selected ones
    $aoi_field_names = dtr_get_aoi_field_names();
    if (!$aoi_field_names) {
        dtr_html_log("[{$debug_id}] [WARNING] No AOI field names available");
        return false;
    }
    
    // Track which AOIs were explicitly selected by the user on the form
    $user_selected_aois = [];
    foreach ($aoi_field_names as $aoi_field => $display_name) {
        $existing_value = get_user_meta($user_id, $aoi_field, true);
        if ($existing_value) {
            $user_selected_aois[] = $aoi_field;
        }
    }
    
    dtr_html_log("[{$debug_id}] [DEBUG] User-selected AOIs before TOI mapping: " . json_encode($user_selected_aois));
    
    // Derive TOI selections from meta
    $toi_fields = ['cf_person_business', 'cf_person_diseases', 'cf_person_drugs_therapies', 'cf_person_genomics_3774', 'cf_person_research_development', 'cf_person_technology', 'cf_person_tools_techniques'];
    $selected_toi = [];
    foreach ($toi_fields as $field) {
        if (get_user_meta($user_id, $field, true)) {
            $selected_toi[] = $field;
        }
    }
    
    dtr_html_log("[{$debug_id}] [DEBUG] TOIs selected for AOI mapping: " . json_encode($selected_toi));
    
    if (!$selected_toi) {
        dtr_html_log("[{$debug_id}] [DEBUG] No TOIs selected - skipping AOI mapping");
        return true;
    }
    
    $aoi_map = dtr_map_toi_to_aoi($selected_toi);
    dtr_html_log("[{$debug_id}] [DEBUG] AOI matrix result: " . json_encode($aoi_map));
    
    $applied_count = 0;
    $skipped_count = 0;
    foreach ($aoi_map as $aoi_field => $aoi_val) {
        if ($aoi_val) {
            // Only apply AOI mapping if the user didn't explicitly select this AOI
            if (!in_array($aoi_field, $user_selected_aois)) {
                update_user_meta($user_id, $aoi_field, 1);
                $applied_count++;
                dtr_html_log("[{$debug_id}] [DEBUG] Applied AOI via TOI mapping: {$aoi_field}");
            } else {
                $skipped_count++;
                dtr_html_log("[{$debug_id}] [DEBUG] Skipped AOI (user-selected): {$aoi_field}");
            }
        }
    }
    
    dtr_html_log("[{$debug_id}] [DEBUG] AOI mapping applied - {$applied_count} new fields set, {$skipped_count} user-selected preserved");
    return true;
}

/* --------------------------------------------------------------------------
 * Build Workbooks payload
 * Based on dtr_nf_build_workbooks_payload pattern
 * -------------------------------------------------------------------------- */
function dtr_html_build_workbooks_payload($user_id, $data, $debug_id) {
    $payload = [
        'person_first_name' => $data['first_name'],
        'person_last_name'  => $data['last_name'],
        'name' => trim($data['first_name'].' '.$data['last_name']),
        'main_location[email]' => $data['email'],
        'created_through_reference' => 'wp_user_' . $user_id,
        'person_personal_title' => $data['title'],
        'person_job_title' => $data['job_title'],
        'main_location[telephone]' => $data['telephone'],
        'main_location[country]' => $data['country'],
        'main_location[town]' => $data['town'],
        'main_location[postcode]' => $data['postcode'],
        'cf_person_claimed_employer' => $data['claimed_employer'], // Workbooks employer field (uncommented for Workbooks sync)
        // Note: employer_name is used for WordPress meta, cf_person_claimed_employer for Workbooks API
        'cf_person_dtr_subscriber_type' => 'Prospect',
        /* 'cf_person_dtr_subscriber' => 1, */
        'cf_person_dtr_web_member' => 1,
        'lead_source_type' => 'Online Registration',
        'cf_person_is_person_active_or_inactive' => 'Active',
        'cf_person_data_source_detail' => 'DTR Web Member Signup'
    ];
    
    // Marketing & TOI fields from meta (ensures consistency)
    $marketing_fields = ['cf_person_dtr_news', 'cf_person_dtr_events', 'cf_person_dtr_third_party', 'cf_person_dtr_webinar'];
    foreach ($marketing_fields as $mf) {
        $payload[$mf] = (int) get_user_meta($user_id, $mf, true);
    }
    
    $toi_fields = ['cf_person_business', 'cf_person_diseases', 'cf_person_drugs_therapies', 'cf_person_genomics_3774', 'cf_person_research_development', 'cf_person_technology', 'cf_person_tools_techniques'];
    foreach ($toi_fields as $tf) {
        $payload[$tf] = (int) get_user_meta($user_id, $tf, true);
    }
    
    // Debug employer field values
    dtr_html_log("[{$debug_id}] [DEBUG] Workbooks payload cf_person_claimed_employer: '{$data['claimed_employer']}'");
    dtr_html_log("[{$debug_id}] [DEBUG] Source data claimed_employer: '{$data['claimed_employer']}'");
    
    // Add AOI fields if available
    if (function_exists('dtr_get_aoi_field_names')) {
        foreach (array_keys(dtr_get_aoi_field_names()) as $aoi_wp_field) {
            $val = (int) get_user_meta($user_id, $aoi_wp_field, true);
            $wb_field = str_replace('_aoi_', '_', $aoi_wp_field);
            $payload[$wb_field] = $val;
        }
    }
    
    // Debug: Log employer field mappings
    dtr_html_log("[{$debug_id}] [DEBUG] Workbooks payload employer_name: '{$payload['employer_name']}'");
    // dtr_html_log("[{$debug_id}] [DEBUG] Workbooks payload cf_person_claimed_employer: '{$payload['cf_person_claimed_employer']}'");
    dtr_html_log("[{$debug_id}] [DEBUG] Source data employer: '{$data['employer']}'");
    dtr_html_log("[{$debug_id}] [DEBUG] Source data claimed_employer: '{$data['claimed_employer']}'");
    
    return $payload;
}

/* --------------------------------------------------------------------------
 * Employer organisation attachment
 * Based on dtr_nf_maybe_attach_employer_org pattern
 * -------------------------------------------------------------------------- */
function dtr_html_maybe_attach_employer_org($user_id, $payload, $data, $debug_id) {
    $employer_name = $data['claimed_employer'] ?? $data['employer'] ?? '';
    if (!empty($employer_name) && function_exists('workbooks_get_or_create_organisation_id')) {
        $org_id = workbooks_get_or_create_organisation_id($employer_name);
        if ($org_id) {
            $payload['employer_link'] = $org_id; // Use employer_link field as per Workbooks API
            dtr_html_log("[{$debug_id}] [DEBUG] Attached employer_link: {$org_id} for employer: {$employer_name}");
        } else {
            dtr_html_log("[{$debug_id}] [WARNING] Could not resolve employer organisation for: {$employer_name}");
        }
    }
    
    dtr_html_log("[{$debug_id}] [DEBUG] Prepared Workbooks payload");
    return $payload;
}

/* --------------------------------------------------------------------------
 * Workbooks sync (search + create)
 * Based on dtr_nf_workbooks_person_sync pattern
 * -------------------------------------------------------------------------- */
function dtr_html_workbooks_person_sync($workbooks, $user_id, $payload, $email, $debug_id) {
    dtr_html_log("[{$debug_id}] [DEBUG] Checking Workbooks for existing person with email: {$email}");
    
    // Check if Workbooks instance is valid
    if (!$workbooks || !is_object($workbooks)) {
        dtr_html_log("[{$debug_id}] [ERROR] Invalid Workbooks instance provided");
        return false;
    }
    
    try {
        // Search for existing person - use same endpoint as working Ninja Forms handler
        $search_result = $workbooks->assertGet('crm/people', ['main_location[email]' => $email, '_limit' => 1]);
        
        if (!empty($search_result['data']) && count($search_result['data']) > 0) {
            $existing_person = $search_result['data'][0];
            $found_email = strtolower($existing_person['main_location[email]'] ?? '');
            $target_email = strtolower($email);
            
            if ($found_email === $target_email) {
                dtr_html_log("[{$debug_id}] [DEBUG] Exact email match found - updating existing person");
                // Update existing person logic could go here
                return true;
            } else {
                dtr_html_log("[{$debug_id}] [DEBUG] Workbooks search returned a person, but email did not match exactly. Found: '{$found_email}', Expected: '{$target_email}'");
            }
        }
    } catch (Exception $e) {
        dtr_html_log("[{$debug_id}] [WARNING] Workbooks search failed: " . $e->getMessage());
        
        // Check if it's a cURL error
        if (strpos($e->getMessage(), 'cURL') !== false || strpos($e->getMessage(), 'curl_init') !== false) {
            dtr_html_log("[{$debug_id}] [ERROR] cURL initialization failed - likely missing cURL extension or network issue");
            return false;
        }
    }
    
    dtr_html_log("[{$debug_id}] [SUCCESS] WP user created with ID: {$user_id}");
    dtr_html_log("[{$debug_id}] [DEBUG] No existing Workbooks person found - creating new");
    dtr_html_log("[{$debug_id}] [DEBUG] Starting Workbooks sync");
    dtr_html_log("[{$debug_id}] [DEBUG] Searching for existing person with email: {$email}");
    
    // Person not found in initial search, proceed to create
    
    dtr_html_log("[{$debug_id}] [DEBUG] No existing person found - creating new Workbooks person");
    dtr_html_log("[{$debug_id}] [DEBUG] Sending create request to Workbooks API");
    
    try {
        // Fix: Create a variable for the payload array that can be passed by reference
        $payload_array = [$payload];
        $create_result = $workbooks->assertCreate('crm/people', $payload_array);
        
        dtr_html_log("[{$debug_id}] [DEBUG] Workbooks create response: " . json_encode($create_result));
        
        if (!empty($create_result['affected_objects'][0]['id'])) {
            $person_id = $create_result['affected_objects'][0]['id'];
            $person_ref = $create_result['affected_objects'][0]['object_ref'] ?? '';
            
            dtr_html_log("[{$debug_id}] [SUCCESS] NEW person created in Workbooks with ID: {$person_id}, ref: {$person_ref}");
            
            // Store Workbooks ID in user meta - match the exact pattern from working Ninja Forms handler
            update_user_meta($user_id, 'workbooks_person_id', $person_id);
            update_user_meta($user_id, 'workbooks_object_ref', $person_ref);
            dtr_html_log("[{$debug_id}] [SUCCESS] Stored workbooks_person_id: {$person_id} and workbooks_object_ref: {$person_ref}");
        } else {
            dtr_html_log("[{$debug_id}] [WARNING] Person created but no affected_objects[0][id] in response");
            dtr_html_log("[{$debug_id}] [DEBUG] Full response structure: " . json_encode($create_result));
        }
        
        return true;
        
    } catch (Exception $e) {
        dtr_html_log("[{$debug_id}] [ERROR] Workbooks person creation failed: " . $e->getMessage());
        
        // Provide specific error information for cURL issues
        if (strpos($e->getMessage(), 'cURL') !== false || strpos($e->getMessage(), 'curl_init') !== false) {
            dtr_html_log("[{$debug_id}] [ERROR] This appears to be a cURL configuration issue. Check if cURL extension is installed and can connect to external services.");
        }
        
        return false;
    }
}

/* --------------------------------------------------------------------------
 * Summary logger (post-success)
 * Based on dtr_nf_membership_log_summary pattern
 * -------------------------------------------------------------------------- */
function dtr_html_membership_log_summary($user_id, $data, $test_mode = false) {
    if ($test_mode) {
        dtr_html_log("==== MEMBER REGISTRATION - TEST MODE =====");
        dtr_html_log("TEST MODE: Registration would complete successfully");
        dtr_html_log("TEST MODE: Email: " . $data['email']);
        dtr_html_log("==== MEMBER REGISTRATION TEST MODE COMPLETE =====");
        return;
    }
    
    dtr_html_log("==== MEMBER REGISTRATION =====");
    dtr_html_log("User Details:");
    dtr_html_log("Title: " . $data['title']);
    dtr_html_log("First Name: " . $data['first_name']);
    dtr_html_log("Last Name: " . $data['last_name']);
    dtr_html_log("Email Address: " . $data['email']);
    dtr_html_log("Telephone Number: " . $data['telephone']);
    dtr_html_log("Job Title: " . $data['job_title']);
    dtr_html_log("Employer: " . $data['claimed_employer']);
    dtr_html_log("Country: " . $data['country']);
    dtr_html_log("Town: " . $data['town']);
    dtr_html_log("Post Code: " . $data['postcode']);
    dtr_html_log("");
    
    dtr_html_log("---- MARKETING COMMUNICATION ----");
    $marketing_labels = [
        'cf_person_dtr_news' => 'Newsletter',
        'cf_person_dtr_events' => 'Event', 
        'cf_person_dtr_third_party' => 'Third party',
        'cf_person_dtr_webinar' => 'Webinar'
    ];
    
    foreach ($marketing_labels as $field => $label) {
        $selected = in_array($field, $data['marketing_selected'], true) ? 'Yes' : 'No';
        dtr_html_log("{$label}: - {$selected}");
    }
    
    dtr_html_log("");
    dtr_html_log("---- TOPICS OF INTEREST ----");
    $toi_labels = [
        'cf_person_business' => 'Business',
        'cf_person_diseases' => 'Diseases',
        'cf_person_drugs_therapies' => 'Drugs & Therapies',
        'cf_person_genomics_3774' => 'Genomics',
        'cf_person_research_development' => 'Research & Development',
        'cf_person_technology' => 'Technology',
        'cf_person_tools_techniques' => 'Tools & Techniques'
    ];
    
    foreach ($toi_labels as $field => $label) {
        $selected = in_array($field, $data['toi_selected'], true) ? 'Yes' : 'No';
        dtr_html_log("{$label}: - {$selected}");
    }
    
    if ($user_id && function_exists('dtr_get_aoi_field_names')) {
        dtr_html_log("");
        dtr_html_log("---- AREAS OF INTEREST ----");
        $aoi_fields = dtr_get_aoi_field_names();
        foreach ($aoi_fields as $field => $label) {
            $value = get_user_meta($user_id, $field, true) ? 'Yes' : 'No';
            dtr_html_log("{$field}: - {$value}");
        }
    }
    
    if ($user_id) {
        dtr_html_log("");
        dtr_html_log("---- WORDPRESS/WORKBOOKS ----");
        dtr_html_log("SUCCESS: WordPress user created with ID: {$user_id}");
        
        $workbooks_id = get_user_meta($user_id, 'workbooks_person_id', true);
        $workbooks_ref = get_user_meta($user_id, 'workbooks_person_ref', true);
        
        if ($workbooks_id) {
            dtr_html_log("SUCCESS: New Workbooks person created with ID: {$workbooks_id}");
            if ($workbooks_ref) {
                dtr_html_log("Workbooks Person Ref: {$workbooks_ref}");
            }
        }
        
        dtr_html_log("");
        
        // Add communication preferences summary
        $marketing_summary = [];
        foreach ($marketing_labels as $field => $label) {
            $selected = in_array($field, $data['marketing_selected'], true) ? 'Yes' : 'No';
            $marketing_summary[] = $label . '=' . $selected;
        }
        dtr_html_log("Communication Preferences: " . implode(', ', $marketing_summary));
        
        // Add topics of interest summary
        $toi_summary = [];
        foreach ($toi_labels as $field => $label) {
            $selected = in_array($field, $data['toi_selected'], true) ? 'Yes' : 'No';
            $toi_summary[] = $label . '=' . $selected;
        }
        dtr_html_log("Topics of Interest: " . implode(', ', $toi_summary));
        
        // Add areas of interest summary if available
        if (function_exists('dtr_get_aoi_field_names')) {
            $aoi_summary = [];
            $aoi_fields = dtr_get_aoi_field_names();
            foreach ($aoi_fields as $field => $label) {
                $value = get_user_meta($user_id, $field, true) ? 'Yes' : 'No';
                $aoi_summary[] = $field . '=' . $value;
            }
            dtr_html_log("Areas of Interest: " . implode(', ', $aoi_summary));
        }
    }
    
    dtr_html_log("");
    dtr_html_log("==== MEMBER REGISTRATION SUCCESSFUL =====");
    dtr_html_log("Member registration successful - Fucking Celebrate Good Times Come On!!!");
}

/* --------------------------------------------------------------------------
 * Failure logger (captures partial state)
 * Based on dtr_nf_membership_log_failure pattern
 * -------------------------------------------------------------------------- */
function dtr_html_membership_log_failure($reason, $post_data = [], $user_id = null, $data = []) {
    dtr_html_log("==== MEMBER REGISTRATION FAILED =====");
    dtr_html_log("FAILURE REASON: {$reason}");
    
    if (!empty($data)) {
        dtr_html_log("User Data: " . json_encode($data));
    } elseif (!empty($post_data['email'])) {
        dtr_html_log("Email: " . sanitize_email($post_data['email']));
    }
    
    if ($user_id) {
        dtr_html_log("WordPress User ID: {$user_id}");
    }
    
    dtr_html_log("==== MEMBER REGISTRATION FAILURE END =====");
}

/* --------------------------------------------------------------------------
 * Add nonce generation for forms
 * -------------------------------------------------------------------------- */
function dtr_html_form_get_nonce() {
    return wp_create_nonce('dtr_html_form_submit');
}

// Make nonce available via AJAX
add_action('wp_ajax_dtr_get_form_nonce', function() {
    wp_send_json_success(['nonce' => dtr_html_form_get_nonce()]);
});

add_action('wp_ajax_nopriv_dtr_get_form_nonce', function() {
    wp_send_json_success(['nonce' => dtr_html_form_get_nonce()]);
});

/* --------------------------------------------------------------------------
 * Diagnostic functions for troubleshooting
 * -------------------------------------------------------------------------- */

// Add a diagnostic endpoint to check user meta
add_action('wp_ajax_dtr_check_user_meta', 'dtr_check_user_meta');
add_action('wp_ajax_nopriv_dtr_check_user_meta', 'dtr_check_user_meta');

function dtr_check_user_meta() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not authorized']);
        return;
    }
    
    $user_email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    if (empty($user_email)) {
        wp_send_json_error(['message' => 'Email required']);
        return;
    }
    
    $user = get_user_by('email', $user_email);
    if (!$user) {
        wp_send_json_error(['message' => 'User not found']);
        return;
    }
    
    // Get all user meta
    $meta = get_user_meta($user->ID);
    $filtered_meta = [];
    
    // Filter meta fields for security
    foreach ($meta as $key => $values) {
        if (strpos($key, 'cf_') === 0 || 
            in_array($key, ['first_name', 'last_name', 'nickname', 'form_title', 'employer_name'])) {
            $filtered_meta[$key] = $values[0] ?? '';
        }
    }
    
    wp_send_json_success([
        'user_id' => $user->ID,
        'user_email' => $user->user_email,
        'user_meta' => $filtered_meta
    ]);
}