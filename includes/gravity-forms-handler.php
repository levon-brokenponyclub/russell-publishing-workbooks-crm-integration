<?php
/**
 * Gravity Forms Registration Handler (Form ID 1)
 * Consolidated user creation + Workbooks sync, marketing prefs, TOI->AOI mapping,
 * employer org resolution, duplicate detection & rollback.
 */
if (!defined('ABSPATH')) exit;

/* --------------------------------------------------------------------------
 * Logging (kept near top so we can emit a boot message immediately)
 * -------------------------------------------------------------------------- */
function dtr_gf_reg_log($msg) {
    $timestamp = current_time('Y-m-d H:i:s');
    $line = "{$timestamp} [GF-Membership-Reg] {$msg}\n";
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $file1 = DTR_WORKBOOKS_LOG_DIR . 'gf-member-registration-debug.log';
        if (!file_exists(dirname($file1))) wp_mkdir_p(dirname($file1));
        if (defined('DTR_WORKBOOKS_LOG_DIR')) {
            $log_file1 = DTR_WORKBOOKS_LOG_DIR . basename($file1);
            file_put_contents($log_file1, $line, FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($file1, $line, FILE_APPEND | LOCK_EX);
        }
        $file2 = DTR_WORKBOOKS_LOG_DIR . 'gf-membership-registration-debug.log';
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
    $test_file = DTR_WORKBOOKS_LOG_DIR . 'gf-member-registration-debug.log';
    $test_msg = date('Y-m-d H:i:s') . " [GF-FILE-LOAD-TEST] Gravity Forms handler file is being loaded\n";
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $log_test_file = DTR_WORKBOOKS_LOG_DIR . basename($test_file);
        file_put_contents($log_test_file, $test_msg, FILE_APPEND | LOCK_EX);
    } else {
        file_put_contents($test_file, $test_msg, FILE_APPEND | LOCK_EX);
    }
}

dtr_gf_reg_log('[BOOT] Gravity Forms membership handler file loaded');

// Add an immediate hook test
add_action('init', function() {
    dtr_gf_reg_log('[INIT] WordPress init hook fired - GF handler is loaded');
});

/* --------------------------------------------------------------------------
 * Public hook entrypoint for Gravity Forms
 * -------------------------------------------------------------------------- */
add_action('gform_after_submission_1', 'dtr_gf_membership_registration_entrypoint', 10, 2);

function dtr_gf_membership_registration_entrypoint($entry, $form) {
    dtr_gf_reg_log('[HOOK] Gravity Forms after submission hook called - basic test');
    
    try {
        $form_id = $form['id'] ?? 'unknown';
        dtr_gf_reg_log('[HOOK] Gravity Forms after submission called with form ID: ' . $form_id);
        
        if (!is_array($entry) || empty($entry)) {
            dtr_gf_reg_log('[ENTRY] Invalid entry data structure');
            return;
        }
        
        if (intval($form_id) !== 1) {
            dtr_gf_reg_log('[ENTRY] Not form 1, skipping (form ID: ' . $form_id . ')');
            return;
        }
        
        dtr_gf_reg_log('[ENTRY] Gravity Forms membership registration submission received for form 1.');
        dtr_gf_membership_process($entry, $form);
        
    } catch (Throwable $t) {
        dtr_gf_reg_log('[FATAL] Throwable in entrypoint: ' . $t->getMessage());
    }
}

/* --------------------------------------------------------------------------
 * Orchestrator
 * -------------------------------------------------------------------------- */
function dtr_gf_membership_process($entry, $form) {
    $debug_id = 'GF-REG-' . uniqid();
    // Get plugin test mode setting for this form
    $options = get_option('dtr_workbooks_options', []);
    $test_mode = !empty($options['test_mode_forms'][1]) && $options['test_mode_forms'][1] == 1;

    $header = $test_mode ? "[{$debug_id}] ====== GF MEMBER REGISTRATION - TEST MODE ======" : "[{$debug_id}] ====== GF MEMBER REGISTRATION ======";
    dtr_gf_reg_log($header);
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Start processing form " . ($form['id'] ?? 'unknown'));
    
    $data = dtr_gf_collect_membership_data($entry, $form, $debug_id);
    if (!$data) { // already logged inside collector (missing required or duplicate WP user)
        dtr_gf_membership_log_failure('Validation or duplicate WP user', $entry);
        return false;
    }

    if ($test_mode) {
        dtr_gf_reg_log("[{$debug_id}] TEST MODE ENABLED: Skipping user creation and Workbooks sync");
        if (function_exists('error_log')) {
            error_log("[DTR] GF Test Mode: Active");
        }
        // Simulate user_id for payload/debug, but DO NOT create user or sync to Workbooks
        $user_id = 999999;
        dtr_gf_reg_log("[{$debug_id}] TEST MODE: Selected TOIs: " . json_encode($data['toi_selected']));
        $aoi_map = function_exists('dtr_map_toi_to_aoi') ? dtr_map_toi_to_aoi($data['toi_selected']) : [];
        dtr_gf_reg_log("[{$debug_id}] TEST MODE: AOI matrix result: " . json_encode($aoi_map));
        $payload = dtr_gf_build_workbooks_payload($user_id, $data, $debug_id);
        $payload = dtr_gf_maybe_attach_employer_org($user_id, $payload, $data, $debug_id);
        dtr_gf_reg_log("[{$debug_id}] TEST MODE: Would send payload: " . json_encode($payload));
        dtr_gf_reg_log("[{$debug_id}] TEST MODE: End of simulated registration.");
        dtr_gf_reg_log("[GF-Membership-Reg] ==== GF MEMBER REGISTRATION SUCCESSFUL - TEST MODE  =====");
        dtr_gf_reg_log("[GF-Membership-Reg] GF Member registration successful - Test Mode");
        return true;
    }

    $user_id = dtr_gf_create_wp_user_and_meta($data, $debug_id);
    if (!$user_id) { // logged inside create
        dtr_gf_membership_log_failure('WP user creation failed', $entry);
        return false;
    }

    dtr_gf_apply_marketing_and_interests($user_id, $data, $debug_id);

    if (!dtr_gf_handle_aoi_mapping($user_id, $debug_id)) {
        // non fatal – already logged if needed
    }

    // Workbooks integration
    $workbooks = (function_exists('get_workbooks_instance')) ? get_workbooks_instance() : null;
    if (!$workbooks) { 
        dtr_gf_reg_log("[{$debug_id}] GF ERROR: Workbooks API instance missing or null");
        dtr_gf_membership_log_failure('Workbooks API instance missing', $entry, $user_id, $data);
        return false; 
    }

    $payload = dtr_gf_build_workbooks_payload($user_id, $data, $debug_id);
    $payload = dtr_gf_maybe_attach_employer_org($user_id, $payload, $data, $debug_id);

    if (!dtr_gf_workbooks_person_sync($workbooks, $user_id, $payload, $data['email'], $debug_id)) {
        // rollback already handled in sync helper if fatal
        dtr_gf_membership_log_failure('Workbooks create failed / rolled back', $entry, $user_id, $data);
        return false;
    }
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: WP User + Workbooks sync complete for user ID {$user_id}");
    // Emit final human-readable summary block (covers new, duplicate-linked, etc.)
    dtr_gf_membership_log_summary($user_id, $data);
    return true;
}

/* --------------------------------------------------------------------------
 * Data collection & validation for Gravity Forms
 * -------------------------------------------------------------------------- */
function dtr_gf_collect_membership_data($entry, $form, $debug_id) {
    // Map Gravity Forms field IDs to data - Updated based on actual form structure
    $field_map = [
        'title' => '35',          // Title field ID (input_1_35)
        'first_name' => '1.3',    // First name field ID (input_1_1_3) 
        'last_name' => '1.6',     // Last name field ID (input_1_1_6)
        'email' => '2',           // Email field ID (input_1_2)
        'password' => '22',       // Password field ID (input_1_22)
        'employer' => '37',       // Employer field ID (input_1_37)
        'job_title' => '23',      // Job title field ID (input_1_23)
        'telephone' => '3',       // Phone field ID (input_1_3)
        'town' => '4.4',          // Town/City field ID (input_1_4_4)
        'postcode' => '4.5',      // Postcode field ID (input_1_4_5)
        'marketing_prefs' => '36', // Marketing preferences field ID (input_1_36)
        'topics_interest' => '33'  // Topics of interest field ID (input_1_33)
    ];

    // Extract data from Gravity Forms entry
    $raw_title = isset($entry[$field_map['title']]) ? $entry[$field_map['title']] : '';
    $title = dtr_gf_normalize_title($raw_title);
    
    $raw_employer = isset($entry[$field_map['employer']]) ? $entry[$field_map['employer']] : '';
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Raw title from form: '{$raw_title}' -> normalized: '{$title}'");
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Raw employer from form: '{$raw_employer}'");
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Entry data keys available: " . implode(', ', array_keys($entry)));
    
    $data = [
        'email'      => sanitize_email($entry[$field_map['email']] ?? ''),
        'password'   => $entry[$field_map['password']] ?? '',
        'first_name' => sanitize_text_field($entry[$field_map['first_name']] ?? ''),
        'last_name'  => sanitize_text_field($entry[$field_map['last_name']] ?? ''),
        'employer'   => sanitize_text_field($raw_employer),
        'title'      => sanitize_text_field($title),
        'telephone'  => sanitize_text_field($entry[$field_map['telephone']] ?? ''),
        'country'    => sanitize_text_field('South Africa'), // Default country as not in form
        'town'       => sanitize_text_field($entry[$field_map['town']] ?? ''),
        'postcode'   => sanitize_text_field($entry[$field_map['postcode']] ?? ''),
        'job_title'  => sanitize_text_field($entry[$field_map['job_title']] ?? ''),
        'marketing_selected' => dtr_gf_parse_checkbox_field($entry, $field_map['marketing_prefs']),
        'toi_selected'       => dtr_gf_parse_checkbox_field($entry, $field_map['topics_interest'])
    ];
    
    if (!$data['email'] || !is_email($data['email']) || !$data['password'] || !$data['first_name'] || !$data['last_name']) {
        dtr_gf_reg_log("[{$debug_id}] GF ERROR: Missing required fields.");
        return false;
    }
    if (username_exists($data['email']) || email_exists($data['email'])) {
        dtr_gf_reg_log("[{$debug_id}] GF WARNING: User already exists (WP) for email {$data['email']}");
        return false;
    }
    
    // Ensure both Employer and Claimed Employer fields are synchronized
    $employer_name = sanitize_text_field($raw_employer);
    $data['employer'] = $employer_name;
    $data['claimed_employer'] = $employer_name;
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Title: '{$title}', Employer Name: '{$employer_name}', Claimed Employer: '{$data['claimed_employer']}'");

    return $data;
}

/* --------------------------------------------------------------------------
 * Parse Gravity Forms checkbox fields
 * -------------------------------------------------------------------------- */
function dtr_gf_parse_checkbox_field($entry, $field_id) {
    $selected = [];
    
    // Gravity Forms stores checkbox values with decimal notation like 12.1, 12.2, etc.
    foreach ($entry as $key => $value) {
        // Check if this is a checkbox sub-field and has a value
        if (strpos($key, $field_id . '.') === 0 && !empty($value)) {
            $selected[] = $value;
        }
    }
    
    return $selected;
}

/* --------------------------------------------------------------------------
 * WP User creation & core meta
 * -------------------------------------------------------------------------- */
function dtr_gf_create_wp_user_and_meta(array $data, $debug_id) {
    $user_id = wp_create_user($data['email'], $data['password'], $data['email']);
    if (is_wp_error($user_id)) { 
        dtr_gf_reg_log("[{$debug_id}] GF ERROR: WP user creation failed: " . $user_id->get_error_message()); 
        return false; 
    }
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: WP user created - ID {$user_id}");
    
    $core_meta = [
        'created_via_gravity_form' => 1,
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'employer' => $data['employer'],
        'employer_name' => $data['employer'],
        'cf_person_claimed_employer' => $data['employer'],
        'person_personal_title' => $data['title'],
        'telephone' => $data['telephone'],
        'country' => $data['country'],
        'town' => $data['town'],
        'postcode' => $data['postcode'],
        'job_title' => $data['job_title'],
        'cf_person_dtr_subscriber_type' => 'Prospect',
        'cf_person_dtr_web_member' => 1,
        'lead_source_type' => 'Online Registration',
        'cf_person_is_person_active_or_inactive' => 'Active',
        'cf_person_data_source_detail' => 'DTR Web Member Signup'
    ];
    
    foreach ($core_meta as $k=>$v) update_user_meta($user_id,$k,$v);
    return $user_id;
}

/* --------------------------------------------------------------------------
 * Marketing & TOI
 * -------------------------------------------------------------------------- */
function dtr_gf_apply_marketing_and_interests($user_id, array $data, $debug_id) {
    // Marketing preferences - checkboxes use meta field names as values
    $marketing_fields = [
        'cf_person_dtr_news' => 'cf_person_dtr_news',
        'cf_person_dtr_events' => 'cf_person_dtr_events', 
        'cf_person_dtr_third_party' => 'cf_person_dtr_third_party',
        'cf_person_dtr_webinar' => 'cf_person_dtr_webinar'
    ];
    
    foreach ($marketing_fields as $form_value => $meta_field) {
        $selected = in_array($form_value, $data['marketing_selected'], true) ? 1 : 0;
        update_user_meta($user_id, $meta_field, $selected);
        dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Marketing field '{$meta_field}' = {$selected}");
    }
    
    // Topics of interest - checkboxes use meta field names as values  
    $toi_fields = [
        'cf_person_business' => 'cf_person_business',
        'cf_person_diseases' => 'cf_person_diseases',
        'cf_person_drugs_therapies' => 'cf_person_drugs_therapies',
        'cf_person_genomics_3774' => 'cf_person_genomics_3774',
        'cf_person_research_development' => 'cf_person_research_development',
        'cf_person_technology' => 'cf_person_technology',
        'cf_person_tools_techniques' => 'cf_person_tools_techniques'
    ];
    
    $selected_toi = [];
    foreach ($toi_fields as $form_value => $meta_field) {
        $selected = in_array($form_value, $data['toi_selected'], true) ? 1 : 0;
        update_user_meta($user_id, $meta_field, $selected);
        if ($selected) $selected_toi[] = $meta_field;
        dtr_gf_reg_log("[{$debug_id}] GF DEBUG: TOI field '{$meta_field}' = {$selected}");
    }
    
    // Save back for later AOI mapping
    $data['selected_toi'] = $selected_toi;
}

/* --------------------------------------------------------------------------
 * AOI Mapping
 * -------------------------------------------------------------------------- */
function dtr_gf_handle_aoi_mapping($user_id, $debug_id) {
    if (!function_exists('dtr_get_aoi_field_names') || !function_exists('dtr_map_toi_to_aoi')) {
        dtr_gf_reg_log("[{$debug_id}] GF DEBUG: AOI mapping functions not available");
        return false;
    }
    
    // Derive TOI selections from meta (so we rely on persisted data)
    $toi_fields = [ 
        'cf_person_business','cf_person_diseases','cf_person_drugs_therapies',
        'cf_person_genomics_3774','cf_person_research_development',
        'cf_person_technology','cf_person_tools_techniques' 
    ];
    
    $selected_toi = [];
    foreach ($toi_fields as $tf) { 
        $val = (int) get_user_meta($user_id,$tf,true);
        dtr_gf_reg_log("[{$debug_id}] GF DEBUG: TOI field '{$tf}' = {$val}");
        if ($val === 1) $selected_toi[] = $tf; 
    }
    
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: TOIs selected for AOI mapping: " . json_encode($selected_toi));
    
    if (!$selected_toi) {
        dtr_gf_reg_log("[{$debug_id}] GF DEBUG: No TOIs selected, skipping AOI mapping");
        return true; // nothing to map but not an error
    }
    
    $aoi_map = dtr_map_toi_to_aoi($selected_toi);
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: AOI matrix result: " . json_encode($aoi_map));
    
    $applied_count = 0;
    foreach ($aoi_map as $aoi_field=>$aoi_val) {
        update_user_meta($user_id,$aoi_field,$aoi_val);
        if ($aoi_val == 1) $applied_count++;
        dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Set AOI '{$aoi_field}' = {$aoi_val}");
    }
    
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: AOI mapping applied - {$applied_count} fields set to 1");
    return true;
}

/* --------------------------------------------------------------------------
 * Build Workbooks payload
 * -------------------------------------------------------------------------- */
function dtr_gf_build_workbooks_payload($user_id, array $data, $debug_id) {
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
        'employer_name' => $data['employer'],
        'cf_person_claimed_employer' => $data['employer'],
        'cf_person_dtr_subscriber_type' => 'Prospect',
        'cf_person_dtr_subscriber' => 1,
        'cf_person_dtr_web_member' => 1,
        'lead_source_type' => 'Online Registration',
        'cf_person_is_person_active_or_inactive' => 'Active',
        'cf_person_data_source_detail' => 'DTR Web Member Signup'
    ];
    
    // Marketing & TOI fields from meta (ensures consistency)
    $marketing_fields = [ 
        'cf_person_dtr_news','cf_person_dtr_events',
        'cf_person_dtr_third_party','cf_person_dtr_webinar' 
    ];
    foreach ($marketing_fields as $mf) $payload[$mf] = (int) get_user_meta($user_id,$mf,true);
    
    $toi_fields = [ 
        'cf_person_business','cf_person_diseases','cf_person_drugs_therapies',
        'cf_person_genomics_3774','cf_person_research_development',
        'cf_person_technology','cf_person_tools_techniques' 
    ];
    foreach ($toi_fields as $tf) $payload[$tf] = (int) get_user_meta($user_id,$tf,true);
    
    if (function_exists('dtr_get_aoi_field_names')) {
        foreach (array_keys(dtr_get_aoi_field_names()) as $aoi_wp_field) {
            $val = (int) get_user_meta($user_id,$aoi_wp_field,true);
            $wb_field = str_replace('_aoi_', '_', $aoi_wp_field);
            $payload[$wb_field] = $val;
        }
    }
    
    return $payload;
}

/* --------------------------------------------------------------------------
 * Employer organisation attachment
 * -------------------------------------------------------------------------- */
function dtr_gf_maybe_attach_employer_org($user_id, array $payload, array $data, $debug_id) {
    if (!empty($data['employer']) && function_exists('workbooks_get_or_create_organisation_id')) {
        $org_id = workbooks_get_or_create_organisation_id($data['employer']);
        if ($org_id) {
            $payload['main_employer'] = $org_id; 
            $payload['employer_link'] = $org_id;
            update_user_meta($user_id,'main_employer',$org_id); 
            update_user_meta($user_id,'employer_link',$org_id);
            dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Employer org ID resolved = {$org_id}");
        } else {
            dtr_gf_reg_log("[{$debug_id}] GF WARNING: Employer org lookup failed for '{$data['employer']}'");
        }
    }
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Prepared Workbooks payload");
    return $payload;
}

/* --------------------------------------------------------------------------
 * Workbooks sync (search + create)
 * -------------------------------------------------------------------------- */
function dtr_gf_workbooks_person_sync($workbooks, $user_id, array $payload, $email, $debug_id) {
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Searching for existing email: {$email}");
    try {
        $search = $workbooks->assertGet('crm/people', ['main_location[email]' => $email, '_limit'=>1]);
        if (!empty($search['data'][0]['id'])) {
            $existing_id = $search['data'][0]['id'];
            $existing_email = $search['data'][0]['main_location[email]'] ?? '';
            $existing_ref = $search['data'][0]['object_ref'] ?? '';
            dtr_gf_reg_log("[{$debug_id}] GF WARNING: Search returned person ID: {$existing_id}");
            dtr_gf_reg_log("[{$debug_id}] GF WARNING: Submitted email: {$email}");
            dtr_gf_reg_log("[{$debug_id}] GF WARNING: Existing person email: {$existing_email}");
            if ($existing_email && strtolower($existing_email) === strtolower($email)) {
                dtr_gf_reg_log("[{$debug_id}] GF WARNING: Exact email match found - duplicate person");
                update_user_meta($user_id,'workbooks_person_id',$existing_id);
                if ($existing_ref) update_user_meta($user_id,'workbooks_object_ref',$existing_ref);
                update_user_meta($user_id,'workbooks_existing_person',1);
                dtr_gf_reg_log("[{$debug_id}] GF DEBUG: WP User created but Workbooks person already exists for user ID {$user_id}");
                return true;
            } else {
                dtr_gf_reg_log("[{$debug_id}] GF WARNING: Email mismatch - proceeding with person creation despite search result");
            }
        }
    } catch (Exception $e) {
        dtr_gf_reg_log("[{$debug_id}] GF ERROR: Failed to search for existing person - ".$e->getMessage());
    }
    
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: No exact existing person found, creating new person");
    dtr_gf_reg_log("[{$debug_id}] GF DEBUG: About to call Workbooks->assertCreate() at " . date('Y-m-d H:i:s'));
    
    try {
        $start_time = microtime(true);
        $resp = $workbooks->assertCreate('crm/people', [$payload]);
        $duration = round(microtime(true) - $start_time, 2);
        dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Workbooks API call completed in {$duration} seconds");
        
        if (!empty($resp['affected_objects'][0]['id'])) {
            $pid = $resp['affected_objects'][0]['id'];
            $pref = $resp['affected_objects'][0]['object_ref'] ?? '';
            update_user_meta($user_id,'workbooks_person_id',$pid);
            update_user_meta($user_id,'workbooks_object_ref',$pref);
            dtr_gf_reg_log("[{$debug_id}] GF SUCCESS: Workbooks person created with ID {$pid}, ref {$pref}");
            return true;
        }
        
        dtr_gf_reg_log("[{$debug_id}] GF WARNING: Workbooks create response missing ID");
        return true; // not fatal
        
    } catch (Exception $e) {
        dtr_gf_reg_log("[{$debug_id}] GF ERROR: Failed to create Workbooks person - ".$e->getMessage());
        wp_delete_user($user_id);
        dtr_gf_reg_log("[{$debug_id}] GF DEBUG: Rolled back WP user ID {$user_id}");
        return false;
    }
}

/**
 * Normalize title values from the form to proper format
 */
function dtr_gf_normalize_title($raw_title) {
    if (empty($raw_title)) return '';
    
    // Map of form values to proper titles
    $title_map = [
        '.Dr' => 'Dr.',
        'Dr' => 'Dr.',
        'Mr' => 'Mr.',
        'Mrs' => 'Mrs.',
        'Master' => 'Master',
        'Miss' => 'Miss.',
        'Ms' => 'Ms.',
        'Prof' => 'Prof.'
    ];
    
    // Check if we have a direct mapping
    if (isset($title_map[$raw_title])) {
        return $title_map[$raw_title];
    }
    
    // If not found, return the sanitized raw value
    return sanitize_text_field($raw_title);
}

/* --------------------------------------------------------------------------
 * Summary logger (post-success)
 * -------------------------------------------------------------------------- */
if (!function_exists('dtr_gf_membership_log_summary')) {
    function dtr_gf_membership_log_summary($user_id, array $data) {
        // Fetch latest meta (ensures we log persisted state rather than transient arrays)
        $marketing_map = [
            'cf_person_dtr_news'        => 'Newsletter',
            'cf_person_dtr_events'      => 'Event',
            'cf_person_dtr_third_party' => 'Third party',
            'cf_person_dtr_webinar'     => 'Webinar'
        ];
        $toi_map = [
            'cf_person_business'              => 'Business',
            'cf_person_diseases'              => 'Diseases',
            'cf_person_drugs_therapies'       => 'Drugs & Therapies',
            'cf_person_genomics_3774'         => 'Genomics',
            'cf_person_research_development'  => 'Research & Development',
            'cf_person_technology'            => 'Technology',
            'cf_person_tools_techniques'      => 'Tools & Techniques'
        ];

        $wp_user_created_line = "SUCCESS: Wordpress user created with ID: {$user_id}";
        $workbooks_person_id = get_user_meta($user_id, 'workbooks_person_id', true);
        if ($workbooks_person_id) {
            $workbooks_created_line = "SUCCESS: Workbooks person created with ID: {$workbooks_person_id}";
        } else {
            $workbooks_created_line = "SUCCESS: Workbooks person created with ID: (none / existing)";
        }
        $workbooks_ref = get_user_meta($user_id,'workbooks_object_ref',true);
        $duplicate_flag = (int) get_user_meta($user_id,'workbooks_existing_person',true) === 1;

        dtr_gf_reg_log('==== GF MEMBER REGISTRATION =====');
        dtr_gf_reg_log('User Details:');
        dtr_gf_reg_log('First Name: ' . ($data['first_name'] ?? ''));
        dtr_gf_reg_log('Last Name: ' . ($data['last_name'] ?? ''));
        dtr_gf_reg_log('Email Address: ' . ($data['email'] ?? ''));
        dtr_gf_reg_log('Telephone Number: ' . ($data['telephone'] ?? ''));
        dtr_gf_reg_log('Job Title: ' . ($data['job_title'] ?? ''));
        dtr_gf_reg_log('Employer: ' . ($data['employer'] ?? ''));
        dtr_gf_reg_log('Country: ' . ($data['country'] ?? ''));
        dtr_gf_reg_log('Town: ' . ($data['town'] ?? ''));
        dtr_gf_reg_log('Post Code: ' . ($data['postcode'] ?? ''));
        dtr_gf_reg_log('');
        dtr_gf_reg_log('---- MARKETING COMMUNICATION ----');
        $marketing_summary = [];
        foreach ($marketing_map as $meta_key => $label) {
            $val = (int) get_user_meta($user_id, $meta_key, true) === 1 ? 'Yes' : 'No';
            dtr_gf_reg_log($label . ': - ' . $val);
            $marketing_summary[] = $label . '=' . $val;
        }
        dtr_gf_reg_log('');
        dtr_gf_reg_log('---- TOPICS OF INTEREST ----');
        $toi_summary = [];
        foreach ($toi_map as $meta_key => $label) {
            $val = (int) get_user_meta($user_id, $meta_key, true) === 1 ? 'Yes' : 'No';
            dtr_gf_reg_log($label . ': - ' . $val);
            $toi_summary[] = $label . '=' . $val;
        }
        dtr_gf_reg_log('');
        dtr_gf_reg_log('---- WORDPRESS/WORKBOOKS ----');
        dtr_gf_reg_log($wp_user_created_line);
        dtr_gf_reg_log($workbooks_created_line);
        if ($workbooks_ref) dtr_gf_reg_log('Workbooks Person Ref: ' . $workbooks_ref);
        if ($duplicate_flag) dtr_gf_reg_log('Duplicate Detected: YES (existing person linked)');
        dtr_gf_reg_log('');
        dtr_gf_reg_log('Communication Preferences: ' . implode(', ', $marketing_summary));
        dtr_gf_reg_log('Topics of Interest: ' . implode(', ', $toi_summary));
        dtr_gf_reg_log('');
        dtr_gf_reg_log('==== GF MEMBER REGISTRATION SUCCESSFUL =====');
        dtr_gf_reg_log('GF Member registration successful - Celebrate Good Times Come On!!!');
    }
}

/* --------------------------------------------------------------------------
 * Failure logger (captures partial state)
 * -------------------------------------------------------------------------- */
if (!function_exists('dtr_gf_membership_log_failure')) {
    function dtr_gf_membership_log_failure($reason, $entry = [], $user_id = null, $data = []) {
        dtr_gf_reg_log('==== GF MEMBER REGISTRATION FAILURE =====');
        dtr_gf_reg_log('Reason: ' . $reason);
        if ($user_id) dtr_gf_reg_log('WP User ID (may be rolled back): ' . $user_id);
        if (!empty($data)) {
            dtr_gf_reg_log('Collected (partial) Data Snapshot: ' . wp_json_encode(array_intersect_key($data, array_flip(['email','first_name','last_name','employer','country','town']))));
        } elseif (!empty($entry)) {
            $subset = [];
            foreach ($entry as $key => $value) {
                if (in_array($key, ['4', '2', '3', '6', '9', '10'])) { // Corresponding field IDs
                    $subset[$key] = $value;
                }
            }
            if ($subset) dtr_gf_reg_log('Entry Snapshot: ' . wp_json_encode($subset));
        }
        dtr_gf_reg_log('========================================');
    }
}

// Compatibility function
if (!function_exists('dtr_process_gf_user_registration')) {
    function dtr_process_gf_user_registration($entry, $form) {
        if (intval($form['id']) !== 1) return false; 
        return dtr_gf_membership_process($entry, $form);
    }
}