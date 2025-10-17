<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('workbooks_crm_get_personal_titles')) {
    function workbooks_crm_get_personal_titles() {
        // Workbooks CRM title values (from person_personal_title field)
        return [
            'Dr.' => 'Dr.',
            'Master' => 'Master', 
            'Miss' => 'Miss',
            'Mr.' => 'Mr',
            'Mrs.' => 'Mrs.',
            'Ms.' => 'Ms',
            'Prof.' => 'Prof.'
        ];
    }
}

// Get all TOI options for admin display
if (!function_exists('dtr_get_all_toi_options')) {
    function dtr_get_all_toi_options() {
        return [
            'cf_person_business' => 'Business',
            'cf_person_diseases' => 'Diseases',
            'cf_person_drugs_therapies' => 'Drugs & Therapies',
            'cf_person_genomics_3774' => 'Genomics',
            'cf_person_research_development' => 'Research & Development',
            'cf_person_technology' => 'Technology',
            'cf_person_tools_techniques' => 'Tools & Techniques',
        ];
    }
}

// Get all AOI field names for admin display
if (!function_exists('dtr_get_aoi_field_names')) {
    function dtr_get_aoi_field_names() {
        return [
            'cf_person_analysis' => 'Analysis',
            'cf_person_assays' => 'Assays',
            'cf_person_biomarkers' => 'Biomarkers',
            'cf_person_clinical_trials' => 'Clinical trials',
            'cf_person_preclinical_research' => 'Preclinical Research',
            'cf_person_drug_discovery_processes' => 'Drug Discovery Processes',
            'cf_person_genomics' => 'Genomics',
            'cf_person_hit_to_lead' => 'Hit to Lead',
            'cf_person_imaging' => 'Imaging',
            'cf_person_toxicology' => 'Toxicology',
            'cf_person_artificial_intelligencemachine_learning' => 'Artificial Intelligence/Machine Learning',
            'cf_person_cell_gene_therapy' => 'Cell & Gene Therapy',
            'cf_person_informatics' => 'Informatics',
            'cf_person_lab_automation' => 'Lab Automation',
            'cf_person_molecular_diagnostics' => 'Molecular Diagnostics',
            'cf_person_personalised_medicine' => 'Personalised medicine',
            'cf_person_screening_sequencing' => 'Screening sequencing',
            'cf_person_stem_cells' => 'Stem cells',
            'cf_person_targets' => 'Targets',
            'cf_person_translational_science' => 'Translational science',
            'cf_person_protein_production' => 'Protein Production',
        ];
    }
}

// Admin logging helper — only write when plugin debug_mode option is enabled
if (!function_exists('dtr_admin_log')) {
    function dtr_admin_log($message, $filename = 'connection-debug.log') {
        // Load options; default to false
        $options = get_option('dtr_workbooks_options', []);
        $debug_mode = !empty($options['debug_mode']);

        if (!$debug_mode) {
            return; // gated: do not write logs in production unless debug_mode is true
        }

    // Write admin debug logs into central logs directory now (was admin/ before consolidation)
    $log_dir = DTR_WORKBOOKS_PLUGIN_DIR . 'logs/';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

    $logfile = $log_dir . $filename;
        if (defined('DTR_WORKBOOKS_LOG_DIR')) {
            $log_file = DTR_WORKBOOKS_LOG_DIR . basename($logfile);
            @file_put_contents($log_file, $message, FILE_APPEND);
        } else {
            @file_put_contents($logfile, $message, FILE_APPEND);
        }
    }
}

// Main TOI to AOI mapping function
// Provide matrix separately for reuse (JS injection, unit tests, refactors)
if (!function_exists('dtr_get_toi_to_aoi_matrix')) {
    function dtr_get_toi_to_aoi_matrix() {
        return [
            'cf_person_business' => [
                'cf_person_analysis' => 1,
                'cf_person_biomarkers' => 1,
                'cf_person_clinical_trials' => 1,
                'cf_person_genomics' => 1,
                'cf_person_toxicology' => 1,
                'cf_person_artificial_intelligencemachine_learning' => 1,
                'cf_person_cell_gene_therapy' => 1,
                'cf_person_informatics' => 1,
                'cf_person_lab_automation' => 1,
                'cf_person_molecular_diagnostics' => 1,
                'cf_person_personalised_medicine' => 1,
                'cf_person_translational_science' => 1,
            ],
            'cf_person_diseases' => [
                'cf_person_biomarkers' => 1,
                'cf_person_genomics' => 1,
                'cf_person_cell_gene_therapy' => 1,
                'cf_person_molecular_diagnostics' => 1,
                'cf_person_personalised_medicine' => 1,
                'cf_person_stem_cells' => 1,
            ],
            'cf_person_drugs_therapies' => [
                'cf_person_biomarkers' => 1,
                'cf_person_clinical_trials' => 1,
                'cf_person_preclinical_research' => 1,
                'cf_person_drug_discovery_processes' => 1,
                'cf_person_hit_to_lead' => 1,
                'cf_person_toxicology' => 1,
                'cf_person_cell_gene_therapy' => 1,
                'cf_person_personalised_medicine' => 1,
                'cf_person_screening_sequencing' => 1,
                'cf_person_stem_cells' => 1,
                'cf_person_targets' => 1,
                'cf_person_translational_science' => 1,
            ],
            'cf_person_genomics_3774' => [
                'cf_person_biomarkers' => 1,
                'cf_person_genomics' => 1,
            ],
            'cf_person_research_development' => [
                'cf_person_analysis' => 1,
                'cf_person_assays' => 1,
                'cf_person_biomarkers' => 1,
                'cf_person_clinical_trials' => 1,
                'cf_person_preclinical_research' => 1,
                'cf_person_drug_discovery_processes' => 1,
                'cf_person_genomics' => 1,
                'cf_person_hit_to_lead' => 1,
                'cf_person_imaging' => 1,
                'cf_person_informatics' => 1,
                'cf_person_toxicology' => 1,
                'cf_person_cell_gene_therapy' => 1,
                'cf_person_molecular_diagnostics' => 1,
                'cf_person_personalised_medicine' => 1,
                'cf_person_screening_sequencing' => 1,
                'cf_person_stem_cells' => 1,
                'cf_person_targets' => 1,
                'cf_person_translational_science' => 1,
            ],
            'cf_person_technology' => [
                'cf_person_analysis' => 1,
                'cf_person_assays' => 1,
                'cf_person_imaging' => 1,
                'cf_person_artificial_intelligencemachine_learning' => 1,
                'cf_person_informatics' => 1,
                'cf_person_lab_automation' => 1,
                'cf_person_molecular_diagnostics' => 1,
            ],
            'cf_person_tools_techniques' => [
                'cf_person_analysis' => 1,
                'cf_person_assays' => 1,
                'cf_person_imaging' => 1,
                'cf_person_artificial_intelligencemachine_learning' => 1,
                'cf_person_informatics' => 1,
                'cf_person_lab_automation' => 1,
                'cf_person_molecular_diagnostics' => 1,
                'cf_person_screening_sequencing' => 1,
            ],
        ];
    }
}

// Normalise any legacy/alternate TOI keys to canonical ones used in matrix
if (!function_exists('dtr_normalize_toi_key')) {
    function dtr_normalize_toi_key($key) {
        static $aliases = [
            'cf_person_drugs_and_therapies' => 'cf_person_drugs_therapies',
            'cf_person_drug_therapies' => 'cf_person_drugs_therapies'
        ];
        return $aliases[$key] ?? $key;
    }
}

if (!function_exists('dtr_map_toi_to_aoi')) {
    function dtr_map_toi_to_aoi($selected_toi_fields) {
        if (empty($selected_toi_fields) || !is_array($selected_toi_fields)) {
            return [];
        }
        $matrix = dtr_get_toi_to_aoi_matrix();
        $aoi_fields = array_keys(dtr_get_aoi_field_names());
        $aoi_mapping = array_fill_keys($aoi_fields, 0);
        foreach ($selected_toi_fields as $toi_field) {
            if (isset($matrix[$toi_field])) {
                foreach ($matrix[$toi_field] as $aoi_field => $value) {
                    // Use logical OR: if any TOI maps to this AOI, set to 1
                    if ($value) $aoi_mapping[$aoi_field] = 1;
                }
            }
        }
        return $aoi_mapping;
    }
}

// Clean up user AOI fields to ensure they only come from TOI mappings
if (!function_exists('dtr_cleanup_user_aoi_fields')) {
    function dtr_cleanup_user_aoi_fields($user_id) {
        if (!$user_id) return false;
        
        // Get all TOI fields that are currently selected
        $toi_options = dtr_get_all_toi_options();
        $selected_tois = [];
        
        foreach ($toi_options as $toi_field => $label) {
            $value = get_user_meta($user_id, $toi_field, true);
            if ($value == '1') {
                $selected_tois[] = $toi_field;
            }
        }
        
        // Calculate what AOI fields should be based on TOI selections
        $calculated_aoi_mapping = dtr_map_toi_to_aoi($selected_tois);
        
        // Get all AOI fields
        $aoi_fields = array_keys(dtr_get_aoi_field_names());
        
        // Set all AOI fields based on calculated mapping (clear direct selections)
        $changes_made = [];
        foreach ($aoi_fields as $aoi_field) {
            $should_be_selected = isset($calculated_aoi_mapping[$aoi_field]) && $calculated_aoi_mapping[$aoi_field] == 1;
            $current_value = get_user_meta($user_id, $aoi_field, true);
            $new_value = $should_be_selected ? '1' : '0';
            
            if ($current_value !== $new_value) {
                update_user_meta($user_id, $aoi_field, $new_value);
                $changes_made[] = [
                    'field' => $aoi_field,
                    'old_value' => $current_value,
                    'new_value' => $new_value,
                    'reason' => $should_be_selected ? 'Set by TOI mapping' : 'Cleared direct selection'
                ];
            }
        }
        
        // Log changes for debugging
        if (!empty($changes_made)) {
            $log_message = sprintf(
                "[%s] AOI Cleanup for User %d:\nSelected TOIs: %s\nChanges made:\n",
                date('Y-m-d H:i:s'),
                $user_id,
                implode(', ', $selected_tois)
            );
            
            foreach ($changes_made as $change) {
                $log_message .= sprintf(
                    "  - %s: %s → %s (%s)\n",
                    $change['field'],
                    $change['old_value'] ?: 'empty',
                    $change['new_value'],
                    $change['reason']
                );
            }
            
            dtr_admin_log($log_message, 'aoi-cleanup.log');
        }
        
        return $changes_made;
    }
}

// Automatically clean up AOI fields when user profile is saved
add_action('profile_update', 'dtr_auto_cleanup_aoi_on_profile_save');
add_action('user_register', 'dtr_auto_cleanup_aoi_on_profile_save');

if (!function_exists('dtr_auto_cleanup_aoi_on_profile_save')) {
    function dtr_auto_cleanup_aoi_on_profile_save($user_id) {
        // Only run cleanup if we're updating TOI/AOI related fields
        if (isset($_POST['cf_person_business']) || 
            isset($_POST['cf_person_diseases']) || 
            isset($_POST['cf_person_drugs_therapies']) || 
            isset($_POST['cf_person_genomics_3774']) || 
            isset($_POST['cf_person_research_development']) || 
            isset($_POST['cf_person_technology']) || 
            isset($_POST['cf_person_tools_techniques'])) {
            
            dtr_cleanup_user_aoi_fields($user_id);
        }
    }
}

// Manual cleanup function for immediate use
if (!function_exists('dtr_manual_cleanup_user_aoi')) {
    function dtr_manual_cleanup_user_aoi($user_id = null) {
        // If no user ID provided, use current user
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return ['error' => 'No user ID provided'];
        }
        
        $changes = dtr_cleanup_user_aoi_fields($user_id);
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'changes_made' => count($changes),
            'details' => $changes
        ];
    }
}

// Convert Ninja Forms country codes to full names before submission
add_filter( 'ninja_forms_submit_data', function( $form_data ) {

    $target_form_id = 15; // Replace with your form ID
    $target_field_key = 'nf-field-148'; // Replace with your country field key

    if ( intval( $form_data['id'] ) !== $target_form_id ) {
        return $form_data;
    }

    foreach ( $form_data['fields'] as &$field ) {
        if ( isset( $field['key'] ) && $field['key'] === $target_field_key ) {
            // Convert ISO code to full country name
            $field['value'] = dtr_convert_country_code_to_name( $field['value'] );
        }
    }

    return $form_data;
});


/**
 * Get a configured Workbooks instance
 * 
 * @return Workbooks_API|false
 */
if (!function_exists('get_workbooks_instance')) {
    function get_workbooks_instance() {
        try {
            // Get plugin options
            $options = get_option('dtr_workbooks_options', []);
            $api_url = $options['api_url'] ?? '';
            $api_key = $options['api_key'] ?? '';
            
            if (empty($api_url) || empty($api_key)) {
                throw new Exception('API URL or API Key not configured');
            }
            
            // Check if we're in a development environment
            $is_development = (strpos($_SERVER['HTTP_HOST'], '.local') !== false || 
                             strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
                             strpos($_SERVER['HTTP_HOST'], '.test') !== false);
            
            // Required parameters for API connection
            $params = array(
                'application_name' => 'DTR_Workbooks_WordPress_Integration',  // No spaces in application name
                'user_agent' => 'DTR-WordPress-Integration/2.0.0',
                'api_key' => $api_key,
                'api_url' => rtrim($api_url, '/'),  // Ensure no trailing slash
                'verify_peer' => !$is_development,  // Disable SSL verification in development
                'debug' => true  // Enable debug mode
            );
            
            // Log connection attempts to debug file
            $today = date('Y-m-d');
            $debug_log = DTR_WORKBOOKS_PLUGIN_DIR . 'logs/dtr-workbooks-' . $today . '.log';
            
            // Create logs directory if it doesn't exist
            if (!file_exists(dirname($debug_log))) {
                wp_mkdir_p(dirname($debug_log));
            }
            
            // Log attempt with detailed information
            $debug_entry = sprintf(
                "[%s] [debug] Attempting Workbooks connection:\nURL: %s\nApp Name: %s\nUser Agent: %s\n",
                date('Y-m-d H:i:s'),
                $params['api_url'],
                $params['application_name'],
                $params['user_agent']
            );
            if (defined('DTR_WORKBOOKS_LOG_DIR')) {
                $log_file = DTR_WORKBOOKS_LOG_DIR . basename($debug_log);
                file_put_contents($log_file, $debug_entry, FILE_APPEND);
            } else {
                file_put_contents($debug_log, $debug_entry, FILE_APPEND);
            }
            
            // Log diagnostic information (gated by debug_mode)
            dtr_admin_log(date('[Y-m-d H:i:s]') . " Initializing Workbooks API with parameters:\n", 'connection-debug.log');
            dtr_admin_log(date('[Y-m-d H:i:s]') . " - Application Name: {$params['application_name']}\n", 'connection-debug.log');
            dtr_admin_log(date('[Y-m-d H:i:s]') . " - User Agent: {$params['user_agent']}\n", 'connection-debug.log');
            dtr_admin_log(date('[Y-m-d H:i:s]') . " - API URL: {$params['api_url']}\n", 'connection-debug.log');
            dtr_admin_log(date('[Y-m-d H:i:s]') . " - Verify Peer: " . ($params['verify_peer'] ? 'true' : 'false') . "\n", 'connection-debug.log');
            
            // Initialize Workbooks API with required parameters
            try {
                $workbooks = new Workbooks_API($params);
                dtr_admin_log(date('[Y-m-d H:i:s]') . " Workbooks API object created successfully\n", 'connection-debug.log');

                // Attempt login
                dtr_admin_log(date('[Y-m-d H:i:s]') . " Attempting login...\n", 'connection-debug.log');
                $login_response = $workbooks->login();

                // Log login response
                dtr_admin_log(date('[Y-m-d H:i:s]') . " Login response: " . print_r($login_response, true) . "\n", 'connection-debug.log');
                
                if ($login_response['success']) {
                    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
                        $log_file = DTR_WORKBOOKS_LOG_DIR . basename($debug_log);
                        file_put_contents($log_file, date('[Y-m-d H:i:s]') . " Login successful\n", FILE_APPEND);
                    } else {
                        file_put_contents($debug_log, date('[Y-m-d H:i:s]') . " Login successful\n", FILE_APPEND);
                    }
                    return $workbooks;
                } else {
                    throw new Exception('Workbooks login failed: ' . ($login_response['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                dtr_admin_log(date('[Y-m-d H:i:s]') . " Exception during API initialization: " . $e->getMessage() . "\n", 'connection-debug.log');
                throw $e;
            }
            
        } catch (Exception $e) {
            // Log detailed error information
            $error_message = sprintf(
                "[%s] [error] Workbooks connection error:\nMessage: %s\nFile: %s\nLine: %d\nTrace:\n%s\n",
                date('Y-m-d H:i:s'),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            
            // Log to both custom log and debug log (gated)
            dtr_custom_log($error_message, 'error');
            dtr_admin_log($error_message, 'connection-debug.log');
            
            return false;
        }
    }
}

// Dynamically populate the Title dropdown in Ninja Forms
add_filter('ninja_forms_render_options', function($options, $field) {
    // Check if the field is the Title dropdown (adjust field key or ID as needed)
    if (isset($field['key']) && $field['key'] === 'nf-field-141') {
        $titles = workbooks_crm_get_personal_titles();
        $options = [];
        foreach ($titles as $key => $value) {
            $options[] = [
                'label' => $value,
                'value' => $key
            ];
        }
    }
    return $options;
}, 10, 2);

/**
 * ===================================================================
 * GRAVITY FORMS DYNAMIC POPULATION FUNCTIONS
 * Functions to populate Gravity Forms dropdowns and checkboxes
 * Based on sliding-form-layout.html structure
 * ===================================================================
 */

/**
 * Personal Titles for Gravity Forms
 * Matches the sliding form dropdown options
 */
if (!function_exists('dtr_get_gravity_personal_titles')) {
    function dtr_get_gravity_personal_titles() {
        return [
            'Dr.' => 'Dr.',
            'Mr.' => 'Mr.',
            'Mrs.' => 'Mrs.',
            'Master' => 'Master',
            'Miss' => 'Miss',
            'Ms.' => 'Ms.',
            'Prof.' => 'Prof.'
        ];
    }
}

/**
 * Marketing Preferences for Gravity Forms
 * With title and description for each option
 * Matches the sliding form Step 2 structure
 */
if (!function_exists('dtr_get_gravity_marketing_options')) {
    function dtr_get_gravity_marketing_options() {
        return [
            'cf_person_dtr_news' => [
                'title' => 'Newsletter:',
                'description' => 'News, articles and analysis by email',
                'label' => 'Newsletter: News, articles and analysis by email'
            ],
            'cf_person_dtr_events' => [
                'title' => 'Event:',
                'description' => 'Information about events by email',
                'label' => 'Event: Information about events by email'
            ],
            'cf_person_dtr_third_party' => [
                'title' => 'Third party:',
                'description' => 'Application notes, product developments and updates from our trusted partners by email',
                'label' => 'Third party: Application notes, product developments and updates from our trusted partners by email'
            ],
            'cf_person_dtr_webinar' => [
                'title' => 'Webinar:',
                'description' => 'Information about webinars by email',
                'label' => 'Webinar: Information about webinars by email'
            ]
        ];
    }
}

/**
 * Topics of Interest for Gravity Forms
 * Matches the sliding form Step 3 structure
 */
if (!function_exists('dtr_get_gravity_topics_options')) {
    function dtr_get_gravity_topics_options() {
        return [
            'cf_person_business' => 'Business',
            'cf_person_diseases' => 'Diseases',
            'cf_person_drugs_therapies' => 'Drugs & Therapies',
            'cf_person_genomics_3774' => 'Genomics',
            'cf_person_research_development' => 'Research & Development',
            'cf_person_technology' => 'Technology',
            'cf_person_tools_techniques' => 'Tools & Techniques'
        ];
    }
}

/**
 * ===================================================================
 * GRAVITY FORMS POPULATION HOOKS
 * These hooks will automatically populate your Gravity Forms fields
 * ===================================================================
 */

/**
 * Populate Title dropdown in Gravity Forms
 * Usage: Set field parameter name to 'populate_titles'
 */
add_filter('gform_pre_render', 'dtr_populate_gravity_titles');
add_filter('gform_pre_validation', 'dtr_populate_gravity_titles');
add_filter('gform_pre_submission_filter', 'dtr_populate_gravity_titles');
add_filter('gform_admin_pre_render', 'dtr_populate_gravity_titles');

if (!function_exists('dtr_populate_gravity_titles')) {
    function dtr_populate_gravity_titles($form) {
        foreach ($form['fields'] as &$field) {
            // Check if field has parameter to populate titles
            if ($field->type === 'select' && 
                isset($field->allowsPrepopulate) && $field->allowsPrepopulate && 
                isset($field->inputName) && $field->inputName === 'populate_titles') {
                
                $titles = dtr_get_gravity_personal_titles();
                $choices = [];
                
                // Add empty option
                $choices[] = array(
                    'text' => '- Select Title -',
                    'value' => ''
                );
                
                // Add title options
                foreach ($titles as $value => $label) {
                    $choices[] = array(
                        'text' => $label,
                        'value' => $value
                    );
                }
                
                $field->choices = $choices;
            }
        }
        return $form;
    }
}

/**
 * Populate Marketing Preferences checkboxes in Gravity Forms
 * Usage: Set field parameter name to 'populate_marketing'
 */
add_filter('gform_pre_render', 'dtr_populate_gravity_marketing');
add_filter('gform_pre_validation', 'dtr_populate_gravity_marketing');
add_filter('gform_pre_submission_filter', 'dtr_populate_gravity_marketing');
add_filter('gform_admin_pre_render', 'dtr_populate_gravity_marketing');

if (!function_exists('dtr_populate_gravity_marketing')) {
    function dtr_populate_gravity_marketing($form) {
        foreach ($form['fields'] as &$field) {
            // Check if field has parameter to populate marketing options
            if ($field->type === 'checkbox' && 
                isset($field->allowsPrepopulate) && $field->allowsPrepopulate && 
                isset($field->inputName) && $field->inputName === 'populate_marketing') {
                
                $marketing_options = dtr_get_gravity_marketing_options();
                $choices = [];
                
                // Add marketing preference options
                foreach ($marketing_options as $value => $option) {
                    $choices[] = array(
                        'text' => '<strong>' . $option['title'] . '</strong><br>' . $option['description'],
                        'value' => $value
                    );
                }
                
                $field->choices = $choices;
            }
        }
        return $form;
    }
}

/**
 * Populate Topics of Interest checkboxes in Gravity Forms
 * Usage: Set field parameter name to 'populate_topics'
 */
add_filter('gform_pre_render', 'dtr_populate_gravity_topics');
add_filter('gform_pre_validation', 'dtr_populate_gravity_topics');
add_filter('gform_pre_submission_filter', 'dtr_populate_gravity_topics');
add_filter('gform_admin_pre_render', 'dtr_populate_gravity_topics');

if (!function_exists('dtr_populate_gravity_topics')) {
    function dtr_populate_gravity_topics($form) {
        foreach ($form['fields'] as &$field) {
            // Check if field has parameter to populate topics
            if ($field->type === 'checkbox' && 
                isset($field->allowsPrepopulate) && $field->allowsPrepopulate && 
                isset($field->inputName) && $field->inputName === 'populate_topics') {
                
                $topics = dtr_get_gravity_topics_options();
                $choices = [];
                
                // Add topic options
                foreach ($topics as $value => $label) {
                    $choices[] = array(
                        'text' => $label,
                        'value' => $value
                    );
                }
                
                $field->choices = $choices;
            }
        }
        return $form;
    }
}

?>