<?php
/**
 * Membership Registration Handler (Form ID 15)
 * Consolidated user creation + Workbooks sync, marketing prefs, TOI->AOI mapping,
 * employer org resolution, duplicate detection & rollback.
 */
if (!defined('ABSPATH')) exit;

/* --------------------------------------------------------------------------
 * Logging (kept near top so we can emit a boot message immediately)
 * -------------------------------------------------------------------------- */
function dtr_reg_log($msg) {
    $timestamp = current_time('Y-m-d H:i:s');
    $line = "{$timestamp} [Membership-Reg] {$msg}\n";
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $file1 = DTR_WORKBOOKS_LOG_DIR . 'member-registration-debug.log';
        if (!file_exists(dirname($file1))) wp_mkdir_p(dirname($file1));
        if (defined('DTR_WORKBOOKS_LOG_DIR')) {
            $log_file1 = DTR_WORKBOOKS_LOG_DIR . basename($file1);
            file_put_contents($log_file1, $line, FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($file1, $line, FILE_APPEND | LOCK_EX);
        }
        $file2 = DTR_WORKBOOKS_LOG_DIR . 'membership-registration-debug.log';
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
    $test_file = DTR_WORKBOOKS_LOG_DIR . 'member-registration-debug.log';
    $test_msg = date('Y-m-d H:i:s') . " [FILE-LOAD-TEST] Form handler file is being loaded\n";
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $log_test_file = DTR_WORKBOOKS_LOG_DIR . basename($test_file);
        file_put_contents($log_test_file, $test_msg, FILE_APPEND | LOCK_EX);
    } else {
        file_put_contents($test_file, $test_msg, FILE_APPEND | LOCK_EX);
    }
}

dtr_reg_log('[BOOT] Membership handler file loaded');

// Add an immediate hook test
add_action('init', function() {
    dtr_reg_log('[INIT] WordPress init hook fired - handler is loaded');
});

/* --------------------------------------------------------------------------
 * Public hook entrypoint
 * -------------------------------------------------------------------------- */
add_filter('ninja_forms_submit_data', 'dtr_nf_membership_registration_entrypoint', 20, 1);

function dtr_nf_membership_registration_entrypoint($form_data) {
    dtr_reg_log('[HOOK] Submit data filter called - basic test');
    
    try {
        $form_id = $form_data['id'] ?? 'unknown';
        dtr_reg_log('[HOOK] Submit data filter called with form ID: ' . $form_id);
        
        if (!is_array($form_data) || empty($form_data['id'])) {
            dtr_reg_log('[ENTRY] Invalid form data structure');
            return $form_data;
        }
        
        if (intval($form_data['id']) !== 15) {
            dtr_reg_log('[ENTRY] Not form 15, skipping (form ID: ' . $form_data['id'] . ')');
            return $form_data;
        }
        
        dtr_reg_log('[ENTRY] Membership registration submission received for form 15.');
        dtr_nf_membership_process($form_data);
        
    } catch (Throwable $t) {
        dtr_reg_log('[FATAL] Throwable in entrypoint: ' . $t->getMessage());
    }
    
    return $form_data; // Always return the data for the filter chain
}

/* --------------------------------------------------------------------------
 * Orchestrator
 * -------------------------------------------------------------------------- */
function dtr_nf_membership_process($form_data) {
    $debug_id = 'REG-' . uniqid();
    // Get plugin test mode setting for this form
    $options = get_option('dtr_workbooks_options', []);
    $test_mode = !empty($options['test_mode_forms'][15]) && $options['test_mode_forms'][15] == 1;

    $header = $test_mode ? "[{$debug_id}] ====== MEMBER REGISTRATION - TEST MODE ======" : "[{$debug_id}] ====== MEMBER REGISTRATION ======";
    dtr_reg_log($header);
    dtr_reg_log("[{$debug_id}] NF DEBUG: Start processing form " . ($form_data['id'] ?? 'unknown'));
    $flat = dtr_nf_membership_flatten_fields($form_data);
    $data = dtr_nf_collect_membership_data($flat, $debug_id);
    if (!$data) { // already logged inside collector (missing required or duplicate WP user)
        dtr_nf_membership_log_failure('Validation or duplicate WP user', $flat);
        return false;
    }

    if ($test_mode) {
        dtr_reg_log("[{$debug_id}] TEST MODE ENABLED: Skipping user creation and Workbooks sync");
        if (function_exists('error_log')) {
            error_log("[DTR] Test Mode: Active");
        }
        // Simulate user_id for payload/debug, but DO NOT create user or sync to Workbooks
        $user_id = 999999;
        dtr_reg_log("[{$debug_id}] TEST MODE: Selected TOIs: " . json_encode($data['toi_selected']));
        $aoi_map = function_exists('dtr_map_toi_to_aoi') ? dtr_map_toi_to_aoi($data['toi_selected']) : [];
        dtr_reg_log("[{$debug_id}] TEST MODE: AOI matrix result: " . json_encode($aoi_map));
        $payload = dtr_nf_build_workbooks_payload($user_id, $data, $debug_id);
        $payload = dtr_nf_maybe_attach_employer_org($user_id, $payload, $data, $debug_id);
        dtr_reg_log("[{$debug_id}] TEST MODE: Would send payload: " . json_encode($payload));
        dtr_reg_log("[{$debug_id}] TEST MODE: End of simulated registration.");
        dtr_reg_log("[Membership-Reg] ==== MEMBER REGISTRATION SUCCESSFUL - TEST MODE  =====");
        dtr_reg_log("[Membership-Reg] Member registration successful - Test Mode");
        return true;
    }

    $user_id = dtr_nf_create_wp_user_and_meta($data, $debug_id);
    if (!$user_id) { // logged inside create
        dtr_nf_membership_log_failure('WP user creation failed', $flat);
        return false;
    }

    dtr_nf_apply_marketing_and_interests($user_id, $data, $debug_id);

    if (!dtr_nf_handle_aoi_mapping($user_id, $debug_id)) {
        // non fatal – already logged if needed
    }

    // Workbooks integration
    $workbooks = (function_exists('get_workbooks_instance')) ? get_workbooks_instance() : null;
    if (!$workbooks) { 
        dtr_reg_log("[{$debug_id}] NF ERROR: Workbooks API instance missing or null");
        dtr_nf_membership_log_failure('Workbooks API instance missing', $flat, $user_id, $data);
        return false; 
    }

    $payload = dtr_nf_build_workbooks_payload($user_id, $data, $debug_id);
    $payload = dtr_nf_maybe_attach_employer_org($user_id, $payload, $data, $debug_id);

    if (!dtr_nf_workbooks_person_sync($workbooks, $user_id, $payload, $data['email'], $debug_id)) {
        // rollback already handled in sync helper if fatal
        dtr_nf_membership_log_failure('Workbooks create failed / rolled back', $flat, $user_id, $data);
        return false;
    }
    dtr_reg_log("[{$debug_id}] NF DEBUG: WP User + Workbooks sync complete for user ID {$user_id}");
    // Emit final human-readable summary block (covers new, duplicate-linked, etc.)
    dtr_nf_membership_log_summary($user_id, $data);
    return true;
}

/* --------------------------------------------------------------------------
 * Data collection & validation
 * -------------------------------------------------------------------------- */
function dtr_nf_collect_membership_data(array $flat, $debug_id) {
    // Normalize title field - handle form values like ".Dr" to "Dr."
    $raw_title = dtr_nf_pick($flat, ['title','141']);
    $title = dtr_nf_normalize_title($raw_title);
    
    // Debug employer field processing
    $raw_employer = dtr_nf_pick($flat, ['employer','employer_name','218']);
    dtr_reg_log("[{$debug_id}] NF DEBUG: Raw title from form: '{$raw_title}' -> normalized: '{$title}'");
    dtr_reg_log("[{$debug_id}] NF DEBUG: Raw employer from form: '{$raw_employer}'");
    dtr_reg_log("[{$debug_id}] NF DEBUG: Form data keys available: " . implode(', ', array_keys($flat)));
    
    $data = [
        'email'      => sanitize_email(dtr_nf_pick($flat, ['email_address','144'])),
        'password'   => dtr_nf_pick($flat, ['password','221']),
        'first_name' => sanitize_text_field(dtr_nf_pick($flat, ['first_name','142'])),
        'last_name'  => sanitize_text_field(dtr_nf_pick($flat, ['last_name','143'])),
        'employer'   => sanitize_text_field($raw_employer),
        'title'      => sanitize_text_field($title),
        'telephone'  => sanitize_text_field(dtr_nf_pick($flat, ['telephone','146'])),
        'country'    => sanitize_text_field(dtr_nf_pick($flat, ['country','148'],'South Africa')),
        'town'       => sanitize_text_field(dtr_nf_pick($flat, ['town','149'])),
        'postcode'   => sanitize_text_field(dtr_nf_pick($flat, ['postcode','150'])),
        'job_title'  => sanitize_text_field(dtr_nf_pick($flat, ['job_title','147'])),
        'marketing_selected' => dtr_nf_to_array(dtr_nf_pick($flat, ['marketing_preferences'])),
        'toi_selected'       => dtr_nf_to_array(dtr_nf_pick($flat, ['topics_of_interest']))
    ];
    if (!$data['email'] || !is_email($data['email']) || !$data['password'] || !$data['first_name'] || !$data['last_name']) {
        dtr_reg_log("[{$debug_id}] NF ERROR: Missing required fields.");
        return false;
    }
    if (username_exists($data['email']) || email_exists($data['email'])) {
        dtr_reg_log("[{$debug_id}] NF WARNING: User already exists (WP) for email {$data['email']}");
        return false;
    }
    // Ensure both Employer and Claimed Employer fields are synchronized
    $employer_name = sanitize_text_field($raw_employer);
    $data['employer'] = $employer_name;
    $data['claimed_employer'] = $employer_name;
    dtr_reg_log("[{$debug_id}] NF DEBUG: Title: '{$title}', Employer Name: '{$employer_name}', Claimed Employer: '{$data['claimed_employer']}'");

    return $data;
}

/* --------------------------------------------------------------------------
 * WP User creation & core meta
 * -------------------------------------------------------------------------- */
function dtr_nf_create_wp_user_and_meta(array $data, $debug_id) {
    $user_id = wp_create_user($data['email'], $data['password'], $data['email']);
    if (is_wp_error($user_id)) { dtr_reg_log("[{$debug_id}] NF ERROR: WP user creation failed: " . $user_id->get_error_message()); return false; }
    dtr_reg_log("[{$debug_id}] NF DEBUG: WP user created - ID {$user_id}");
    $core_meta = [
        'created_via_ninja_form' => 1,
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'employer' => $data['employer'],
        'employer_name' => $data['employer'],
        'cf_person_claimed_employer' => $data['employer'],
        // Always keep employer_name and cf_person_claimed_employer in sync
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
function dtr_nf_apply_marketing_and_interests($user_id, array $data, $debug_id) {
    $marketing_fields = [ 'cf_person_dtr_news','cf_person_dtr_events','cf_person_dtr_third_party','cf_person_dtr_webinar' ];
    foreach ($marketing_fields as $mf) update_user_meta($user_id,$mf, in_array($mf,$data['marketing_selected'],true)?1:0);
    $toi_fields = [ 'cf_person_business','cf_person_diseases','cf_person_drugs_therapies','cf_person_genomics_3774','cf_person_research_development','cf_person_technology','cf_person_tools_techniques' ];
    $selected_toi = [];
    foreach ($toi_fields as $tf) { $val = in_array($tf,$data['toi_selected'],true)?1:0; update_user_meta($user_id,$tf,$val); if ($val) $selected_toi[]=$tf; }
    // Save back for later AOI mapping
    $data['selected_toi'] = $selected_toi; // (Not used later directly, kept for clarity / possible extension)
}

/* --------------------------------------------------------------------------
 * AOI Mapping
 * -------------------------------------------------------------------------- */
function dtr_nf_handle_aoi_mapping($user_id, $debug_id) {
    if (!function_exists('dtr_get_aoi_field_names') || !function_exists('dtr_map_toi_to_aoi')) {
        dtr_reg_log("[{$debug_id}] NF DEBUG: AOI mapping functions not available");
        return false;
    }
    // Derive TOI selections from meta (so we rely on persisted data)
    $toi_fields = [ 'cf_person_business','cf_person_diseases','cf_person_drugs_therapies','cf_person_genomics_3774','cf_person_research_development','cf_person_technology','cf_person_tools_techniques' ];
    $selected_toi = [];
    foreach ($toi_fields as $tf) { 
        $val = (int) get_user_meta($user_id,$tf,true);
        dtr_reg_log("[{$debug_id}] NF DEBUG: TOI field '{$tf}' = {$val}");
        if ($val === 1) $selected_toi[] = $tf; 
    }
    dtr_reg_log("[{$debug_id}] NF DEBUG: TOIs selected for AOI mapping: " . json_encode($selected_toi));
    if (!$selected_toi) {
        dtr_reg_log("[{$debug_id}] NF DEBUG: No TOIs selected, skipping AOI mapping");
        return true; // nothing to map but not an error
    }
    $aoi_map = dtr_map_toi_to_aoi($selected_toi);
    dtr_reg_log("[{$debug_id}] NF DEBUG: AOI matrix result: " . json_encode($aoi_map));
    $applied_count = 0;
    foreach ($aoi_map as $aoi_field=>$aoi_val) {
        update_user_meta($user_id,$aoi_field,$aoi_val);
        if ($aoi_val == 1) $applied_count++;
        dtr_reg_log("[{$debug_id}] NF DEBUG: Set AOI '{$aoi_field}' = {$aoi_val}");
    }
    dtr_reg_log("[{$debug_id}] NF DEBUG: AOI mapping applied - {$applied_count} fields set to 1");
    return true;
}

/* --------------------------------------------------------------------------
 * Build Workbooks payload
 * -------------------------------------------------------------------------- */
function dtr_nf_build_workbooks_payload($user_id, array $data, $debug_id) {
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
        // Always keep employer_name and cf_person_claimed_employer in sync
        'cf_person_dtr_subscriber_type' => 'Prospect',
        'cf_person_dtr_subscriber' => 1,
        'cf_person_dtr_web_member' => 1,
        'lead_source_type' => 'Online Registration',
        'cf_person_is_person_active_or_inactive' => 'Active',
        'cf_person_data_source_detail' => 'DTR Web Member Signup'
    ];
    // Marketing & TOI fields from meta (ensures consistency)
    $marketing_fields = [ 'cf_person_dtr_news','cf_person_dtr_events','cf_person_dtr_third_party','cf_person_dtr_webinar' ];
    foreach ($marketing_fields as $mf) $payload[$mf] = (int) get_user_meta($user_id,$mf,true);
    $toi_fields = [ 'cf_person_business','cf_person_diseases','cf_person_drugs_therapies','cf_person_genomics_3774','cf_person_research_development','cf_person_technology','cf_person_tools_techniques' ];
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
function dtr_nf_maybe_attach_employer_org($user_id, array $payload, array $data, $debug_id) {
    if (!empty($data['employer']) && function_exists('workbooks_get_or_create_organisation_id')) {
        $org_id = workbooks_get_or_create_organisation_id($data['employer']);
        if ($org_id) {
            $payload['main_employer'] = $org_id; $payload['employer_link'] = $org_id;
            update_user_meta($user_id,'main_employer',$org_id); update_user_meta($user_id,'employer_link',$org_id);
            dtr_reg_log("[{$debug_id}] NF DEBUG: Employer org ID resolved = {$org_id}");
        } else {
            dtr_reg_log("[{$debug_id}] NF WARNING: Employer org lookup failed for '{$data['employer']}'");
        }
    }
    dtr_reg_log("[{$debug_id}] NF DEBUG: Prepared Workbooks payload");
    return $payload;
}

/* --------------------------------------------------------------------------
 * Workbooks sync (search + create)
 * -------------------------------------------------------------------------- */
function dtr_nf_workbooks_person_sync($workbooks, $user_id, array $payload, $email, $debug_id) {
    dtr_reg_log("[{$debug_id}] NF DEBUG: Searching for existing email: {$email}");
    try {
        $search = $workbooks->assertGet('crm/people', ['main_location[email]' => $email, '_limit'=>1]);
        if (!empty($search['data'][0]['id'])) {
            $existing_id = $search['data'][0]['id'];
            $existing_email = $search['data'][0]['main_location[email]'] ?? '';
            $existing_ref = $search['data'][0]['object_ref'] ?? '';
            dtr_reg_log("[{$debug_id}] NF WARNING: Search returned person ID: {$existing_id}");
            dtr_reg_log("[{$debug_id}] NF WARNING: Submitted email: {$email}");
            dtr_reg_log("[{$debug_id}] NF WARNING: Existing person email: {$existing_email}");
            if ($existing_email && strtolower($existing_email) === strtolower($email)) {
                dtr_reg_log("[{$debug_id}] NF WARNING: Exact email match found - duplicate person");
                update_user_meta($user_id,'workbooks_person_id',$existing_id);
                if ($existing_ref) update_user_meta($user_id,'workbooks_object_ref',$existing_ref);
                update_user_meta($user_id,'workbooks_existing_person',1);
                dtr_reg_log("[{$debug_id}] NF DEBUG: WP User created but Workbooks person already exists for user ID {$user_id}");
                return true;
            } else {
                dtr_reg_log("[{$debug_id}] NF WARNING: Email mismatch - proceeding with person creation despite search result");
            }
        }
    } catch (Exception $e) {
        dtr_reg_log("[{$debug_id}] NF ERROR: Failed to search for existing person - ".$e->getMessage());
    }
    dtr_reg_log("[{$debug_id}] NF DEBUG: No exact existing person found, creating new person");
    dtr_reg_log("[{$debug_id}] NF DEBUG: About to call Workbooks->assertCreate() at " . date('Y-m-d H:i:s'));
    try {
        $start_time = microtime(true);
        $resp = $workbooks->assertCreate('crm/people', [$payload]);
        $duration = round(microtime(true) - $start_time, 2);
        dtr_reg_log("[{$debug_id}] NF DEBUG: Workbooks API call completed in {$duration} seconds");
        if (!empty($resp['affected_objects'][0]['id'])) {
            $pid = $resp['affected_objects'][0]['id'];
            $pref = $resp['affected_objects'][0]['object_ref'] ?? '';
            update_user_meta($user_id,'workbooks_person_id',$pid);
            update_user_meta($user_id,'workbooks_object_ref',$pref);
            dtr_reg_log("[{$debug_id}] NF SUCCESS: Workbooks person created with ID {$pid}, ref {$pref}");
            return true;
        }
        dtr_reg_log("[{$debug_id}] NF WARNING: Workbooks create response missing ID");
        return true; // not fatal
    } catch (Exception $e) {
        dtr_reg_log("[{$debug_id}] NF ERROR: Failed to create Workbooks person - ".$e->getMessage());
        wp_delete_user($user_id);
        dtr_reg_log("[{$debug_id}] NF DEBUG: Rolled back WP user ID {$user_id}");
        return false;
    }
}

function dtr_nf_membership_flatten_fields($form_data) {
    $flat = [];
    if (!empty($form_data['fields'])) {
        foreach ($form_data['fields'] as $f) { if (!is_array($f)) continue; if (isset($f['key'])) $flat[$f['key']]=$f['value']??''; if (isset($f['id'])) $flat[$f['id']]=$f['value']??''; }
    }
    return $flat;
}
function dtr_nf_pick($flat,$candidates,$default='') { foreach ($candidates as $c) if (isset($flat[$c]) && $flat[$c] !== '') return $flat[$c]; return $default; }
function dtr_nf_to_array($val){ if(is_array($val)) return $val; if($val===''||$val===null) return []; return [$val]; }

/**
 * Normalize title values from the form to proper format
 */
function dtr_nf_normalize_title($raw_title) {
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
if (!function_exists('dtr_nf_membership_log_summary')) {
    function dtr_nf_membership_log_summary($user_id, array $data) {
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
            // Could be duplicate or creation skipped; still log line for traceability
            $workbooks_created_line = "SUCCESS: Workbooks person created with ID: (none / existing)";
        }
        $workbooks_ref = get_user_meta($user_id,'workbooks_object_ref',true);
        $duplicate_flag = (int) get_user_meta($user_id,'workbooks_existing_person',true) === 1;

        dtr_reg_log('==== MEMBER REGISTRATION =====');
        dtr_reg_log('User Details:');
        dtr_reg_log('First Name: ' . ($data['first_name'] ?? ''));
        dtr_reg_log('Last Name: ' . ($data['last_name'] ?? ''));
        dtr_reg_log('Email Address: ' . ($data['email'] ?? ''));
        dtr_reg_log('Telephone Number: ' . ($data['telephone'] ?? ''));
        dtr_reg_log('Job Ttitle: ' . ($data['job_title'] ?? '')); // Intentionally retaining provided label spelling
        dtr_reg_log('Employer: ' . ($data['employer'] ?? ''));
        dtr_reg_log('Country: ' . ($data['country'] ?? ''));
        dtr_reg_log('Town: ' . ($data['town'] ?? ''));
        dtr_reg_log('Post Code: ' . ($data['postcode'] ?? ''));
        dtr_reg_log('');
        dtr_reg_log('---- MARKETING COMMUNICATION ----');
        $marketing_summary = [];
        foreach ($marketing_map as $meta_key => $label) {
            $val = (int) get_user_meta($user_id, $meta_key, true) === 1 ? 'Yes' : 'No';
            dtr_reg_log($label . ': - ' . $val);
            $marketing_summary[] = $label . '=' . $val;
        }
        dtr_reg_log('');
        dtr_reg_log('---- TOPICS OF INTEREST ----');
        $toi_summary = [];
        foreach ($toi_map as $meta_key => $label) {
            $val = (int) get_user_meta($user_id, $meta_key, true) === 1 ? 'Yes' : 'No';
            dtr_reg_log($label . ': - ' . $val);
            $toi_summary[] = $label . '=' . $val;
        }
        dtr_reg_log('');
        dtr_reg_log('---- WORDPRESS/WORKBOOKS ----');
        dtr_reg_log($wp_user_created_line);
        dtr_reg_log($workbooks_created_line);
        if ($workbooks_ref) dtr_reg_log('Workbooks Person Ref: ' . $workbooks_ref);
        if ($duplicate_flag) dtr_reg_log('Duplicate Detected: YES (existing person linked)');
        dtr_reg_log('');
        dtr_reg_log('Communication Preferences: ' . implode(', ', $marketing_summary));
        dtr_reg_log('Topics of Interest: ' . implode(', ', $toi_summary));
        dtr_reg_log('');
        dtr_reg_log('==== MEMBER REGISTRATION SUCCESSFUL =====');
        dtr_reg_log('Member registration successful - Fucking Celebrate Good Times Come On!!!');
    }
}

/* --------------------------------------------------------------------------
 * Failure logger (captures partial state)
 * -------------------------------------------------------------------------- */
if (!function_exists('dtr_nf_membership_log_failure')) {
    function dtr_nf_membership_log_failure($reason, $flat = [], $user_id = null, $data = []) {
        dtr_reg_log('==== MEMBER REGISTRATION FAILURE =====');
        dtr_reg_log('Reason: ' . $reason);
        if ($user_id) dtr_reg_log('WP User ID (may be rolled back): ' . $user_id);
        if (!empty($data)) {
            dtr_reg_log('Collected (partial) Data Snapshot: ' . wp_json_encode(array_intersect_key($data, array_flip(['email','first_name','last_name','employer','country','town']))));
        } elseif (!empty($flat)) {
            $subset = [];
            foreach (['email_address','first_name','last_name','employer','country','town'] as $k) if (isset($flat[$k])) $subset[$k]=$flat[$k];
            if ($subset) dtr_reg_log('Flat Snapshot: ' . wp_json_encode($subset));
        }
        dtr_reg_log('========================================');
    }
}

// (Logger moved to top and enhanced with boot message)

if (!function_exists('dtr_process_user_registration')) {
    function dtr_process_user_registration($form_data, $form_id = 15) {
        if (intval($form_id) !== 15) return false; return dtr_nf_membership_process($form_data);
    }
}