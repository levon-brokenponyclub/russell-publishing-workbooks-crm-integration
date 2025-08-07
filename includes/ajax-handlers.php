<?php
// === Test Connection AJAX Handler ===
add_action('wp_ajax_workbooks_test_connection', 'workbooks_test_connection_callback');
function workbooks_test_connection_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    try {
        $workbooks = get_workbooks_instance();
        // Try a harmless API call (e.g., get current user info or API version)
        $response = $workbooks->assertGet('system/api_version.api', [ '_limit' => 1 ]);
        if (!empty($response['data'][0]['api_version'])) {
            wp_send_json_success('Connection successful! API Version: ' . esc_html($response['data'][0]['api_version']));
        } else {
            wp_send_json_error('API responded but version not found.');
        }
    } catch (Exception $e) {
        wp_send_json_error('Connection failed: ' . $e->getMessage());
    }
}

if (!defined('ABSPATH')) exit;

// === Webinar AJAX Handlers ===
add_action('wp_ajax_fetch_webinar_acf_data', 'fetch_webinar_acf_data_callback');
add_action('wp_ajax_nopriv_fetch_webinar_acf_data', 'fetch_webinar_acf_data_callback');
function fetch_webinar_acf_data_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $post_id = absint($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error('Invalid webinar selected.');
    }
    $webinar_fields = get_field('webinar_fields', $post_id);
    wp_send_json_success([
        'workbooks_reference' => $webinar_fields['workbook_reference'] ?? '',
        'campaign_reference' => $webinar_fields['campaign_reference'] ?? '',
    ]);
}

// === Fetch Workbooks Event Handler ===
add_action('wp_ajax_fetch_workbooks_event', 'fetch_workbooks_event_callback');
add_action('wp_ajax_nopriv_fetch_workbooks_event', 'fetch_workbooks_event_callback');
function fetch_workbooks_event_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $event_ref = sanitize_text_field($_POST['event_ref'] ?? '');
    if (!$event_ref) {
        wp_send_json_error('Missing event reference.');
    }
    $workbooks = get_workbooks_instance();
    $params = [
        '_limit' => 1,
        '_ff[]' => is_numeric($event_ref) ? 'id' : 'reference',
        '_ft[]' => 'eq',
        '_fc[]' => $event_ref,
    ];
    $response = $workbooks->assertGet('event/events.api', $params);
    if (!empty($response['data'][0])) {
        wp_send_json_success($response['data'][0]);
    } else {
        wp_send_json_error('Event not found.');
    }
}

// Webinar register handler (with nonce check)
add_action('wp_ajax_workbooks_webinar_register', 'workbooks_webinar_register_callback');
add_action('wp_ajax_nopriv_workbooks_webinar_register', 'workbooks_webinar_register_callback');

function workbooks_webinar_register_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $result = dtr_register_workbooks_webinar($_POST);
    if (isset($result['success'])) {
        wp_send_json_success($result['success']);
    } else {
        wp_send_json_error($result['error'] ?? 'Unknown error');
    }
}

// === Fetch employers (no nonce check, public) ===
add_action('wp_ajax_fetch_workbooks_employers', 'fetch_workbooks_employers_callback');
add_action('wp_ajax_nopriv_fetch_workbooks_employers', 'fetch_workbooks_employers_callback');

function fetch_workbooks_employers_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');

    global $wpdb;

    $table_name = $wpdb->prefix . 'workbooks_employers';

    $results = $wpdb->get_results("SELECT id, name FROM $table_name ORDER BY name ASC", ARRAY_A);

    if ($results) {
        wp_send_json_success($results);
    } else {
        wp_send_json_error('No employers found');
    }
}


function dtr_log_to_file($message) {
    $log_file = dirname(__DIR__) . '/logs/workbooks-2025-06-27.log';
    
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND);
}

function dtr_webinar_debug_log($level, $message, $data = []) {
    $log_file = dirname(__DIR__) . '/webinar-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] [$level] $message";
    if (!empty($data)) {
        $entry .= ' | ' . json_encode($data);
    }
    file_put_contents($log_file, $entry . PHP_EOL, FILE_APPEND);
}


function dtr_register_workbooks_webinar($data) {
    $webinar_id = absint($data['webinar_post_id'] ?? 0);
    $email = sanitize_email($data['participant_email'] ?? '');
    $speaker_question = sanitize_textarea_field($data['speaker_question'] ?? '');
    $sponsor_optin = isset($data['sponsor_optin']) && $data['sponsor_optin'] == '1' ? 1 : 0;
    dtr_webinar_debug_log('INFO', 'Webinar registration started', [
        'webinar_id' => $webinar_id,
        'email' => $email,
        'speaker_question' => $speaker_question,
        'sponsor_optin' => $sponsor_optin
    ]);
    if (!$webinar_id || empty($email)) {
        dtr_webinar_debug_log('ERROR', 'Webinar ID or email missing', [ 'webinar_id' => $webinar_id, 'email' => $email ]);
        return ['error' => 'Webinar selection and participant email are required.'];
    }
    $webinar_fields = get_field('webinar_fields', $webinar_id);
    $workbook_reference = $webinar_fields['workbook_reference'] ?? '';
    $campaign_reference = $webinar_fields['campaign_reference'] ?? '';
    dtr_webinar_debug_log('INFO', 'Webinar ACF fields', [ 'workbook_reference' => $workbook_reference, 'campaign_reference' => $campaign_reference ]);
    if (empty($workbook_reference) || !is_numeric($workbook_reference)) {
        dtr_webinar_debug_log('ERROR', 'Invalid or missing Workbooks Webinar Reference', [ 'webinar_id' => $webinar_id ]);
        return ['error' => 'Invalid or missing Workbooks Webinar Reference.'];
    }
    $user = wp_get_current_user();
    $name = trim($user->first_name . ' ' . $user->last_name);
    if (empty($name)) {
        $name = $email;
        dtr_webinar_debug_log('WARN', 'User name empty, using email as fallback', [ 'email' => $email ]);
    }
    $workbooks = get_workbooks_instance();

    // Find or create person in Workbooks
    $person_id = null;
    try {
        $search = $workbooks->assertGet('crm/people.api', [
            '_ff[]' => 'main_location[email]',
            '_ft[]' => 'eq',
            '_fc[]' => $email,
            '_limit' => 1,
            '_select_columns[]' => ['id'],
        ]);
        dtr_webinar_debug_log('INFO', 'Workbooks person search response', $search);
        if (!empty($search['data'][0]['id'])) {
            $person_id = $search['data'][0]['id'];
            dtr_webinar_debug_log('INFO', 'Found existing person in Workbooks', [ 'person_id' => $person_id ]);
        } else {
            $person_payload = [[
                'person_first_name' => $user->first_name ?: $name,
                'person_last_name' => $user->last_name ?: '',
                'main_location[email]' => $email,
            ]];
            dtr_webinar_debug_log('INFO', 'Creating new person payload', $person_payload);
            $create = $workbooks->assertCreate('crm/people.api', $person_payload);
            dtr_webinar_debug_log('INFO', 'Workbooks person create response', $create);
            if (!empty($create['affected_objects'][0]['id'])) {
                $person_id = $create['affected_objects'][0]['id'];
                dtr_webinar_debug_log('INFO', 'Created new person in Workbooks', [ 'person_id' => $person_id ]);
            } else {
                dtr_webinar_debug_log('ERROR', 'Failed to create person in Workbooks', $create);
                return ['error' => 'Could not create person in Workbooks.'];
            }
        }
    } catch (Exception $e) {
        dtr_webinar_debug_log('ERROR', 'Person search/create exception', [ 'exception' => $e->getMessage() ]);
        return ['error' => 'Exception creating/finding person: ' . $e->getMessage()];
    }

    try {
        $existing_ticket = $workbooks->assertGet('event/tickets.api', [
            '_limit' => 1,
            '_ff[]' => 'event_id',
            '_ft[]' => 'eq',
            '_fc[]' => (int)$workbook_reference,
            '_ff[]' => 'person_id',
            '_ft[]' => 'eq',
            '_fc[]' => $person_id,
            '_select_columns[]' => ['id', 'lock_version'],
        ]);
        dtr_webinar_debug_log('INFO', 'Workbooks ticket search params', [
            'event_id' => (int)$workbook_reference,
            'person_id' => $person_id
        ]);
        dtr_webinar_debug_log('INFO', 'Workbooks ticket search response', $existing_ticket);
        $ticket_id = $existing_ticket['data'][0]['id'] ?? null;
        $lock_version = $existing_ticket['data'][0]['lock_version'] ?? null;
        
        if ($ticket_id) {
            dtr_webinar_debug_log('INFO', 'Found existing ticket for this person/event', ['ticket_id' => $ticket_id, 'lock_version' => $lock_version]);
        } else {
            dtr_webinar_debug_log('INFO', 'No existing ticket found - will create new one');
        }

        $ticket_payload = [[
            'event_id' => (int)$workbook_reference,
            'person_id' => $person_id,
            'name' => $name,
            'status' => 'Registered',
        ]];
        dtr_webinar_debug_log('INFO', 'Base ticket payload created', $ticket_payload);
        
        // Add speaker question if provided
        if (!empty($speaker_question)) {
            // Try simpler field name first
            $ticket_payload[0]['comment'] = $speaker_question;
            dtr_webinar_debug_log('INFO', 'Added speaker question to ticket', ['speaker_question' => $speaker_question]);
        }
        
        // Test with basic fields only - comment out custom fields temporarily
        // $ticket_payload[0]['cf_event_ticket_sponsor_optin'] = $sponsor_optin;
        // $ticket_payload[0]['sponsor_optin'] = $sponsor_optin;
        dtr_webinar_debug_log('INFO', 'Temporarily disabled custom fields for testing');
        
        dtr_webinar_debug_log('INFO', 'Ticket payload', $ticket_payload);
        if ($ticket_id && $lock_version) {
            $ticket_payload[0]['id'] = $ticket_id;
            $ticket_payload[0]['lock_version'] = $lock_version;
            dtr_webinar_debug_log('INFO', 'Updating existing ticket', [ 'ticket_id' => $ticket_id ]);
            try {
                // Try using the regular update method instead of assertUpdate to get better error info
                $response = $workbooks->update('event/tickets.api', $ticket_payload);
                dtr_webinar_debug_log('INFO', 'Raw update response received', $response);
                
                // Check if the API returned an error (like Sales Order association)
                if (isset($response['success']) && $response['success'] === false) {
                    dtr_webinar_debug_log('INFO', 'Update returned error, attempting to create new ticket instead');
                    // Remove ID and lock_version to create a new ticket
                    unset($ticket_payload[0]['id']);
                    unset($ticket_payload[0]['lock_version']);
                    $response = $workbooks->create('event/tickets.api', $ticket_payload);
                    dtr_webinar_debug_log('INFO', 'Fallback create response', $response);
                }
            } catch (Exception $e) {
                dtr_webinar_debug_log('ERROR', 'Update ticket exception details', [ 
                    'exception' => $e->getMessage(),
                    'ticket_payload' => $ticket_payload 
                ]);
                // If update fails, try creating a new ticket instead
                dtr_webinar_debug_log('INFO', 'Update failed, attempting to create new ticket instead');
                unset($ticket_payload[0]['id']);
                unset($ticket_payload[0]['lock_version']);
                $response = $workbooks->create('event/tickets.api', $ticket_payload);
                dtr_webinar_debug_log('INFO', 'Fallback create response', $response);
            }
        } else {
            dtr_webinar_debug_log('INFO', 'Creating new ticket');
            try {
                $response = $workbooks->create('event/tickets.api', $ticket_payload);
                dtr_webinar_debug_log('INFO', 'Create response received', $response);
            } catch (Exception $e) {
                dtr_webinar_debug_log('ERROR', 'Create ticket exception details', [ 
                    'exception' => $e->getMessage(),
                    'ticket_payload' => $ticket_payload 
                ]);
                throw $e;
            }
        }
        dtr_webinar_debug_log('INFO', 'Workbooks ticket API response', $response);
        
        // Check what fields were actually updated
        if (isset($response['affected_objects'][0])) {
            dtr_webinar_debug_log('INFO', 'Fields actually updated in ticket', $response['affected_objects'][0]);
        }
        
        // Handle both success and error responses
        if (isset($response['success']) && $response['success'] === false) {
            dtr_webinar_debug_log('ERROR', 'Workbooks API returned error', $response);
            return ['error' => 'Workbooks API error: ' . json_encode($response)];
        }
        
        $final_ticket_id = $response['affected_objects'][0]['id'] ?? null;
        if ($final_ticket_id) {
            dtr_webinar_debug_log('SUCCESS', 'Webinar registration submitted', [ 'ticket_id' => $final_ticket_id ]);
            return ['success' => 'Webinar registration submitted successfully. Ticket ID: ' . $final_ticket_id];
        } else {
            dtr_webinar_debug_log('ERROR', 'No ticket ID in response', $response);
            return ['error' => 'Unexpected ticket response: ' . json_encode($response)];
        }
    } catch (Exception $e) {
        dtr_webinar_debug_log('ERROR', 'Webinar registration exception', [ 'exception' => $e->getMessage() ]);
        return ['error' => 'Exception: ' . $e->getMessage()];
    }
}

/**
 * Register a lead for a restricted content piece in Workbooks CRM
 * Called directly from ninja-forms-simple-hook.php
 */
function dtr_register_workbooks_lead($registration_data) {
    // Debug logging function
    $debug_log_file = WP_CONTENT_DIR . '/plugins/dtr-workbooks-crm-integration/simple-webinar-debug.log';
    $log_function = function($message) use ($debug_log_file) {
        error_log("[" . date('Y-m-d H:i:s') . "] LEAD-REG: $message\n", 3, $debug_log_file);
    };
    
    $log_function("=== DTR REGISTER WORKBOOKS LEAD ===");
    
    // Validate required data
    if (empty($registration_data['email_address'])) {
        $log_function("❌ ERROR: Missing email address");
        return false;
    }
    
    $participant_email = sanitize_email($registration_data['email_address']);
    $first_name = sanitize_text_field($registration_data['first_name'] ?? '');
    $last_name = sanitize_text_field($registration_data['last_name'] ?? '');
    $post_title = sanitize_text_field($registration_data['post_title'] ?? '');
    $event_id = sanitize_text_field($registration_data['campaign_id'] ?? ''); // This is actually event ID
    $event_reference = sanitize_text_field($registration_data['campaign_reference'] ?? ''); // This is actually event reference
    
    $log_function("📧 Processing lead registration for: $participant_email");
    $log_function("📝 Content: $post_title");
    $log_function("🎯 Event ID: $event_id");
    $log_function("🎯 Event Reference: $event_reference");
    
    // Check if get_workbooks_instance function exists
    if (!function_exists('get_workbooks_instance')) {
        $log_function("❌ ERROR: get_workbooks_instance function not found");
        return false;
    }
    
    $log_function("✅ get_workbooks_instance function is available");
    
    // Check the API credentials first
    $api_url = get_option('workbooks_api_url');
    $api_key = get_option('workbooks_api_key');
    $log_function("🔧 API URL from options: " . ($api_url ?: 'NOT SET'));
    $log_function("🔧 API Key from options: " . ($api_key ? 'SET (' . substr($api_key, 0, 10) . '...)' : 'NOT SET'));
    
    // Use Workbooks API instance like the webinar registration function
    $workbooks = get_workbooks_instance();
    if (!$workbooks) {
        $log_function("❌ ERROR: Workbooks API instance not available");
        $log_function("🔧 Checking individual components...");
        
        // Test if the WorkbooksApi class exists
        if (!class_exists('WorkbooksApi')) {
            $log_function("❌ WorkbooksApi class not found");
        } else {
            $log_function("✅ WorkbooksApi class exists");
        }
        
        return false;
    }
    
    $log_function("✅ Workbooks API instance available");
    
    // Create full name
    $full_name = trim($first_name . ' ' . $last_name);
    if (empty($full_name)) {
        $full_name = $participant_email; // fallback to email
    }
    
    try {
        // Find or create person in Workbooks (same logic as webinar registration)
        $person_id = null;
        $search = $workbooks->assertGet('crm/people.api', [
            '_ff[]' => 'main_location[email]',
            '_ft[]' => 'eq',
            '_fc[]' => $participant_email,
            '_limit' => 1,
            '_select_columns[]' => ['id'],
        ]);
        
        $log_function("🔍 Person search response: " . json_encode($search));
        
        if (!empty($search['data'][0]['id'])) {
            $person_id = $search['data'][0]['id'];
            $log_function("✅ Found existing person in Workbooks: $person_id");
        } else {
            // Create new person
            $person_payload = [[
                'person_first_name' => $first_name,
                'person_last_name' => $last_name,
                'name' => $full_name,
                'main_location[email]' => $participant_email,
                'cf_person_dtr_subscriber_type' => 'Prospect',
                'cf_person_dtr_web_member' => 1,
                'lead_source_type' => 'Lead Generation Form',
                'cf_person_is_person_active_or_inactive' => 'Active',
                'cf_person_data_source_detail' => 'DTR Lead Generation Form',
                'created_through_reference' => 'dtr_lead_' . time(),
            ]];
            
            $log_function("👤 Creating new person: " . json_encode($person_payload));
            $create = $workbooks->assertCreate('crm/people.api', $person_payload);
            $log_function("👤 Person create response: " . json_encode($create));
            
            if (!empty($create['affected_objects'][0]['id'])) {
                $person_id = $create['affected_objects'][0]['id'];
                $log_function("✅ Created new person in Workbooks: $person_id");
            } else {
                $log_function("❌ ERROR: Failed to create person in Workbooks");
                return false;
            }
        }
        
        // For lead generation campaigns, do NOT create or check event tickets. Only create the person record.
        $log_function("ℹ️  Lead generation campaign: no event ticket will be created or checked");
        
        // Store the registration in WordPress options for tracking
        $registration_meta = array(
            'email' => $participant_email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'content_title' => $post_title,
            'event_id' => $event_id,
            'event_reference' => $event_reference,
            'person_id' => $person_id,
            'registration_date' => current_time('mysql'),
            'registration_type' => 'lead_generation'
        );
        
        $registration_key = 'dtr_lead_registration_' . md5($participant_email . $event_id . time());
        update_option($registration_key, $registration_meta);
        
        $log_function("💾 Stored registration data with key: $registration_key");
        $log_function("🎉 Lead generation registration completed successfully!");
        
        return array(
            'success' => true,
            'person_id' => $person_id,
            'message' => 'Lead registered successfully in Workbooks CRM'
        );
        
    } catch (Exception $e) {
        $log_function("❌ ERROR: Exception during Workbooks API calls - " . $e->getMessage());
        return false;
    }
}
