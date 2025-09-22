<?php
/**
 * Media Planner Form Handler
 * Handles form submissions for media planner downloads
 * Creates leads and event tickets in Workbooks CRM
 * Event ID: 5137 (EVENT-2571) - DTR Media Planner 2025
 */
if (!defined('ABSPATH')) exit;

/* --------------------------------------------------------------------------
 * Ensure required dependencies are loaded
 * -------------------------------------------------------------------------- */
// Load Workbooks API library
if (!class_exists('WorkbooksApi')) {
    require_once DTR_WORKBOOKS_PLUGIN_DIR . 'lib/workbooks_api.php';
}

// Load helper functions if not already available
if (!function_exists('get_workbooks_instance')) {
    require_once DTR_WORKBOOKS_INCLUDES_DIR . 'class-helper-functions.php';
}

/* --------------------------------------------------------------------------
 * Logging
 * -------------------------------------------------------------------------- */
function dtr_media_planner_log($msg) {
    $timestamp = current_time('Y-m-d H:i:s');
    $line = "{$timestamp} [Media-Planner] {$msg}\n";
    
    // Log to WordPress error log
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($line);
    }
    
    // Log to specific file
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $file = DTR_WORKBOOKS_LOG_DIR . 'media-planner-debug.log';
        if (!file_exists(dirname($file))) {
            wp_mkdir_p(dirname($file));
        }
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

// Initialize logging - emit boot message
dtr_media_planner_log('[BOOT] Media planner form handler loaded');

/**
 * Get a clean Workbooks instance without stdout logging for AJAX requests
 */
function dtr_get_clean_workbooks_instance() {
    try {
        $options = get_option('dtr_workbooks_options', []);
        $api_url = $options['api_url'] ?? '';
        $api_key = $options['api_key'] ?? '';
        
        if (empty($api_url) || empty($api_key)) {
            return false;
        }

        // Check if WorkbooksApi class exists
        if (!class_exists('WorkbooksApi')) {
            return false;
        }
        
        // Initialize Workbooks API without the problematic stdout logger
        $workbooks = new WorkbooksApi([
            'application_name' => 'DTR Workbooks Integration',
            'user_agent' => 'DTR-WordPress-Plugin/2.0.0',
            'service' => rtrim($api_url, '/'),
            'api_key' => $api_key,
            'verify_peer' => true,
            'connect_timeout' => $options['api_timeout'] ?? 30,
            'request_timeout' => $options['api_timeout'] ?? 30,
            // Do NOT set logger_callback to avoid stdout contamination during AJAX
        ]);
        
        return $workbooks;
        
    } catch (Exception $e) {
        return false;
    }
}

/* --------------------------------------------------------------------------
 * AJAX Handler Registration
 * -------------------------------------------------------------------------- */
add_action('wp_ajax_dtr_media_planner_form_submit', 'dtr_handle_media_planner_form_submit');
add_action('wp_ajax_nopriv_dtr_media_planner_form_submit', 'dtr_handle_media_planner_form_submit');

/**
 * Handle media planner form submission
 */
function dtr_handle_media_planner_form_submit() {
    dtr_media_planner_log('=== MEDIA PLANNER FORM SUBMISSION START ===');
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'dtr_html_form_submit')) {
        dtr_media_planner_log('SECURITY: Invalid nonce provided');
        wp_send_json_error(['message' => 'Security verification failed']);
        return;
    }

    // Collect form data
    $form_data = [
        'firstName' => sanitize_text_field($_POST['firstName'] ?? ''),
        'lastName' => sanitize_text_field($_POST['lastName'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'jobTitle' => sanitize_text_field($_POST['jobTitle'] ?? ''),
        'organisation' => sanitize_text_field($_POST['organisation'] ?? ''),
        'city' => sanitize_text_field($_POST['city'] ?? ''),
        'country' => sanitize_text_field($_POST['country'] ?? ''),
        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        'canWeHelpFurther' => sanitize_text_field($_POST['canWeHelpFurther'] ?? ''),
        'consent' => sanitize_text_field($_POST['consent'] ?? ''),
    ];

    // Collect hidden fields
    $hidden_data = [
        'event_id' => sanitize_text_field($_POST['event_id'] ?? '5137'),
        'data_source_detail' => sanitize_text_field($_POST['data_source_detail'] ?? 'DTR-MEDIA-PLANNER-2025'),
        'download_name' => sanitize_text_field($_POST['download_name'] ?? 'DTR-MEDIA-PLANNER-2025'),
        'type' => sanitize_text_field($_POST['type'] ?? 'Event Registration'),
        'lead_source_type' => sanitize_text_field($_POST['lead_source_type'] ?? 'Event Registration'),
        'cf_customer_order_brand_for_pdf' => sanitize_text_field($_POST['cf_customer_order_brand_for_pdf'] ?? 'Drug Target Review'),
        'campaign_name' => sanitize_text_field($_POST['campaign_name'] ?? 'Media Planner 2025'),
        'cf_customer_order_line_item_brand' => sanitize_text_field($_POST['cf_customer_order_line_item_brand'] ?? 'DTR'),
        'cf_customer_order_line_item_rp_product_delegate' => sanitize_text_field($_POST['cf_customer_order_line_item_rp_product_delegate'] ?? 'Media Planner 2025'),
        'cf_customer_order_line_item_subproduct_event' => sanitize_text_field($_POST['cf_customer_order_line_item_subproduct_event'] ?? 'FOC'),
        'cf_customer_order_line_item_streams' => sanitize_text_field($_POST['cf_customer_order_line_item_streams'] ?? 'N/A'),
        'cf_customer_order_line_item_campaign_delegate' => sanitize_text_field($_POST['cf_customer_order_line_item_campaign_delegate'] ?? 'Media Planner 2025'),
        'cf_customer_order_line_item_campaign_reference_2' => sanitize_text_field($_POST['cf_customer_order_line_item_campaign_reference_2'] ?? 'CAMP-41496'),
        'cf_customer_order_line_item_delegate_type' => sanitize_text_field($_POST['cf_customer_order_line_item_delegate_type'] ?? 'Primary'),
        'cf_customer_order_line_item_delegate_type_608' => sanitize_text_field($_POST['cf_customer_order_line_item_delegate_type_608'] ?? 'Delegate'),
        'cf_customer_order_line_item_delegate_ticket_type' => sanitize_text_field($_POST['cf_customer_order_line_item_delegate_ticket_type'] ?? 'VIP'),
        'cf_customer_order_line_item_attended' => sanitize_text_field($_POST['cf_customer_order_line_item_attended'] ?? 'No'),
        'cf_customer_order_line_item_dinner' => sanitize_text_field($_POST['cf_customer_order_line_item_dinner'] ?? 'N/A'),
        'assigned_to' => sanitize_text_field($_POST['assigned_to'] ?? 'Unassigned'),
        'web_key' => sanitize_text_field($_POST['web_key'] ?? '663d4d9f011e521baf6fc92150976b453f3b0a72'),
        'success_url' => sanitize_url($_POST['success_url'] ?? 'https://www.drugtargetreview.com'),
        'failure_url' => sanitize_url($_POST['failure_url'] ?? 'https://www.drugtargetreview.com'),
        'sales_lead_rating' => sanitize_text_field($_POST['sales_lead_rating'] ?? 'Warm'),
        'lead_type' => sanitize_text_field($_POST['lead_type'] ?? 'Reader'),
        'dtr_subscriber_type' => sanitize_text_field($_POST['dtr_subscriber_type'] ?? 'Prospect'),
        'product_mix' => sanitize_text_field($_POST['product_mix'] ?? ''),
        'name1' => sanitize_text_field($_POST['name1'] ?? ''),
        'name2' => sanitize_text_field($_POST['name2'] ?? ''),
        'org_lead_party_email' => sanitize_email($_POST['org_lead_party_email'] ?? ''),
    ];

    dtr_media_planner_log('Form data collected: ' . json_encode($form_data));
    dtr_media_planner_log('Hidden data: ' . json_encode($hidden_data));

    // Validate required fields
    $required_fields = ['firstName', 'lastName', 'email', 'jobTitle', 'organisation', 'city', 'country', 'phone'];
    foreach ($required_fields as $field) {
        if (empty($form_data[$field])) {
            dtr_media_planner_log("VALIDATION ERROR: Missing required field: $field");
            wp_send_json_error(['message' => "Missing required field: $field"]);
            return;
        }
    }

    // Validate email
    if (!is_email($form_data['email'])) {
        dtr_media_planner_log('VALIDATION ERROR: Invalid email format');
        wp_send_json_error(['message' => 'Invalid email format']);
        return;
    }

    // Validate consent
    if ($form_data['consent'] !== '1') {
        dtr_media_planner_log('VALIDATION ERROR: Consent not given');
        wp_send_json_error(['message' => 'You must consent to data collection']);
        return;
    }

    try {
        // Get Workbooks instance with clean logging for AJAX
        $workbooks = dtr_get_clean_workbooks_instance();
        if (!$workbooks) {
            throw new Exception('Failed to get Workbooks instance');
        }

        dtr_media_planner_log('Workbooks instance obtained successfully');

        // Create or find person in Workbooks
        $person_result = dtr_create_or_find_person($workbooks, $form_data);
        if (!$person_result['success']) {
            throw new Exception('Failed to create/find person: ' . $person_result['message']);
        }

        $person_id = $person_result['person_id'];
        dtr_media_planner_log("Person created/found with ID: $person_id");

        // Create event registration (ticket) for EVENT-2571 (ID: 5137)
        $ticket_result = dtr_create_event_ticket($workbooks, $person_id, $hidden_data);
        if (!$ticket_result['success']) {
            throw new Exception('Failed to create event ticket: ' . $ticket_result['message']);
        }

        dtr_media_planner_log('Event ticket created successfully: ' . json_encode($ticket_result));

        // Success response
        wp_send_json_success([
            'message' => 'Media planner request processed successfully',
            'person_id' => $person_id,
            'ticket_id' => $ticket_result['ticket_id'],
            'download_url' => $hidden_data['success_url'] // You can customize this
        ]);

    } catch (Exception $e) {
        dtr_media_planner_log('ERROR: ' . $e->getMessage());
        dtr_media_planner_log('ERROR Trace: ' . $e->getTraceAsString());
        wp_send_json_error(['message' => 'Failed to process request: ' . $e->getMessage()]);
    }
}

/**
 * Create or find person in Workbooks
 */
function dtr_create_or_find_person($workbooks, $form_data) {
    dtr_media_planner_log('Creating/finding person in Workbooks');

    try {
        // First, try to find existing person by email
        $filter_limit_select = [
            '_filter_json' => json_encode([
                ['main_location[email]', 'eq', $form_data['email']]
            ]),
            '_limit' => 1
        ];

        $find_response = $workbooks->get('crm/people.api', $filter_limit_select);
        dtr_media_planner_log('Person search response: ' . json_encode($find_response));

        if (!$find_response['success'] || empty($find_response['data'])) {
            // Person doesn't exist, create new one
            dtr_media_planner_log('Person not found, creating new person');

            $person_data = [
                'name' => trim($form_data['firstName'] . ' ' . $form_data['lastName']),
                'person_first_name' => $form_data['firstName'],
                'person_last_name' => $form_data['lastName'],
                'main_location[email]' => $form_data['email'],
                'main_location[telephone]' => $form_data['phone'],
                'person_job_title' => $form_data['jobTitle'],
                'website' => '', // No website field in form
                'main_location[country]' => $form_data['country'],
                'main_location[town]' => $form_data['city'],
                'person_type' => 'Contact',
                'record_type' => 'Person',
                'lead_source_type' => 'Event Registration',
                'sales_lead_rating' => 'Warm',
                'cf_person_dtr_subscriber_type' => 'Prospect',
                'cf_person_lead_type' => 'Reader',
                'cf_person_data_source_detail' => 'DTR-MEDIA-PLANNER-2025',
            ];

            // Add organisation if provided
            if (!empty($form_data['organisation'])) {
                $person_data['employer_name'] = $form_data['organisation'];
                $person_data['cf_person_claimed_employer'] = $form_data['organisation'];
            }

            $create_response = $workbooks->create('crm/people.api', $person_data);
            dtr_media_planner_log('Person creation response: ' . json_encode($create_response));

            if (!$create_response['success']) {
                return ['success' => false, 'message' => 'Failed to create person'];
            }

            $person_id = $create_response['affected_objects'][0]['id'];
            dtr_media_planner_log("New person created with ID: $person_id");

        } else {
            // Person exists, use existing ID
            $person_id = $find_response['data'][0]['id'];
            dtr_media_planner_log("Existing person found with ID: $person_id");

            // Update person with any new information
            $update_data = [
                'id' => $person_id,
                'lock_version' => $find_response['data'][0]['lock_version'],
                'main_location[telephone]' => $form_data['phone'],
                'person_job_title' => $form_data['jobTitle'],
                'main_location[country]' => $form_data['country'],
                'main_location[town]' => $form_data['city'],
            ];

            if (!empty($form_data['organisation'])) {
                $update_data['employer_name'] = $form_data['organisation'];
                $update_data['cf_person_claimed_employer'] = $form_data['organisation'];
            }

            $update_response = $workbooks->update('crm/people.api', $update_data);
            dtr_media_planner_log('Person update response: ' . json_encode($update_response));
        }

        return ['success' => true, 'person_id' => $person_id];

    } catch (Exception $e) {
        dtr_media_planner_log('Error in person creation/finding: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Create event ticket for media planner download
 */
function dtr_create_event_ticket($workbooks, $person_id, $hidden_data) {
    dtr_media_planner_log("Creating event ticket for person ID: $person_id");

    try {
        $event_id = 5137; // EVENT-2571 - DTR Media Planner 2025
        
        $ticket_data = [
            'event_id' => $event_id,
            'person_id' => $person_id,
            'ticket_type' => 'Delegate',
            'ticket_status' => 'Registered',
            'registration_status' => 'Confirmed',
            'ticket_price' => 0.00, // Free
            'currency' => 'GBP',
            'attended' => 'No',
            'registration_method' => 'Website',
            'data_source_reference' => $hidden_data['data_source_detail'],
            'cf_customer_order_brand_for_pdf' => $hidden_data['cf_customer_order_brand_for_pdf'],
            'cf_customer_order_line_item_brand' => $hidden_data['cf_customer_order_line_item_brand'],
            'cf_customer_order_line_item_rp_product_delegate' => $hidden_data['cf_customer_order_line_item_rp_product_delegate'],
            'cf_customer_order_line_item_subproduct_event' => $hidden_data['cf_customer_order_line_item_subproduct_event'],
            'cf_customer_order_line_item_streams' => $hidden_data['cf_customer_order_line_item_streams'],
            'cf_customer_order_line_item_campaign_delegate' => $hidden_data['cf_customer_order_line_item_campaign_delegate'],
            'cf_customer_order_line_item_campaign_reference_2' => $hidden_data['cf_customer_order_line_item_campaign_reference_2'],
            'cf_customer_order_line_item_delegate_type' => $hidden_data['cf_customer_order_line_item_delegate_type'],
            'cf_customer_order_line_item_delegate_type_608' => $hidden_data['cf_customer_order_line_item_delegate_type_608'],
            'cf_customer_order_line_item_delegate_ticket_type' => $hidden_data['cf_customer_order_line_item_delegate_ticket_type'],
            'cf_customer_order_line_item_attended' => $hidden_data['cf_customer_order_line_item_attended'],
            'cf_customer_order_line_item_dinner' => $hidden_data['cf_customer_order_line_item_dinner'],
            'assigned_to_name' => $hidden_data['assigned_to'],
        ];

        dtr_media_planner_log('Creating ticket with data: ' . json_encode($ticket_data));

        $response = $workbooks->create('event/tickets.api', $ticket_data);
        dtr_media_planner_log('Event ticket creation response: ' . json_encode($response));

        if (!$response['success']) {
            return ['success' => false, 'message' => 'Failed to create event ticket'];
        }

        $ticket_id = $response['affected_objects'][0]['id'];
        dtr_media_planner_log("Event ticket created successfully with ID: $ticket_id");

        return [
            'success' => true,
            'ticket_id' => $ticket_id,
            'response' => $response
        ];

    } catch (Exception $e) {
        dtr_media_planner_log('Error creating event ticket: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}