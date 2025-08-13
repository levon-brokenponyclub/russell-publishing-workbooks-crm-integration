<?php
if (!defined('ABSPATH')) exit;

// Ensure helper functions are available
if (file_exists(__DIR__ . '/helper-functions.php')) {
    require_once __DIR__ . '/helper-functions.php';
}

if (!function_exists('nf_debug_log')) {
    function nf_debug_log($message) {
        $log_file = WORKBOOKS_NF_PATH . 'logs/register-debug.log';
        
        // Ensure the logs directory exists
        $logs_dir = dirname($log_file);
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Re-enable temporarily while fixing the main handler
add_action('ninja_forms_after_submission', 'nf_create_wp_user_and_sync_workbooks', 10, 1);

function nf_create_wp_user_and_sync_workbooks($form_data) {
    nf_debug_log('=== NF DEBUG: Submission triggered ===');

    $target_form_id = 15;
    if (!isset($form_data['form_id'])) {
        nf_debug_log('NF DEBUG: No form_id in submission.');
        return;
    }
    if ($form_data['form_id'] != $target_form_id) {
        nf_debug_log('NF DEBUG: Skipping - form ID does not match target.');
        return;
    }

    // Extract fields by key and ID
    nf_debug_log('NF DEBUG: Submitted Fields = ' . print_r($form_data['fields'], true));
    $fields = [];
    $fields_by_id = [];
    foreach ($form_data['fields'] as $field) {
        $fields[$field['key']] = $field['value'];
        $fields_by_id[$field['id']] = $field['value'];
    }
    
    nf_debug_log('NF DEBUG: Fields by key = ' . print_r($fields, true));
    nf_debug_log('NF DEBUG: Fields by ID = ' . print_r($fields_by_id, true));

    // Map field IDs from the actual form (based on debug log analysis)
    $email = sanitize_email($fields['email_address'] ?? $fields_by_id['144'] ?? '');
    $password = $fields['password'] ?? $fields_by_id['221'] ?? '';
    $first_name = sanitize_text_field($fields['first_name'] ?? $fields_by_id['142'] ?? '');
    $last_name = sanitize_text_field($fields['last_name'] ?? $fields_by_id['143'] ?? '');
    $employer = sanitize_text_field($fields['employer'] ?? $fields['employer_name'] ?? $fields_by_id['218'] ?? '');
    $title = sanitize_text_field($fields['title'] ?? $fields_by_id['141'] ?? '');
    $telephone = sanitize_text_field($fields['telephone'] ?? $fields_by_id['146'] ?? '');
    $country = sanitize_text_field($fields['country'] ?? $fields_by_id['148'] ?? 'South Africa');
    $town = sanitize_text_field($fields['town'] ?? $fields_by_id['149'] ?? '');
    $postcode = sanitize_text_field($fields['postcode'] ?? $fields_by_id['150'] ?? '');
    $job_title = sanitize_text_field($fields['job_title'] ?? $fields_by_id['147'] ?? '');

    nf_debug_log("NF DEBUG: Email=$email, Password=" . (empty($password) ? 'EMPTY' : 'SET') . ", First Name=$first_name, Last Name=$last_name, Employer=$employer, Title=$title");
    
    // Additional debug logging for employer field
    nf_debug_log("NF DEBUG: Employer field extraction debug:");
    nf_debug_log("NF DEBUG: - fields['employer'] = " . ($fields['employer'] ?? 'NOT SET'));
    nf_debug_log("NF DEBUG: - fields['employer_name'] = " . ($fields['employer_name'] ?? 'NOT SET'));
    nf_debug_log("NF DEBUG: - fields_by_id['218'] = " . ($fields_by_id['218'] ?? 'NOT SET'));
    nf_debug_log("NF DEBUG: - Final employer value = '$employer'");

    if (empty($email) || !is_email($email) || empty($password) || empty($first_name) || empty($last_name)) {
        nf_debug_log('NF ERROR: Missing required fields.');
        return;
    }

    if (username_exists($email)) {
        nf_debug_log("NF ERROR: Username already exists - $email");
        return;
    }

    if (email_exists($email)) {
        nf_debug_log("NF ERROR: Email already exists - $email");
        return;
    }

    // Create WP user
    $user_id = wp_create_user($email, $password, $email);
    if (is_wp_error($user_id)) {
        nf_debug_log('NF ERROR: WP user creation failed - ' . $user_id->get_error_message());
        return;
    }
    nf_debug_log("NF DEBUG: WP User created - ID $user_id");

    // Save basic user meta
    $meta_fields = [
        'created_via_ninja_form' => 1,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'employer' => $employer,
        'employer_name' => $employer, // Keep this for WP meta
        'cf_person_claimed_employer' => $employer, // Add claimed employer to WP meta
        'person_personal_title' => $title,
        'telephone' => $telephone,
        'country' => $country,
        'town' => $town,
        'postcode' => $postcode,
        'job_title' => $job_title,
        'cf_person_dtr_subscriber_type' => 'Prospect',
        'cf_person_dtr_web_member' => 1,
        'lead_source_type' => 'Online Registration',
        'cf_person_is_person_active_or_inactive' => 'Active',
        'cf_person_data_source_detail' => 'DTR Member Registration'
    ];
    
    foreach ($meta_fields as $key => $value) {
        update_user_meta($user_id, $key, $value);
    }
    
    nf_debug_log("NF DEBUG: Basic meta saved for user ID $user_id");

    // Handle marketing preferences (listcheckbox)
    $marketing_fields = [
        'cf_person_dtr_news' => 'cf_person_dtr_news',
        'cf_person_dtr_events' => 'cf_person_dtr_events', 
        'cf_person_dtr_third_party' => 'cf_person_dtr_third_party',
        'cf_person_dtr_webinar' => 'cf_person_dtr_webinar',
    ];
    
    // Get marketing preferences from form data
    $selected_marketing = $fields['marketing_preferences'] ?? [];
    if (!is_array($selected_marketing)) {
        $selected_marketing = [];
    }
    
    nf_debug_log('NF DEBUG: Selected marketing: ' . print_r($selected_marketing, true));
    
    foreach ($marketing_fields as $meta_key => $field_value) {
        $is_selected = in_array($field_value, $selected_marketing) ? 1 : 0;
        update_user_meta($user_id, $meta_key, $is_selected);
        nf_debug_log("NF DEBUG: Marketing preference $meta_key = $is_selected");
    }

    // Handle topics of interest (listcheckbox)
    $interests_fields = [
        'cf_person_business' => 'cf_person_business',
        'cf_person_diseases' => 'cf_person_diseases',
        'cf_person_drugs_therapies' => 'cf_person_drugs_therapies',
        'cf_person_genomics_3774' => 'cf_person_genomics_3774',
        'cf_person_research_development' => 'cf_person_research_development',
        'cf_person_technology' => 'cf_person_technology',
        'cf_person_tools_techniques' => 'cf_person_tools_techniques',
    ];
    
    // Get topics of interest from form data
    $selected_interests = $fields['topics_of_interest'] ?? [];
    if (!is_array($selected_interests)) {
        $selected_interests = [];
    }
    
    nf_debug_log('NF DEBUG: Selected interests: ' . print_r($selected_interests, true));
    
    $selected_toi_fields = [];
    foreach ($interests_fields as $meta_key => $field_value) {
        $is_selected = in_array($field_value, $selected_interests) ? 1 : 0;
        update_user_meta($user_id, $meta_key, $is_selected);
        if ($is_selected) $selected_toi_fields[] = $meta_key;
        nf_debug_log("NF DEBUG: Interest $meta_key = $is_selected");
    }
    // Map TOI to AOI and save AOI fields
    if (function_exists('dtr_map_toi_to_aoi') && !empty($selected_toi_fields)) {
        $aoi_mapping = dtr_map_toi_to_aoi($selected_toi_fields);
        foreach ($aoi_mapping as $aoi_field => $aoi_value) {
            update_user_meta($user_id, $aoi_field, $aoi_value);
        }
        nf_debug_log('NF DEBUG: TOI to AOI mapping applied: ' . print_r($aoi_mapping, true));
    }

    // === Workbooks API Integration ===
    if (!function_exists('get_workbooks_instance')) {
        nf_debug_log('NF ERROR: get_workbooks_instance() function not found');
        return;
    }

    $workbooks = get_workbooks_instance();
    if (!$workbooks) {
        nf_debug_log('NF ERROR: Workbooks API instance is null');
        return;
    }

    nf_debug_log('NF DEBUG: Workbooks API instance acquired');

    // Prepare Workbooks payload with correct field names
    $payload = [
        'person_first_name' => $first_name,
        'person_last_name' => $last_name,
        'name' => trim("$first_name $last_name"),
        'main_location[email]' => $email,
        'created_through_reference' => 'wp_user_' . $user_id,
        'person_personal_title' => $title, // Use correct field name
        'person_job_title' => $job_title,
        'main_location[telephone]' => $telephone,
        'main_location[country]' => $country,
        'main_location[town]' => $town,
        'main_location[postcode]' => $postcode,
        'employer_name' => $employer, // Use editable employer_name field
        'cf_person_claimed_employer' => $employer, // Also set claimed employer field
        'cf_person_dtr_subscriber_type' => 'Prospect',
        'cf_person_dtr_subscriber' => 1,
        'cf_person_dtr_web_member' => 1,
        'lead_source_type' => 'Online Registration',
        'cf_person_is_person_active_or_inactive' => 'Active',
        'cf_person_data_source_detail' => 'DTR Member Registration',
        // Explicitly set all nurture track fields to 0 (disabled)
        'cf_person_dtr_nurture_track' => 0,
        'cf_person_nf_nurture_track' => 0,
        'cf_person_epr_nurture_track' => 0,
        'cf_person_grr_nurture_track' => 0,
        'cf_person_iar_nurture_track' => 0,
        'cf_person_it_nurture_track' => 0,
        'cf_person_nurture_track' => 0
    ];
    
    // Add marketing preferences to payload
    foreach ($marketing_fields as $field => $nf_value) {
        $payload[$field] = get_user_meta($user_id, $field, true) ? 1 : 0;
    }
    
    // Add interests to payload
    foreach ($interests_fields as $field => $nf_value) {
        $payload[$field] = get_user_meta($user_id, $field, true) ? 1 : 0;
    }
    
    // Include AOI fields in Workbooks payload with correct field names
    if (function_exists('dtr_get_aoi_field_names')) {
        $aoi_fields = array_keys(dtr_get_aoi_field_names());
        nf_debug_log('NF DEBUG: Available AOI fields in WP meta: ' . print_r($aoi_fields, true));
        
        // Map AOI fields from WP meta (with aoi_ prefix) to Workbooks fields (without aoi_ prefix)
        foreach ($aoi_fields as $wp_aoi_field) {
            $aoi_value = get_user_meta($user_id, $wp_aoi_field, true) ? 1 : 0;
            
            // Convert cf_person_aoi_xxxx to cf_person_xxxx for Workbooks
            $workbooks_field = str_replace('_aoi_', '_', $wp_aoi_field);
            $payload[$workbooks_field] = $aoi_value;
            
            nf_debug_log("NF DEBUG: AOI mapping: WP field $wp_aoi_field (value: $aoi_value) -> Workbooks field $workbooks_field");
        }
    } else {
        nf_debug_log('NF WARNING: dtr_get_aoi_field_names() function not found - AOI fields will not be synced');
    }

    // Add employer organisation if available
    if (!empty($employer)) {
        nf_debug_log("NF DEBUG: Processing employer organization for: '$employer'");
        if (function_exists('workbooks_get_or_create_organisation_id')) {
            nf_debug_log("NF DEBUG: workbooks_get_or_create_organisation_id() function found, calling...");
            $org_id = workbooks_get_or_create_organisation_id($employer);
            if ($org_id) {
                $payload['main_employer'] = $org_id;
                $payload['employer_link'] = $org_id;
                $payload['employer_name'] = $employer;
                // Also save the organization ID to WordPress user meta
                update_user_meta($user_id, 'main_employer', $org_id);
                update_user_meta($user_id, 'employer_link', $org_id);
                nf_debug_log("NF DEBUG: Employer org ID = $org_id (saved to WP meta)");
            } else {
                nf_debug_log("NF DEBUG: Employer org could not be found or created for '$employer'");
            }
        } else {
            nf_debug_log('NF ERROR: workbooks_get_or_create_organisation_id() function not found');
        }
    } else {
        nf_debug_log("NF DEBUG: Employer is empty, skipping organization creation");
    }

    nf_debug_log('NF DEBUG: Workbooks payload = ' . print_r($payload, true));

    // First check if a person with this email already exists
    try {
        $search_params = array(
            'main_location[email]' => $email,
            '_limit' => 1
        );
        nf_debug_log('NF DEBUG: Searching for existing email: ' . $email);
        $search_response = $workbooks->assertGet('crm/people', $search_params);
        nf_debug_log("Workbooks search response total records: " . ($search_response['total'] ?? 'unknown'));
        
        if (isset($search_response['data']) && count($search_response['data']) > 0) {
            $existing_person = $search_response['data'][0];
            $existing_email = $existing_person['main_location[email]'] ?? 'unknown';
            nf_debug_log('NF WARNING: Search returned person ID: ' . $existing_person['id']);
            nf_debug_log('NF WARNING: Submitted email: ' . $email);
            nf_debug_log('NF WARNING: Existing person email: ' . $existing_email);
            
            // Check if emails actually match (case-insensitive)
            if (strtolower($email) === strtolower($existing_email)) {
                nf_debug_log('NF WARNING: Exact email match found - duplicate person');
                nf_debug_log("=== NF DEBUG: WP User created but Workbooks person already exists for user ID $user_id ===");
                return;
            } else {
                nf_debug_log('NF WARNING: Email mismatch - this may be a Workbooks search issue');
                nf_debug_log('NF WARNING: Proceeding with person creation despite search result');
                // Continue with creation since emails don't actually match
            }
        }
    } catch (Exception $e) {
        nf_debug_log('NF ERROR: Failed to search for existing person - ' . $e->getMessage());
    }

    try {
        nf_debug_log('NF DEBUG: No existing person found, creating new person');
        nf_debug_log('NF DEBUG: About to call $workbooks->assertCreate() with payload at ' . date('Y-m-d H:i:s'));
        
        // Set a reasonable timeout before making the call
        set_time_limit(120); // 2 minutes max
        
        // Create array variable for passing by reference
        $people_array = [$payload];
        
        $start_time = microtime(true);
        $response = $workbooks->assertCreate('crm/people', $people_array);
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        nf_debug_log("NF DEBUG: Workbooks API call completed in {$duration} seconds at " . date('Y-m-d H:i:s'));
        nf_debug_log("Workbooks person record: " . print_r($response, true));

        if (isset($response['affected_objects'][0]['id'])) {
            $workbooks_id = $response['affected_objects'][0]['id'];
            $workbooks_ref = $response['affected_objects'][0]['object_ref'] ?? '';
            nf_debug_log("NF SUCCESS: Workbooks person created with ID $workbooks_id, ref $workbooks_ref");

            // Store Workbooks person ID and reference as WP user meta
            update_user_meta($user_id, 'workbooks_person_id', $workbooks_id);
            update_user_meta($user_id, 'workbooks_object_ref', $workbooks_ref);
        } else {
            nf_debug_log('NF WARNING: Workbooks create response missing ID: ' . print_r($response, true));
            nf_debug_log('NF WARNING: User created in WordPress but not in Workbooks - this may be a duplicate email or validation issue');
        }

    } catch (Exception $e) {
        nf_debug_log('NF ERROR: Failed to create Workbooks person - ' . $e->getMessage());
        nf_debug_log('NF ERROR: Exception details: ' . print_r($e, true));
        nf_debug_log('NF ERROR: Exception class: ' . get_class($e));
        nf_debug_log('NF ERROR: Exception file: ' . $e->getFile() . ' line: ' . $e->getLine());
        wp_delete_user($user_id); // Roll back user if Workbooks fails
        nf_debug_log("NF DEBUG: Rolled back WP user ID $user_id");
        return;
    } catch (Error $e) {
        nf_debug_log('NF FATAL ERROR: PHP Error during Workbooks creation - ' . $e->getMessage());
        nf_debug_log('NF FATAL ERROR: Error details: ' . print_r($e, true));
        wp_delete_user($user_id); // Roll back user if Workbooks fails
        nf_debug_log("NF DEBUG: Rolled back WP user ID $user_id");
        return;
    } catch (Throwable $e) {
        nf_debug_log('NF CRITICAL ERROR: Throwable during Workbooks creation - ' . $e->getMessage());
        nf_debug_log('NF CRITICAL ERROR: Throwable details: ' . print_r($e, true));
        wp_delete_user($user_id); // Roll back user if Workbooks fails
        nf_debug_log("NF DEBUG: Rolled back WP user ID $user_id");
        return;
    }

    nf_debug_log("=== NF DEBUG: WP User + Workbooks sync complete for user ID $user_id ===");
}
