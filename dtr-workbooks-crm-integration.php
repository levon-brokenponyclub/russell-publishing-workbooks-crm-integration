<?php
/**
 * Plugin Name: DTR - Workbooks CRM API Integration
 * Description: Connects WordPress to DTR Workbooks CRM
 * Version: 1.4.5
 * Author: Supersonic Playground
 * Author URI: https://www.supersonicplayground.com
 * Text Domain: dtr-workbooks-crm-integration
 */

if (!defined('ABSPATH')) exit;

// Debug: Plugin is loading
error_log('DTR Workbooks Plugin Loading - File: ' . __FILE__);
// Define plugin base path constant
if (!defined('WORKBOOKS_NF_PATH')) {
    define('WORKBOOKS_NF_PATH', plugin_dir_path(__FILE__));
}

// Include Workbooks API file safely
if (file_exists(WORKBOOKS_NF_PATH . 'lib/workbooks_api.php')) {
    require_once WORKBOOKS_NF_PATH . 'lib/workbooks_api.php';
} else {
    error_log('Workbooks API file not found at ' . WORKBOOKS_NF_PATH . 'lib/workbooks_api.php');
}

// Include helper functions first
if (file_exists(WORKBOOKS_NF_PATH . 'includes/helper-functions.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/helper-functions.php';
}

// Include user meta fields
if (file_exists(WORKBOOKS_NF_PATH . 'includes/dtr-shortcodes.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/dtr-shortcodes.php';
}

// Include user meta fields
if (file_exists(WORKBOOKS_NF_PATH . 'includes/user-meta-fields.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/user-meta-fields.php';
}

// Include employer sync functionality
if (file_exists(WORKBOOKS_NF_PATH . 'includes/workbooks-employer-sync.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/workbooks-employer-sync.php';
}

// Include Ninja Forms country converter
if (file_exists(WORKBOOKS_NF_PATH . 'includes/nf-country-converter.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/nf-country-converter.php';
}

// Include Ninja Forms Webinar Handlder
if (file_exists(WORKBOOKS_NF_PATH . 'includes/webinar-handler.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/webinar-handler.php';
}

// Load Ninja Forms ACF questions handler
if (file_exists(WORKBOOKS_NF_PATH . 'includes/acf-ninjaforms-questions.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/acf-ninjaforms-questions.php';
}

// Load AJAX handler for Media Planner Test Form
if (file_exists(WORKBOOKS_NF_PATH . 'includes/media-planner-ajax-handler.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/media-planner-ajax-handler.php';
}

// Include media planner form
if (file_exists(WORKBOOKS_NF_PATH . 'includes/media-planner-form.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/media-planner-form.php';
    error_log('Media Planner form included');
} else {
    error_log('Media Planner form NOT found at ' . WORKBOOKS_NF_PATH . 'includes/media-planner-form.php');
}

// Register Ninja Forms submission handler for Media Planner
if (class_exists('DTR_Media_Planner_Handler')) {
    add_action('ninja_forms_after_submission', ['DTR_Media_Planner_Handler', 'handle_form_submission'], 10, 1);
    error_log('Media Planner Ninja Forms handler registered');
} else {
    error_log('DTR_Media_Planner_Handler class NOT found');
}

// Load AJAX handlers for gated content
require_once WORKBOOKS_NF_PATH . 'admin/gated-content-ajax.php';

// Get WorkbooksApi instance (API Key only)
function get_workbooks_instance() {
    $params = [
        'application_name' => 'wp_workbooks_plugin',
        'user_agent' => 'wp_workbooks_plugin/1.0',
        'api_key' => get_option('workbooks_api_key'),
        'service' => get_option('workbooks_api_url'),
        'json_utf8_encoding' => true,
        'request_timeout' => 30,
        'verify_peer' => false,
    ];
    if ($logical_db = get_option('workbooks_logical_database_id')) {
        $params['logical_database_id'] = $logical_db;
    }
    return new WorkbooksApi($params);
}

// Create database tables on activation
register_activation_hook(__FILE__, 'workbooks_crm_create_tables');
function workbooks_crm_create_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'workbooks_employers';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL,
        name varchar(255) NOT NULL,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY name (name)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

    wp_enqueue_script(
        'workbooks-ninjaform-employers',
        plugin_dir_url(__FILE__) . 'js/ninjaform-employers-field.js',
        ['jquery', 'select2'],
        null,
        true
    );

    wp_localize_script('workbooks-ninjaform-employers', 'workbooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('workbooks_nonce'),
        'plugin_url' => plugin_dir_url(__FILE__)
    ]);

});

// Enqueue custom JS for Ninja Form registration fields (Title, Marketing, Interests)
// Only enqueue employer field JS (keep as before)
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
    wp_enqueue_script(
        'workbooks-ninjaform-employers',
        plugin_dir_url(__FILE__) . 'js/ninjaform-employers-field.js',
        ['jquery', 'select2'],
        null,
        true
    );
    wp_localize_script('workbooks-ninjaform-employers', 'workbooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('workbooks_nonce'),
        'plugin_url' => plugin_dir_url(__FILE__)
    ]);
});
// Enqueue custom JS for copying ACF questions into Ninja Forms (placed at the end for clarity)
/* add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'acf-form-questions',
        plugin_dir_url(__FILE__) . 'js/acf-form-questions.js',
        [],
        null,
        true
    );
}, 30); */

// AJAX handler for dynamic Workbooks titles
add_action('wp_ajax_get_workbooks_titles', 'dtr_ajax_get_workbooks_titles');
add_action('wp_ajax_nopriv_get_workbooks_titles', 'dtr_ajax_get_workbooks_titles');
function dtr_ajax_get_workbooks_titles() {
    if (!function_exists('workbooks_crm_get_personal_titles')) {
        require_once __DIR__ . '/includes/helper-functions.php';
    }
    $titles = function_exists('workbooks_crm_get_personal_titles') ? array_values(workbooks_crm_get_personal_titles()) : [];
    wp_send_json_success($titles);
}
// (Reverted) No Ninja Forms submission payload adjustment

// Add admin menu page with gated content submenus
add_action('admin_menu', function() {
    // Top-level menu
    add_menu_page(
        'Workbooks CRM Settings',
        'Workbooks CRM',
        'manage_options',
        'workbooks-crm-settings',
        'workbooks_crm_settings_page',
        'dashicons-admin-network',
        25
    );
});

// Enqueue admin scripts and styles for AJAX and UI (only on workbooks pages)
add_action('admin_enqueue_scripts', function($hook) {
    // Only include top-level and Gated Content admin pages
    $workbooks_pages = [
        'toplevel_page_workbooks-crm-settings',
        'workbooks-crm_page_workbooks-gated-content'
    ];
    if (!in_array($hook, $workbooks_pages)) return;
    
    // Enqueue admin CSS
    wp_enqueue_style('workbooks-admin-css', plugin_dir_url(__FILE__) . 'assets/admin.css', [], null);
    
    // Enqueue admin JS from js folder
    wp_enqueue_script('workbooks-admin-js', plugin_dir_url(__FILE__) . 'js/admin.js', ['jquery'], null, true);
    wp_localize_script('workbooks-admin-js', 'workbooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('workbooks_nonce'),
        'plugin_url' => plugin_dir_url(__FILE__)
    ]);
    
    // Create single nonce for all AJAX calls
    $ajax_nonce = wp_create_nonce('workbooks_nonce');

    // Enqueue and localize webinar-endpoint.js for this page
    wp_enqueue_script('workbooks-webinar-endpoint-js', plugin_dir_url(__FILE__) . 'js/webinar-endpoint.js', ['jquery'], null, true);
    wp_localize_script('workbooks-webinar-endpoint-js', 'workbooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => $ajax_nonce
    ]);
    
    wp_enqueue_script('employers-sync', plugin_dir_url(__FILE__) . 'js/employers-sync.js', ['jquery'], null, true);
    wp_localize_script('employers-sync', 'workbooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => $ajax_nonce
    ]);

// Enqueue frontend scripts for webinar forms
add_action('wp_enqueue_scripts', function() {
    // Temporarily load on all pages to test - we'll restrict this later
    error_log('🚀 DTR FRONTEND SCRIPTS: wp_enqueue_scripts hook fired at ' . date('Y-m-d H:i:s'));
    
    // Enqueue webinar endpoint script on frontend
    wp_enqueue_script('workbooks-webinar-endpoint-js', plugin_dir_url(__FILE__) . 'js/webinar-endpoint.js', ['jquery'], time(), true);
    wp_localize_script('workbooks-webinar-endpoint-js', 'workbooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('workbooks_nonce')
    ]);
    
    error_log('🎯 DTR WEBINAR SCRIPT: Enqueued with URL: ' . plugin_dir_url(__FILE__) . 'js/webinar-endpoint.js');
}, 20);

// Fallback: Force script inclusion in footer if enqueue doesn't work
add_action('wp_footer', function() {
    error_log('🦶 DTR FOOTER: wp_footer hook fired at ' . date('Y-m-d H:i:s'));
    if (!wp_script_is('workbooks-webinar-endpoint-js', 'enqueued')) {
        error_log('❌ DTR FALLBACK: Script not enqueued, adding manually to footer');
        echo '<script type="text/javascript" src="' . plugin_dir_url(__FILE__) . 'js/webinar-endpoint.js?ver=' . time() . '"></script>';
        echo '<script type="text/javascript">
        var workbooks_ajax = {
            ajax_url: "' . admin_url('admin-ajax.php') . '",
            nonce: "' . wp_create_nonce('workbooks_nonce') . '"
        };
        </script>';
    } else {
        error_log('✅ DTR SUCCESS: Script properly enqueued');
    }
}, 99);

// AJAX handler for fetching webinar ACF data
add_action('wp_ajax_fetch_webinar_acf_data', 'dtr_ajax_fetch_webinar_acf_data');
add_action('wp_ajax_nopriv_fetch_webinar_acf_data', 'dtr_ajax_fetch_webinar_acf_data');
add_action('wp_ajax_fetch_leadgen_acf_data', 'dtr_ajax_fetch_leadgen_acf_data');
add_action('wp_ajax_nopriv_fetch_leadgen_acf_data', 'dtr_ajax_fetch_leadgen_acf_data');

// Debug: AJAX actions registered
error_log('DTR AJAX actions registered for fetch_leadgen_acf_data');

// Test function to ensure AJAX is working
add_action('wp_ajax_test_leadgen_ajax', 'dtr_test_leadgen_ajax');
add_action('wp_ajax_nopriv_test_leadgen_ajax', 'dtr_test_leadgen_ajax');

function dtr_test_leadgen_ajax() {
    error_log('=== dtr_test_leadgen_ajax CALLED ===');
    echo 'AJAX TEST SUCCESS';
    wp_die(); // Use wp_die() instead of wp_send_json_success() for testing
}
function dtr_ajax_fetch_webinar_acf_data() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
        return;
    }
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'webinars') {
        wp_send_json_error('Webinar not found');
        return;
    }
    $workbooks_reference = get_field('workbooks_webinar_reference', $post_id) ?: get_post_meta($post_id, 'workbooks_webinar_reference', true);
    $campaign_reference = get_field('campaign_reference', $post_id) ?: get_post_meta($post_id, 'campaign_reference', true);
    wp_send_json_success([
        'workbooks_reference' => $workbooks_reference,
        'campaign_reference' => $campaign_reference
    ]);
}

function dtr_ajax_fetch_leadgen_acf_data() {
    // IMMEDIATE debug logging
    error_log('=== dtr_ajax_fetch_leadgen_acf_data CALLED ===');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Simple debug logging to WordPress debug log
    error_log('dtr_ajax_fetch_leadgen_acf_data called');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Create logs directory if it doesn't exist
    $log_dir = plugin_dir_path(__FILE__) . 'logs';
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    $log_file = $log_dir . '/admin-lead-gen-debug.log';
    
    // Custom logging function
    $log_debug = function($message) use ($log_file) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    };
    
    // Add debug logging
    $log_debug('dtr_ajax_fetch_leadgen_acf_data called with: ' . print_r($_POST, true));
    
    // Simplified nonce check - try to make it more permissive
    $nonce_valid = false;
    if (isset($_POST['nonce'])) {
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'workbooks_nonce');
        $log_debug('Nonce check result: ' . ($nonce_valid ? 'VALID' : 'INVALID'));
        $log_debug('Provided nonce: ' . $_POST['nonce']);
        $log_debug('Expected nonce action: workbooks_nonce');
    } else {
        $log_debug('No nonce provided');
    }
    
    // Continue even if nonce fails for debugging
    if (!$nonce_valid) {
        $log_debug('Nonce verification failed but continuing for debug');
        // Don't return early, continue for debugging
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) {
        $log_debug('Invalid post ID provided: ' . ($post_id ?: 'empty'));
        wp_send_json_error('Invalid post ID');
        return;
    }
    $post = get_post($post_id);
    if (!$post) {
        $log_debug('Post not found for ID: ' . $post_id);
        wp_send_json_error('Content not found');
        return;
    }
    
    // Check if this is a valid lead gen content type
    $valid_types = ['post', 'publications', 'whitepapers'];
    if (!in_array($post->post_type, $valid_types)) {
        $log_debug('Invalid content type for lead generation: ' . $post->post_type);
        wp_send_json_error('Invalid content type for lead generation');
        return;
    }
    
    // Fetch ACF fields - check if gated content field group is active first
    $workbooks_reference = '';
    $campaign_reference = '';
    
    $log_debug("Checking ACF field groups for post $post_id");
    
    // Check if ACF is available
    if (!function_exists('get_field')) {
        $log_debug('ACF get_field function not available');
        wp_send_json_error('ACF plugin not available');
        return;
    }
    
    // First, let's see what ACF fields are actually available for this post
    $all_acf_fields = get_fields($post_id);
    $log_debug('All ACF fields for post: ' . print_r($all_acf_fields, true));
    
    // Check if this post has the "Gated Content" field group and if restrict_post is enabled
    $restrict_post = get_field('restrict_post', $post_id);
    $log_debug("Restrict post setting: " . ($restrict_post ? 'true' : 'false'));
    
    $has_gated_content = false;
    
    if ($restrict_post) {
        // Access the nested restricted_content_fields group
        $restricted_content_fields = get_field('restricted_content_fields', $post_id);
        $log_debug('Restricted content fields: ' . print_r($restricted_content_fields, true));
        
        if (is_array($restricted_content_fields)) {
            $has_gated_content = true;
            
            // Extract reference and campaign_reference from the nested group
            if (isset($restricted_content_fields['reference'])) {
                $workbooks_reference = $restricted_content_fields['reference'];
                $log_debug("Found workbooks reference '$workbooks_reference' in restricted_content_fields.reference");
            }
            
            if (isset($restricted_content_fields['campaign_reference'])) {
                $campaign_reference = $restricted_content_fields['campaign_reference'];
                $log_debug("Found campaign reference '$campaign_reference' in restricted_content_fields.campaign_reference");
            }
        } else {
            $log_debug('Restricted content fields group is not an array or is empty');
        }
    } else {
        $log_debug('Restrict post is not enabled - checking for legacy field structure');
        
        // Fallback: Check for legacy field structure (direct fields)
        $workbooks_ref_fields = [
            'workbooks_event_reference',
            'workbooks_reference', 
            'event_reference',
            'reference',
            'gated_content_workbooks_reference',
            'gated_content_event_reference'
        ];
        
        foreach ($workbooks_ref_fields as $field_name) {
            $value = '';
            // Try ACF first if available
            if (function_exists('get_field')) {
                $value = get_field($field_name, $post_id);
            }
            // Fallback to post meta
            if (!$value) {
                $value = get_post_meta($post_id, $field_name, true);
            }
            if ($value) {
                $workbooks_reference = $value;
                $has_gated_content = true;
                $log_debug("Found workbooks reference '$value' in legacy field '$field_name' for post $post_id");
                break;
            }
        }
        
        // Try common field names for Campaign reference
        $campaign_ref_fields = [
            'campaign_reference',
            'campaign_ref',
            'workbooks_campaign_reference',
            'gated_content_campaign_reference',
            'gated_content_campaign_ref'
        ];
        
        foreach ($campaign_ref_fields as $field_name) {
            $value = '';
            // Try ACF first
            $value = get_field($field_name, $post_id);
            // Fallback to post meta
            if (!$value) {
                $value = get_post_meta($post_id, $field_name, true);
            }
            if ($value) {
                $campaign_reference = $value;
                $log_debug("Found campaign reference '$value' in legacy field '$field_name' for post $post_id");
                break;
            }
        }
    }
    
    if (!$has_gated_content) {
        $log_debug('No gated content fields found - ACF field group may not be active or restrict_post may be disabled');
    }
    
    $log_debug("Final results - Workbooks reference: '$workbooks_reference', Campaign reference: '$campaign_reference'");
    
    // Let's also check the raw post meta to see what's actually stored
    $all_post_meta = get_post_meta($post_id);
    $log_debug('All post meta for this post: ' . print_r($all_post_meta, true));
    
    $response_data = [
        'workbooks_reference' => $workbooks_reference ?: 'Not set',
        'campaign_reference' => $campaign_reference ?: 'Not set',
        'post_type' => $post->post_type,
        'post_title' => $post->post_title,
        'has_gated_content' => $has_gated_content,
        'restrict_post' => $restrict_post,
        'all_acf_fields' => $all_acf_fields,
        'debug_info' => [
            'acf_available' => function_exists('get_field'),
            'gated_content_approach' => $restrict_post ? 'nested_conditional' : 'legacy_direct'
        ]
    ];
    
    $log_debug('Sending response: ' . print_r($response_data, true));
    wp_send_json_success($response_data);
}

// AJAX handler for fetching Workbooks event details
add_action('wp_ajax_fetch_workbooks_event', 'dtr_ajax_fetch_workbooks_event');
add_action('wp_ajax_nopriv_fetch_workbooks_event', 'dtr_ajax_fetch_workbooks_event');
add_action('wp_ajax_list_workbooks_events', 'dtr_ajax_list_workbooks_events');
add_action('wp_ajax_nopriv_list_workbooks_events', 'dtr_ajax_list_workbooks_events');
add_action('wp_ajax_submit_leadgen_form', 'dtr_ajax_submit_leadgen_form');
add_action('wp_ajax_nopriv_submit_leadgen_form', 'dtr_ajax_submit_leadgen_form');
function dtr_ajax_fetch_workbooks_event() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $event_ref = sanitize_text_field($_POST['event_ref'] ?? '');
    if (!$event_ref) {
        wp_send_json_error('Event reference is required');
        return;
    }
    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        wp_send_json_error('Workbooks API not available');
        return;
    }
    try {
        $filter_field = is_numeric($event_ref) ? 'id' : 'object_ref';
        // Fetch the event with all columns
        $event_result = $workbooks->assertGet('crm/events.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => $filter_field,
            '_ft[]' => 'eq',
            '_fc[]' => $event_ref,
            '_select_columns[]' => '*', // all columns
        ]);
        
        // Debug: Log the search parameters and result
        if (function_exists('workbooks_log')) {
            workbooks_log("Event search - Field: {$filter_field}, Value: {$event_ref}, Results: " . count($event_result['data'] ?? []));
        }
        
        if (empty($event_result['data'][0])) {
            // Try alternative search methods
            if (is_numeric($event_ref)) {
                // Try searching by object_ref as well
                $alt_result = $workbooks->assertGet('crm/events.api', [
                    '_start' => 0,
                    '_limit' => 1,
                    '_ff[]' => 'object_ref',
                    '_ft[]' => 'eq',
                    '_fc[]' => $event_ref,
                    '_select_columns[]' => '*',
                ]);
                if (!empty($alt_result['data'][0])) {
                    $event_result = $alt_result;
                }
            }
            
            if (empty($event_result['data'][0])) {
                wp_send_json_error("Event not found with {$filter_field}: {$event_ref}. Please verify the event exists in Workbooks.");
                return;
            }
        }
        $event = $event_result['data'][0];

        // Fetch all tabs/related records (e.g., attendees/registrants)
        // Example: fetch attendees/registrants for the event
        $attendees_result = $workbooks->assertGet('crm/event_attendees.api', [
            '_ff[]' => 'event_id',
            '_ft[]' => 'eq',
            '_fc[]' => $event['id'],
            '_select_columns[]' => '*',
            '_limit' => 1000 // fetch up to 1000 attendees
        ]);
        $attendees = $attendees_result['data'] ?? [];
        
        // Debug: Log attendees count
        if (function_exists('workbooks_log')) {
            workbooks_log("Event {$event['id']} attendees found: " . count($attendees));
        }

        // You can add more related tabs here if needed (e.g., sponsors, sessions, etc.)

        $response = [
            'event' => $event,
            'attendees' => $attendees,
            // Add more related data here as needed
        ];
        wp_send_json_success($response);
    } catch (Exception $e) {
        if (function_exists('workbooks_log')) {
            workbooks_log("Event fetch error for {$event_ref}: " . $e->getMessage());
        }
        wp_send_json_error('Error fetching event: ' . $e->getMessage());
    }
}

function dtr_ajax_list_workbooks_events() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    
    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        wp_send_json_error('Workbooks API not available');
        return;
    }
    
    try {
        // Fetch recent events (last 50)
        $events_result = $workbooks->assertGet('crm/events.api', [
            '_start' => 0,
            '_limit' => 50,
            '_select_columns[]' => ['id', 'name', 'object_ref', 'start_date', 'end_date', 'event_type', 'lock_version'],
            '_sort_column' => 'id',
            '_sort_direction' => 'DESC'
        ]);
        
        $events = $events_result['data'] ?? [];
        
        if (function_exists('workbooks_log')) {
            workbooks_log("Listed " . count($events) . " recent events");
        }
        
        wp_send_json_success(['events' => $events]);
        
    } catch (Exception $e) {
        if (function_exists('workbooks_log')) {
            workbooks_log("Error listing events: " . $e->getMessage());
        }
        wp_send_json_error('Error listing events: ' . $e->getMessage());
    }
}

function dtr_ajax_submit_leadgen_form() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    
    // Sanitize form inputs
    $post_id = intval($_POST['leadgen_post_id'] ?? 0);
    $event_ref = sanitize_text_field($_POST['leadgen_event_ref'] ?? '');
    $participant_email = sanitize_email($_POST['leadgen_participant_email'] ?? '');
    $interest_reason = sanitize_textarea_field($_POST['leadgen_interest_reason'] ?? '');
    $sponsor_optin = isset($_POST['leadgen_sponsor_optin']) ? 1 : 0;
    $marketing_optin = isset($_POST['leadgen_marketing_optin']) ? 1 : 0;
    
    if (!$participant_email) {
        wp_send_json_error('Email is required');
        return;
    }
    
    if (!$post_id && !$event_ref) {
        wp_send_json_error('Either content selection or event reference is required');
        return;
    }
    
    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        wp_send_json_error('Workbooks API not available');
        return;
    }
    
    try {
        // Get or create person record
        $person_result = $workbooks->assertGet('crm/people.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'main_location[email]',
            '_ft[]' => 'eq',
            '_fc[]' => $participant_email,
        ]);
        
        $person_id = null;
        if (!empty($person_result['data'][0])) {
            $person_id = $person_result['data'][0]['id'];
        } else {
            // Create new person if not found
            $person_data = [
                'main_location[email]' => $participant_email,
                'lead_source_type' => 'Lead Generation Form',
                'cf_person_dtr_subscriber_type' => 'Lead',
                'cf_person_is_person_active_or_inactive' => 'Active',
                'cf_person_data_source_detail' => 'Lead Gen Form Submission',
            ];
            
            if ($marketing_optin) {
                $person_data['cf_person_dtr_news'] = 1;
                $person_data['cf_person_dtr_events'] = 1;
            }
            
            $create_result = $workbooks->assertCreate('crm/people', [$person_data]);
            if (!empty($create_result['data'][0]['id'])) {
                $person_id = $create_result['data'][0]['id'];
            }
        }
        
        if (!$person_id) {
            wp_send_json_error('Could not create or find person record');
            return;
        }
        
        // Create lead generation activity
        $activity_data = [
            'party_id' => $person_id,
            'activity_type' => 'Lead Generation',
            'subject' => 'Lead Gen Form Submission',
            'description' => "Interest in content: " . ($post_id ? get_the_title($post_id) : "Event $event_ref"),
        ];
        
        if ($interest_reason) {
            $activity_data['description'] .= "\nReason: " . $interest_reason;
        }
        
        if ($post_id) {
            $activity_data['description'] .= "\nContent ID: $post_id";
            $activity_data['description'] .= "\nContent Type: " . get_post_type($post_id);
        }
        
        if ($event_ref) {
            $activity_data['description'] .= "\nEvent Reference: $event_ref";
        }
        
        $activity_data['description'] .= "\nSponsor Opt-in: " . ($sponsor_optin ? 'Yes' : 'No');
        $activity_data['description'] .= "\nMarketing Opt-in: " . ($marketing_optin ? 'Yes' : 'No');
        
        $activity_result = $workbooks->assertCreate('crm/activities', [$activity_data]);
        
        if (function_exists('workbooks_log')) {
            workbooks_log("Lead gen form submitted for $participant_email, Person ID: $person_id");
        }
        
        wp_send_json_success('Lead generation form submitted successfully. Thank you for your interest!');
        
    } catch (Exception $e) {
        if (function_exists('workbooks_log')) {
            workbooks_log("Lead gen form error for $participant_email: " . $e->getMessage());
        }
        wp_send_json_error('Error submitting form: ' . $e->getMessage());
    }
}
    
    wp_enqueue_script('employers-sync', plugin_dir_url(__FILE__) . 'js/employers-sync.js', ['jquery'], null, true);
    wp_localize_script('employers-sync', 'workbooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('workbooks_nonce')
    ]);
});

// Register plugin settings
add_action('admin_init', function() {
    register_setting('workbooks_crm_options', 'workbooks_api_url');
    register_setting('workbooks_crm_options', 'workbooks_api_key');
    register_setting('workbooks_crm_options', 'workbooks_logical_database_id');
});

// Settings page content
function workbooks_crm_settings_page() {
    ?>
    <style>
    .workbooks-admin-container {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }
    .workbooks-vertical-tabs {
        flex: 0 0 240px;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .workbooks-vertical-tabs .nav-tab {
        display: block;
        width: 100%;
        margin: 0;
        border: none;
        border-bottom: 1px solid #ccd0d4;
        border-radius: 0;
        padding: 12px 15px;
        text-decoration: none;
        background: #f6f7f7;
        color: #555;
        font-weight: 400;
        transition: all 0.2s ease;
    }
    .workbooks-vertical-tabs .nav-tab:last-child {
        border-bottom: none;
    }
    .workbooks-vertical-tabs .nav-tab:hover {
        background: #e6f3ff;
        color: #0073aa;
    }
    .workbooks-vertical-tabs .nav-tab.nav-tab-active {
        background: #0073aa;
        color: #fff;
        font-weight: 600;
        border-left: 4px solid #005177;
    }
    .workbooks-content-area {
        flex: 1;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        min-height: 600px;
    }
    .workbooks-tab-content {
        display: none;
    }
    .workbooks-tab-content.active {
        display: block !important;
    }
    </style>
    <div class="wrap">
        <h1>Workbooks CRM API Key Integration</h1>
        <div class="workbooks-admin-container">
            <nav class="workbooks-vertical-tabs">
                <a href="#" class="nav-tab nav-tab-active" id="workbooks-settings-tab">Settings</a>
                <a href="#" class="nav-tab" id="workbooks-person-tab">Person Record</a>
                <a href="#" class="nav-tab" id="workbooks-gated-content-tab">Gated Content</a>
                <a href="#" class="nav-tab" id="workbooks-webinar-tab">Webinar Registration</a>
                <a href="#" class="nav-tab" id="workbooks-mediaplanner-tab">Media Planner Form</a>
                <a href="#" class="nav-tab" id="workbooks-membership-tab">Membership Sign Up</a>
                <a href="#" class="nav-tab" id="workbooks-employers-tab">Employers</a>
                <a href="#" class="nav-tab" id="workbooks-ninja-users-tab">Ninja Form Users</a>
                <a href="#" class="nav-tab" id="workbooks-topics-tab">Topics of Interest</a>
            </nav>
            <script>
            jQuery(document).ready(function($) {
                $('.workbooks-vertical-tabs .nav-tab').on('click', function(e) {
                    e.preventDefault();
                    var tabId = $(this).attr('id');
                    // Remove active from all tabs
                    $('.workbooks-vertical-tabs .nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    // Hide all tab contents
                    $('.workbooks-content-area .workbooks-tab-content').removeClass('active').hide();
                    // Show the selected tab content
                    var contentId = tabId.replace('-tab', '-content');
                    $('#' + contentId).addClass('active').show();
                });
                // Ensure only the first tab is visible on load
                $('.workbooks-content-area .workbooks-tab-content').hide();
                $('#workbooks-settings-content').addClass('active').show();
            });
            </script>
            <div class="workbooks-content-area">
                <!-- Settings Tab -->
                <div id="workbooks-settings-content" class="workbooks-tab-content active">
                <h2>Workbooks CRM Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('workbooks_crm_options'); ?>
                    <input type="hidden" name="option_page" value="workbooks_crm_options">
                    <input type="hidden" name="action" value="update">
                    
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="workbooks_api_url">API URL</label></th>
                                <td>
                                    <input name="workbooks_api_url" id="workbooks_api_url" type="url" value="https://russellpublishing-live.workbooks.com/" class="regular-text" required="">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="workbooks_api_key">API Key</label></th>
                                <td>
                                    <input name="workbooks_api_key" id="workbooks_api_key" type="text" value="eb7f1-04a7d-9654d-01904-a6823-d10c0-fc4c5-d5b2c" class="regular-text" required="">
                                </td>
                            </tr>
                            <!-- <tr>
                                <th scope="row"><label for="workbooks_logical_database_id">Logical Database</label></th>
                                <td>
                                    <select name="workbooks_logical_database_id" id="workbooks_logical_database_id" disabled="">
                                        <option value="">No database selection required</option>
                                    </select>
                                    <p class="description">Select your logical database (optional).</p>
                                </td>
                            </tr> -->
                        </tbody>
                    </table>
                    
                    <div class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
                        <button id="workbooks_test_connection" class="button button-secondary" type="button">Test Connection</button>
                        <div class="workbooks-api-status">
                            <span class="workbooks-status-pill" id="connection-status">Status: Connected</span>
                        </div>
                    </div>
                </form>
                
                <div id="workbooks_test_result"></div>
                </div>
                <!-- Person Record Tab -->
                <div id="workbooks-person-content" class="workbooks-tab-content">
                    <h2>Update Fixed Workbooks Person Record (ID: 4208693)</h2>
                    <?php
                    // Handle Delete User and Generate Workbooks IDs actions for Ninja Forms Users tab
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        // Delete user
                        if (isset($_POST['delete_user_id']) && current_user_can('delete_users')) {
                            $delete_user_id = intval($_POST['delete_user_id']);
                            if ($delete_user_id && $delete_user_id !== get_current_user_id()) {
                                require_once ABSPATH . 'wp-admin/includes/user.php';
                                wp_delete_user($delete_user_id);
                                echo '<div class="notice notice-success is-dismissible"><p>User ID ' . esc_html($delete_user_id) . ' deleted.</p></div>';
                            } else {
                                echo '<div class="notice notice-error is-dismissible"><p>Cannot delete this user.</p></div>';
                            }
                        }
                        // Generate Workbooks IDs
                        if (isset($_POST['generate_workbooks_ids_user_id']) && current_user_can('edit_users')) {
                            $gen_user_id = intval($_POST['generate_workbooks_ids_user_id']);
                            $user = get_userdata($gen_user_id);
                            if ($user) {
                                $first = get_user_meta($gen_user_id, 'first_name', true);
                                $last = get_user_meta($gen_user_id, 'last_name', true);
                                $email = $user->user_email;
                                $employer = get_user_meta($gen_user_id, 'employer_name', true);
                                $town = get_user_meta($gen_user_id, 'town', true);
                                $country = get_user_meta($gen_user_id, 'country', true) ?: 'South Africa';
                                $telephone = get_user_meta($gen_user_id, 'telephone', true);
                                $postcode = get_user_meta($gen_user_id, 'postcode', true);
                                $job_title = get_user_meta($gen_user_id, 'job_title', true);
                                $title = get_user_meta($gen_user_id, 'person_personal_title', true);
                                $payload = [
                                    'person_first_name' => $first,
                                    'person_last_name' => $last,
                                    'main_location[email]' => $email,
                                    'cf_person_claimed_employer' => $employer,
                                    'created_through_reference' => 'wp_user_' . $gen_user_id,
                                    'main_location[town]' => $town,
                                    'main_location[country]' => $country,
                                    'main_location[telephone]' => $telephone,
                                    'main_location[postcode]' => $postcode,
                                    'person_job_title' => $job_title,
                                    'person_personal_title' => $title,
                                    'cf_person_dtr_subscriber_type' => 'Prospect',
                                    'cf_person_dtr_web_member' => 1,
                                    'lead_source_type' => 'Online Registration',
                                    'cf_person_is_person_active_or_inactive' => 'Active',
                                    'cf_person_data_source_detail' => 'DTR Member Registration',
                                ];
                                $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
                                if ($workbooks) {
                                    try {
                                        $result = $workbooks->assertCreate('crm/people', [$payload]);
                                        if (!empty($result['data'][0]['id'])) {
                                            update_user_meta($gen_user_id, 'workbooks_person_id', $result['data'][0]['id']);
                                            update_user_meta($gen_user_id, 'workbooks_object_ref', $result['data'][0]['object_ref'] ?? '');
                                            echo '<div class="notice notice-success is-dismissible"><p>Workbooks IDs generated for user ID ' . esc_html($gen_user_id) . '.</p></div>';
                                        } else {
                                            echo '<div class="notice notice-error is-dismissible"><p>Workbooks did not return an ID for user ID ' . esc_html($gen_user_id) . '.</p></div>';
                                        }
                                    } catch (Exception $e) {
                                        echo '<div class="notice notice-error is-dismissible"><p>Workbooks error: ' . esc_html($e->getMessage()) . '</p></div>';
                                    }
                                } else {
                                    echo '<div class="notice notice-error is-dismissible"><p>Workbooks API not available.</p></div>';
                                }
                            }
                        }
                    }
                    // Display all Workbooks fields and values for current user (from Workbooks API, not just user meta)
                    $current_user_id = get_current_user_id();
                    $workbooks_person_id = get_user_meta($current_user_id, 'workbooks_person_id', true);
                    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
                    $workbooks_data = [];
                    if ($workbooks && $workbooks_person_id) {
                        try {
                            $result = $workbooks->assertGet('crm/people.api', [
                                '_start' => 0,
                                '_limit' => 1,
                                '_ff[]' => 'id',
                                '_ft[]' => 'eq',
                                '_fc[]' => $workbooks_person_id
                            ]);
                            if (!empty($result['data'][0])) {
                                $workbooks_data = $result['data'][0];
                            }
                        } catch (Exception $e) {
                            echo '<div class="notice notice-error"><p>Could not fetch Workbooks record: ' . esc_html($e->getMessage()) . '</p></div>';
                        }
                    }
                    echo '<div style="margin-bottom:10px;">';
                    echo '<strong>Workbooks API Fields for this User:</strong>';
                    if (!empty($workbooks_data)) {
                        echo '<table class="widefat striped" style="margin-top:8px; max-width:900px;">';
                        echo '<thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';
                        foreach ($workbooks_data as $field => $val) {
                            if (is_array($val)) $val = json_encode($val);
                            echo '<tr><td>' . esc_html($field) . '</td><td>' . esc_html(($val === '' ? '-' : $val)) . '</td></tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<em>No Workbooks record found for this user.</em>';
                    }
                    echo '</div>';
                    ?>
                    <form id="workbooks_update_user_form" method="post">
                        <input type="hidden" name="workbooks_update_user" value="1">
                        <?php
                        $user_id = get_current_user_id();
                        $user = get_userdata($user_id);
                        $workbooks = get_workbooks_instance();
                        $fixed_person_id = 4208693;
                        $existing = $workbooks->assertGet('crm/people.api', [
                            '_start' => 0,
                            '_limit' => 1,
                            '_ff[]' => 'id',
                            '_ft[]' => 'eq',
                            '_fc[]' => $fixed_person_id,
                            '_select_columns[]' => [
                                'id', 'lock_version',
                                'person_title', 'person_first_name', 'person_last_name', 'person_job_title',
                                'main_location[email]', 'main_location[telephone]', 'main_location[country]',
                                'main_location[town]', 'main_location[postcode]',
                                'employer_name'
                            ]
                        ]);
                        $person = $existing['data'][0] ?? [];
                        function get_field_value($person, $field, $user_id, $meta_key = '') {
                            if (!empty($person[$field])) {
                                return esc_attr($person[$field]);
                            } elseif ($meta_key && $value = get_user_meta($user_id, $meta_key, true)) {
                                return esc_attr($value);
                            }
                            return '';
                        }
                        if (!empty($person['id'])) {
                            echo '<input type="hidden" name="person_id" value="' . esc_attr($person['id']) . '">';
                            echo '<input type="hidden" name="lock_version" value="' . esc_attr($person['lock_version']) . '">';
                        }
                        ?>
                        <p><label for="person_title">Title<br>
                            <?php
                            $titles = [
                                'Dr.' => 'Dr.',
                                'Master' => 'Master',
                                'Miss' => 'Miss',
                                'Mr.' => 'Mr.',
                                'Mrs.' => 'Mrs.',
                                'Ms.' => 'Ms.',
                                'Prof.' => 'Prof.'
                            ];
                            $current_title = $person['person_personal_title'] ?? get_user_meta($user_id, 'person_personal_title', true);
                            echo '<select id="person_title" name="person_personal_title">';
                            echo '<option value="">-- Select --</option>';
                            foreach ($titles as $value => $label) {
                                $selected = ($current_title == $value) ? 'selected' : '';
                                echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                            }
                            echo '</select>';
                            ?>
                        </label></p>
                        <p><label for="person_first_name">First Name<br>
                            <input type="text" id="person_first_name" name="person_first_name" value="<?php echo get_field_value($person, 'person_first_name', $user_id, 'first_name'); ?>" class="regular-text"></label></p>
                        <p><label for="person_last_name">Last Name<br>
                            <input type="text" id="person_last_name" name="person_last_name" value="<?php echo get_field_value($person, 'person_last_name', $user_id, 'last_name'); ?>" class="regular-text"></label></p>
                        <p><label for="person_job_title">Job Title<br>
                            <input type="text" id="person_job_title" name="person_job_title" value="<?php echo get_field_value($person, 'person_job_title', $user_id, 'job_title'); ?>" class="regular-text"></label></p>
                        <?php
                        $dtr_fields = [
                            'cf_person_dtr_news' => 'DTR News',
                            'cf_person_dtr_events' => 'DTR Events',
                            // 'cf_person_dtr_subscriber' => 'DTR Subscriber', // Not editable, set on registration
                            'cf_person_dtr_third_party' => 'DTR Third Party',
                            'cf_person_dtr_webinar' => 'DTR Webinar',
                        ];
                        $interests_fields = [
                            'cf_person_business' => 'Business',
                            'cf_person_diseases' => 'Diseases',
                            'cf_person_drugs_therapies' => 'Drugs & Therapies',
                            'cf_person_genomics_3774' => 'Genomics',
                            'cf_person_research_development' => 'Research & Development',
                            'cf_person_technology' => 'Technology',
                            'cf_person_tools_techniques' => 'Tools & Techniques',
                        ];
                        ?>
                        <fieldset style="margin-bottom:20px;">
                            <legend><strong>Marketing Preferences</strong></legend>
                            <?php foreach ($dtr_fields as $field => $label): ?>
                                <?php
                                $checked = get_user_meta($user_id, $field, true) || !empty($person[$field]);
                                ?>
                                <label style="display:block;">
                                    <input type="checkbox" name="<?php echo esc_attr($field); ?>" value="1" <?php checked($checked); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <fieldset style="margin-bottom:20px;">
                            <legend><strong>Topics of Interest</strong></legend>
                            <?php foreach ($interests_fields as $field => $label): ?>
                                <?php
                                $checked = get_user_meta($user_id, $field, true) || !empty($person[$field]);
                                ?>
                                <label style="display:block;">
                                    <input type="checkbox" name="<?php echo esc_attr($field); ?>" value="1" <?php checked($checked); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p><label for="email">Email<br>
                            <input type="email" id="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" class="regular-text" readonly></label>
                            <small>Email is read-only and taken from your WordPress user account.</small></p>
                        <p><label for="employer">Employer<br>
                            <select id="employer" name="employer">
                                <option value="">-- Select Employer --</option>
                                <option value="loading">Loading employers...</option>
                            </select>
                            <span id="employer-loading" style="display:none;">Loading...</span>
                        </label></p>
                        <p><label for="telephone">Telephone<br>
                            <input type="text" id="telephone" name="telephone" value="<?php echo get_field_value($person, 'main_location[telephone]', $user_id); ?>" class="regular-text"></label></p>
                        <p><label for="country">Country<br>
                            <input type="text" id="country" name="country" value="<?php echo get_field_value($person, 'main_location[country]', $user_id); ?>" class="regular-text"></label></p>
                        <p><label for="town">Town / City<br>
                            <input type="text" id="town" name="town" value="<?php echo get_field_value($person, 'main_location[town]', $user_id); ?>" class="regular-text"></label></p>
                        <p><label for="postcode">Post / Zip Code<br>
                            <input type="text" id="postcode" name="postcode" value="<?php echo get_field_value($person, 'main_location[postcode]', $user_id); ?>" class="regular-text"></label></p>
                        <?php submit_button('Update Person Record'); ?>
                    </form>
                </div>
                <!-- Gated Content Section (Single Page, No Tabs) -->
                <div id="workbooks-gated-content" class="workbooks-tab-content">
                    <?php
                        $gated_content_file = WORKBOOKS_NF_PATH . 'admin/gated-content.php';
                        if (file_exists($gated_content_file)) {
                            include $gated_content_file;
                        } else {
                            echo '<p><em>Gated content admin file not found.</em></p>';
                        }
                    ?>
                </div>
                <!-- Webinar Tab -->
                <div id="workbooks-webinar-content" class="workbooks-tab-content">
                    <?php
                    $webinars = get_posts([
                        'post_type'      => 'webinars',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                    ]);
                    $current_user_email = esc_attr(wp_get_current_user()->user_email);
                    ?>
                    <h2>Webinar Registration Endpoint</h2>
                    <form id="webinar-registration-form" method="post">
                        <p>
                            <label for="workbooks_event_ref">Or Enter Workbooks Event ID or Reference:</label><br>
                            <input type="text" id="workbooks_event_ref" name="workbooks_event_ref" class="regular-text" placeholder="Event ID or Reference (e.g. 5029)" />
                            <button type="button" id="fetch-event-btn" class="button">Fetch Event Details</button>
                        </p>
                        <div id="event-fetch-response" style="margin-bottom: 15px; color: #444;"></div>
                        <div id="event-fields-table-container" style="margin-bottom: 15px; display:none;"></div>
                        <p>
                            <label for="webinar_post_id">Select Webinar:</label><br>
                            <select id="webinar_post_id" name="webinar_post_id" required>
                                <option value="">-- Select a Webinar --</option>
                                <?php foreach ($webinars as $webinar): ?>
                                    <option value="<?php echo esc_attr($webinar->ID); ?>">
                                        <?php echo esc_html($webinar->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <div id="acf-info" style="margin-bottom: 15px; display:none;">
                            <strong>Workbooks Webinar Reference:</strong> <span id="webinar_ref"></span><br>
                            <strong>Campaign Reference:</strong> <span id="campaign_ref"></span>
                        </div>
                        <p>
                            <label for="participant_email">Participant Email:</label><br>
                            <input type="email" id="participant_email" name="participant_email" class="regular-text" required value="<?php echo $current_user_email; ?>" readonly>
                        </p>
                        <p>
                            <label for="speaker_question">Speaker Question (optional):</label><br>
                            <textarea id="speaker_question" name="speaker_question" rows="4" cols="50"></textarea>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="sponsor_optin" id="sponsor_optin" value="1">
                                I agree to receive sponsor information (opt-in)
                            </label>
                        </p>
                        <p><button type="submit" class="button button-primary">Submit Registration</button></p>
                    </form>
                    <div id="webinar-response" style="margin-top: 20px;"></div>
                    <script>
                    jQuery(document).ready(function($) {
                        $('#fetch-event-btn').on('click', function(e) {
                            e.preventDefault();
                            var eventRef = $('#workbooks_event_ref').val();
                            var $response = $('#event-fetch-response');
                            var $tableContainer = $('#event-fields-table-container');
                            $response.html('');
                            $tableContainer.hide().html('');
                            if (!eventRef) {
                                $response.html('<span style="color:red;">Please enter an Event ID or Reference.</span>');
                                return;
                            }
                            $response.html('Fetching event details...');
                            $.ajax({
                                url: (typeof workbooks_ajax !== 'undefined' && workbooks_ajax.ajax_url) ? workbooks_ajax.ajax_url : ajaxurl,
                                method: 'POST',
                                data: {
                                    action: 'fetch_workbooks_event',
                                    event_ref: eventRef,
                                    nonce: (typeof workbooks_ajax !== 'undefined' && workbooks_ajax.nonce) ? workbooks_ajax.nonce : ''
                                },
                                dataType: 'json',
                                success: function(response) {
                                    $tableContainer.hide().html('');
                                    if (response && response.success) {
                                        var details = response.data;
                                        var html = '<span style="color:green;">Event details fetched.</span>';
                                        if (details && typeof details === 'object' && Object.keys(details).length > 0) {
                                            html += '<table class="widefat striped" style="margin-top:8px; max-width:600px;">';
                                            html += '<thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';
                                            for (var key in details) {
                                                if (!details.hasOwnProperty(key)) continue;
                                                var val = details[key];
                                                if (typeof val === 'object') val = JSON.stringify(val);
                                                html += '<tr><td>' + key + '</td><td>' + (val === '' ? '-' : val) + '</td></tr>';
                                            }
                                            html += '</tbody></table>';
                                        } else {
                                            html += '<div style="margin-top:10px;">No event details found for this reference.</div>';
                                        }
                                        $response.html(html);
                                    } else {
                                        $response.html('<span style="color:red;">Could not fetch event details.</span>');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    $tableContainer.hide().html('');
                                    $response.html('<span style="color:red;">AJAX error fetching event details.</span>');
                                    if (xhr && xhr.responseText) {
                                        // Optionally log or display debug info
                                        console.error('AJAX error:', xhr.responseText);
                                    }
                                }
                            });
                        });
                    });
                    </script>
                </div>
                <!-- Media Planner Form Tab -->
                <div id="workbooks-mediaplanner-content" class="workbooks-tab-content">
                    <h2>Media Planner Form (Test Submission)</h2>
                    <form id="media-planner-form" method="post">
                        <p>
                            <label for="mp_first_name">First Name:</label><br>
                            <input type="text" id="mp_first_name" name="first_name" class="regular-text" placeholder="First Name" required>
                        </p>
                        <p>
                            <label for="mp_last_name">Last Name:</label><br>
                            <input type="text" id="mp_last_name" name="last_name" class="regular-text" placeholder="Last Name" required>
                        </p>
                        <p>
                            <label for="mp_email_address">Email Address:</label><br>
                            <input type="email" id="mp_email_address" name="email_address" class="regular-text" placeholder="Email Address" required>
                        </p>
                        <p>
                            <label for="mp_job_title">Job Title:</label><br>
                            <input type="text" id="mp_job_title" name="job_title" class="regular-text" placeholder="Job Title" required>
                        </p>
                        <p>
                            <label for="mp_organisation">Organisation:</label><br>
                            <input type="text" id="mp_organisation" name="organisation" class="regular-text" placeholder="Organisation" required>
                        </p>
                        <p>
                            <label for="mp_town">Town:</label><br>
                            <input type="text" id="mp_town" name="town" class="regular-text" placeholder="Town" required>
                        </p>
                        <p>
                            <label for="mp_country">Country:</label><br>
                            <input type="text" id="mp_country" name="country" class="regular-text" placeholder="Country" required>
                        </p>
                        <p>
                            <label for="mp_telephone">Telephone:</label><br>
                            <input type="text" id="mp_telephone" name="telephone" class="regular-text" placeholder="Telephone" required>
                        </p>
                        <p>
                            <button type="submit" class="button button-primary">Submit</button>
                        </p>
                    </form>
                    <div id="media-planner-result"></div>
                    <style>
                        /* Media Planner Form: Input Styles */
                        #media-planner-form input[type="text"],
                        #media-planner-form input[type="email"] {
                            height: 36px !important;
                            padding: 0 12px !important;
                            border: 1px solid #dcdcde !important;
                            border-radius: 4px !important;
                            font-size: 14px !important;
                            line-height: 1.4 !important;
                            transition: border-color 0.2s ease !important;
                            background: #ffffff !important;
                            box-sizing: border-box;
                            width: 100%;
                            margin: 0 0 10px 0;
                        }

                        /* Media Planner Form: Button Style */
                        #media-planner-form button[type="submit"] {
                            height: 36px;
                            padding: 0 16px !important;
                            border-radius: 4px !important;
                            font-size: 14px !important;
                            font-weight: 500;
                            text-decoration: none;
                            cursor: pointer;
                            transition: all 0.2s ease;
                            border-width: 1px;
                            border-style: solid;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            line-height: 1;
                            text-shadow: none !important;
                            box-shadow: none !important;
                            background: #007cba;
                            color: #fff;
                            border-color: #007cba;
                        }
                        #media-planner-form button[type="submit"]:hover {
                            background: #005a9e;
                            border-color: #005a9e;
                        }
                    </style>
                    <script>
                        jQuery(function($){
                            console.log('✅ Media Planner Test Form JS loaded and ready to test');
                            $('#media-planner-form').off('submit').on('submit', function(e){
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                var data = $(this).serialize();
                                data += '&action=media_planner_test_submit&nonce=' + workbooks_ajax.nonce;
                                $('#media-planner-result').html('Submitting...');
                                $.post(workbooks_ajax.ajax_url, data, function(response){
                                    if (response.success) {
                                        $('#media-planner-result').html('<span style="color:green">' + response.data + '</span>');
                                    } else {
                                        $('#media-planner-result').html('<span style="color:red">' + response.data + '</span>');
                                    }
                                });
                                return false;
                            });
                        });
                    </script>
                </div>
                <!-- Membership Sign Up Tab -->
                <div id="workbooks-membership-content" class="workbooks-tab-content">
                    <h2>Membership Sign Up (Test Registration)</h2>
                    <?php
                    if (!current_user_can('manage_options')) {
                        echo '<p>You do not have permission to use this form.</p>';
                    } else {
                        $msg = '';
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dtr_admin_test_reg_submit'])) {
                            $first = sanitize_text_field($_POST['first_name'] ?? 'TestFirst');
                            $last = sanitize_text_field($_POST['last_name'] ?? 'TestLast');
                            $email = sanitize_email($_POST['email'] ?? 'testuser_' . time() . '@supersonicplayground.com');
                            $employer = sanitize_text_field($_POST['employer'] ?? 'Supersonic Playground Ltd');
                            $town = sanitize_text_field($_POST['town'] ?? 'London');
                            $country = sanitize_text_field($_POST['country'] ?? 'United Kingdom');
                            $telephone = sanitize_text_field($_POST['telephone'] ?? '0123456789');
                            $postcode = sanitize_text_field($_POST['postcode'] ?? 'SE10 9XY');
                            $job_title = sanitize_text_field($_POST['job_title'] ?? 'Developer');
                            $title = sanitize_text_field($_POST['person_personal_title'] ?? 'Mr.');
                            $test_user_id = email_exists($email);

                            if (!$test_user_id) {
                                $random_pass = wp_generate_password(12, false);
                                $test_user_id = wp_create_user($email, $random_pass, $email);
                                if (is_wp_error($test_user_id)) {
                                    $msg = '<div class="error"><p>Could not create test user: ' . esc_html($test_user_id->get_error_message()) . '</p></div>';
                                    if (function_exists('workbooks_log')) workbooks_log("Test registration failed: Could not create user $email: " . $test_user_id->get_error_message());
                                }
                            }

                            if ($test_user_id && !is_wp_error($test_user_id)) {
                                $meta_fields = [
                                    'first_name' => $first,
                                    'last_name' => $last,
                                    'employer' => $employer,
                                    'employer_name' => $employer,
                                    'town' => $town,
                                    'country' => $country,
                                    'telephone' => $telephone,
                                    'postcode' => $postcode,
                                    'job_title' => $job_title,
                                    'person_personal_title' => $title,
                                    'created_via_ninja_form' => 1,
                                    'cf_person_dtr_subscriber_type' => 'Prospect',
                                    'cf_person_dtr_web_member' => 1,
                                    'lead_source_type' => 'Online Registration',
                                    'cf_person_is_person_active_or_inactive' => 'Active',
                                    'cf_person_data_source_detail' => 'DTR Member Registration'
                                ];
                                $subs = [
                                    'cf_person_dtr_news' => isset($_POST['cf_person_dtr_news']) ? 1 : 0,
                                    'cf_person_dtr_events' => isset($_POST['cf_person_dtr_events']) ? 1 : 0,
                                    // 'cf_person_dtr_subscriber' => isset($_POST['cf_person_dtr_subscriber']) ? 1 : 0, // Not editable, set on registration
                                    'cf_person_dtr_third_party' => isset($_POST['cf_person_dtr_third_party']) ? 1 : 0,
                                    'cf_person_dtr_webinar' => isset($_POST['cf_person_dtr_webinar']) ? 1 : 0
                                ];
                                $interests = [
                                    'cf_person_business' => isset($_POST['cf_person_business']) ? 1 : 0,
                                    'cf_person_diseases' => isset($_POST['cf_person_diseases']) ? 1 : 0,
                                    'cf_person_drugs_therapies' => isset($_POST['cf_person_drugs_therapies']) ? 1 : 0,
                                    'cf_person_genomics_3774' => isset($_POST['cf_person_genomics_3774']) ? 1 : 0,
                                    'cf_person_research_development' => isset($_POST['cf_person_research_development']) ? 1 : 0,
                                    'cf_person_technology' => isset($_POST['cf_person_technology']) ? 1 : 0,
                                    'cf_person_tools_techniques' => isset($_POST['cf_person_tools_techniques']) ? 1 : 0
                                ];
                                foreach ($meta_fields as $key => $value) {
                                    update_user_meta($test_user_id, $key, $value);
                                }
                                foreach ($subs as $key => $value) {
                                    update_user_meta($test_user_id, $key, $value);
                                }
                                foreach ($interests as $key => $value) {
                                    update_user_meta($test_user_id, $key, $value);
                                }
                                $workbooks = get_workbooks_instance();
                                if (!$workbooks) {
                                    $msg = '<div class="error"><p>Workbooks API initialization failed.</p></div>';
                                    if (function_exists('workbooks_log')) workbooks_log("Test registration failed: Workbooks API initialization failed for $email");
                                } else {
                                    $person_id = get_user_meta($test_user_id, 'workbooks_person_id', true);
                                    $payload = [
                                        'person_first_name' => $first,
                                        'person_last_name' => $last,
                                        'main_location[email]' => $email,
                                        'cf_person_claimed_employer' => $employer,
                                        'created_through_reference' => 'wp_user_' . $test_user_id,
                                        'main_location[town]' => $town,
                                        'main_location[country]' => $country,
                                        'main_location[telephone]' => $telephone,
                                        'main_location[postcode]' => $postcode,
                                        'person_job_title' => $job_title,
                                        'person_personal_title' => $title,
                                        'cf_person_dtr_subscriber_type' => 'Prospect',
                                        'cf_person_dtr_web_member' => 1,
                                        'lead_source_type' => 'Online Registration',
                                        'cf_person_is_person_active_or_inactive' => 'Active',
                                        'cf_person_data_source_detail' => 'DTR Member Registration'
                                    ];
                                    $payload = array_merge($payload, $subs, $interests);
                                    $org_id = function_exists('workbooks_get_or_create_organisation_id') ? workbooks_get_or_create_organisation_id($employer) : null;
                                    if ($org_id) {
                                        $payload['main_employer'] = $org_id;
                                    }
                                    try {
                                        if ($person_id) {
                                            $payload['id'] = $person_id;
                                            $existing = $workbooks->assertGet('crm/people', [
                                                '_start' => 0,
                                                '_limit' => 1,
                                                '_ff[]' => 'id',
                                                '_ft[]' => 'eq',
                                                '_fc[]' => $person_id,
                                                '_select_columns[]' => ['id', 'lock_version']
                                            ]);
                                            $lock_version = $existing['data'][0]['lock_version'] ?? null;
                                            if ($lock_version !== null) {
                                                $payload['lock_version'] = $lock_version;
                                                $workbooks->assertUpdate('crm/people', [$payload]);
                                                if (function_exists('workbooks_log')) workbooks_log("Updated Workbooks person ID $person_id for test user $email");
                                            }
                                        } else {
                                            $result = $workbooks->assertCreate('crm/people', [$payload]);
                                            if (!empty($result['data'][0]['id'])) {
                                                update_user_meta($test_user_id, 'workbooks_person_id', $result['data'][0]['id']);
                                                update_user_meta($test_user_id, 'workbooks_object_ref', $result['data'][0]['object_ref'] ?? '');
                                                if (function_exists('workbooks_log')) workbooks_log("Created Workbooks person ID {$result['data'][0]['id']} for test user $email");
                                            }
                                        }
                                        $msg = '<div class="updated"><p>Test registration created and synced to Workbooks for user ' . esc_html($email) . '.</p></div>';
                                    } catch (Exception $e) {
                                        $msg = '<div class="error"><p>Workbooks error: ' . esc_html($e->getMessage()) . '</p></div>';
                                        if (function_exists('workbooks_log')) workbooks_log("Test registration failed for $email: " . $e->getMessage());
                                    }
                                }
                            }
                        }
                        echo $msg;
                        ?>
                        <form method="post">
                            <p><label>Title<br><input type="text" name="person_personal_title" value="Mr." readonly></label></p>
                            <p><label>First Name<br><input type="text" name="first_name" value="TestFirst" readonly></label></p>
                            <p><label>Last Name<br><input type="text" name="last_name" value="TestLast" readonly></label></p>
                            <p><label>Email Address<br><input type="email" name="email" value="testuser_<?php echo time(); ?>@supersonicplayground.com" readonly></label></p>
                            <p><label>Employer<br><input type="text" name="employer" value="Supersonic Playground Ltd" readonly></label></p>
                            <p><label>Job Title<br><input type="text" name="job_title" value="Developer" readonly></label></p>
                            <p><label>Town / City<br><input type="text" name="town" value="London" readonly></label></p>
                            <p><label>Country<br><input type="text" name="country" value="United Kingdom" readonly></label></p>
                            <p><label>Telephone<br><input type="text" name="telephone" value="0123456789" readonly></label></p>
                            <p><label>Post / Zip Code<br><input type="text" name="postcode" value="SE10 9XY" readonly></label></p>
                            <fieldset>
                                <legend><strong>Subscriptions</strong></legend>
                                <?php
                                $subs = [
                                    'cf_person_dtr_news' => 'DTR News',
                                    'cf_person_dtr_events' => 'DTR Events',
                                    // 'cf_person_dtr_subscriber' => 'DTR Subscriber', // Not editable, set on registration
                                    'cf_person_dtr_third_party' => 'DTR Third Party',
                                    'cf_person_dtr_webinar' => 'DTR Webinar',
                                ];
                                foreach ($subs as $k => $label): ?>
                                    <label style="display:block;">
                                        <input type="checkbox" name="<?php echo esc_attr($k); ?>" value="1" checked disabled>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <fieldset>
                                <legend><strong>Interests</strong></legend>
                                <?php
                                $interests = [
                                    'cf_person_business' => 'Business',
                                    'cf_person_diseases' => 'Diseases',
                                    'cf_person_drugs_therapies' => 'Drugs & Therapies',
                                    'cf_person_genomics_3774' => 'Genomics',
                                    'cf_person_research_development' => 'Research & Development',
                                    'cf_person_technology' => 'Technology',
                                    'cf_person_tools_techniques' => 'Tools & Techniques',
                                ];
                                foreach ($interests as $k => $label): ?>
                                    <label style="display:block;">
                                        <input type="checkbox" name="<?php echo esc_attr($k); ?>" value="1" checked disabled>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <fieldset>
                                <legend><strong>DTR Required Fields (synced, not editable)</strong></legend>
                                <label>Subscriber Type: <input type="text" value="Prospect" readonly></label><br>
                                <label>Web Member: <input type="text" value="1" readonly></label><br>
                                <label>Lead Source Type: <input type="text" value="Online Registration" readonly></label><br>
                                <label>Active/Inactive: <input type="text" value="Active" readonly></label><br>
                                <label>Data Source Detail: <input type="text" value="DTR Member Registration" readonly></label>
                            </fieldset>
                            <input type="submit" name="dtr_admin_test_reg_submit" value="Run Test Registration" class="button button-primary">
                        </form>
                    <?php } ?>
                </div>
                <!-- Employers Tab -->
                <div id="workbooks-employers-content" class="workbooks-tab-content">
                    <h2>Employers Sync</h2>
                    <div class="employers-actions" style="margin-bottom:20px;">
                        <button id="workbooks_sync_employers" class="button button-primary">Sync All Employers</button>
                        <button id="workbooks_generate_json" class="button button-secondary">Generate JSON from Database</button>
                        <button id="workbooks_load_employers" class="button">Load Employers List</button>
                        <span id="employers-sync-status" style="margin-left:10px;"></span>
                    </div>
                    
                    <div id="employers-sync-progress" style="margin-bottom:20px; display:none;">
                        <progress id="employers-progress-bar" value="0" max="100" style="width:100%;"></progress>
                        <p id="employers-progress-text">Starting sync...</p>
                    </div>
                    
                    <div id="employers-search-container" style="margin-bottom:15px; display:none;">
                        <input type="search" id="employer-search" placeholder="Search employers..." style="width:50%;">
                        <button type="button" id="employer-search-btn" class="button">Search</button>
                        <button type="button" id="employer-reset-btn" class="button">Reset</button>
                        <p><span id="employer-count">0</span> employers found</p>
                    </div>
                    
                    <div id="employers-table-container" style="display:none;">
                        <table class="wp-list-table widefat fixed striped employers-table">
                            <thead>
                                <tr>
                                    <th scope="col" width="10%">ID</th>
                                    <th scope="col" width="60%">Name</th>
                                    <th scope="col" width="20%">Last Updated</th>
                                    <th scope="col" width="10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="employers-table-body">
                                <tr>
                                    <td colspan="4">No employers loaded yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="employers-pagination" style="margin-top:15px; display:none;">
                        <button id="load-more-employers" class="button">Load More</button>
                    </div>
                    
                    <div style="margin-top:20px;">
                        <h3>Last Sync Information</h3>
                        <?php
                        $last_sync = get_option('workbooks_employers_last_sync');
                        if ($last_sync) {
                            echo '<p><strong>Last Sync:</strong> ' . date('Y-m-d H:i:s', $last_sync['time']) . '</p>';
                            echo '<p><strong>Employers Count:</strong> ' . intval($last_sync['count']) . '</p>';
                        } else {
                            echo '<p>No sync has been performed yet.</p>';
                        }
                        ?>
                        <p><strong>Next Scheduled Sync:</strong> 
                        <?php
                        $next_sync = wp_next_scheduled('workbooks_daily_employer_sync');
                        echo $next_sync ? date('Y-m-d H:i:s', $next_sync) : 'Not scheduled';
                        ?>
                        </p>
                    </div>
                </div>

                <!-- Ninja Form Users Tab -->
                <div id="workbooks-ninja-users-content" class="workbooks-tab-content">
                    <h2>Users Created from Ninja Forms Submissions</h2>
                    <?php
                    $args = [
                        'meta_key' => 'created_via_ninja_form',
                        'meta_value' => 1,
                        'orderby' => 'registered',
                        'order' => 'DESC',
                        'number' => 50,
                    ];
                    $users = get_users($args);
                    if (empty($users)) {
                        echo '<p>No users created via Ninja Forms found.</p>';
                    } else {
                        echo '<table class="wp-list-table widefat fixed striped">';
                        echo '<thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Workbooks Person ID</th>
                                <th>Workbooks ID</th>
                                <th>Employer</th>
                            </tr>
                        </thead><tbody>';

                        foreach ($users as $user) {

                            // Workbooks object_ref (Person ID) and Workbooks ID
                            $workbooks_object_ref = get_user_meta($user->ID, 'workbooks_object_ref', true);
                            // Fallback to person_object_ref if needed
                            if (!$workbooks_object_ref) {
                                $workbooks_object_ref = get_user_meta($user->ID, 'person_object_ref', true);
                            }
                            // If still not found, try to fetch from Workbooks API using workbooks_person_id
                            if (!$workbooks_object_ref) {
                                $workbooks_person_id = get_user_meta($user->ID, 'workbooks_person_id', true);
                                $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
                                if ($workbooks && $workbooks_person_id) {
                                    try {
                                        $result = $workbooks->assertGet('crm/people.api', [
                                            '_start' => 0,
                                            '_limit' => 1,
                                            '_ff[]' => 'id',
                                            '_ft[]' => 'eq',
                                            '_fc[]' => $workbooks_person_id
                                        ]);
                                        if (!empty($result['data'][0]['object_ref'])) {
                                            $workbooks_object_ref = $result['data'][0]['object_ref'];
                                            // Optionally update user meta for future
                                            update_user_meta($user->ID, 'workbooks_object_ref', $workbooks_object_ref);
                                        }
                                    } catch (Exception $e) {}
                                }
                            }
                            $workbooks_id = get_user_meta($user->ID, 'workbooks_person_id', true);
                            if (!$workbooks_id) {
                                $workbooks_id = get_user_meta($user->ID, 'workbooks_id', true);
                            }
                            $employer = get_user_meta($user->ID, 'employer_name', true);
                            $subscriptions = get_user_meta($user->ID, 'subscriptions', true);

                            // Format subscriptions (if array, convert to comma-separated string)
                            if (is_array($subscriptions)) {
                                $subscriptions = implode(', ', $subscriptions);
                            }

                            echo '<tr>';
                            echo '<td>' . esc_html($user->ID) . '</td>';
                            echo '<td>' . esc_html($user->user_login) . '</td>';
                            echo '<td>' . esc_html($user->user_email) . '</td>';
                            echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($user->user_registered))) . '</td>';
                            echo '<td>' . esc_html($workbooks_object_ref ?: '-') . '</td>';
                            echo '<td>' . esc_html($workbooks_id ?: '-') . '</td>';
                            echo '<td>' . esc_html($employer ?: '-') . '</td>';
                            echo '</tr>';

                            // Sync, Delete, and Generate IDs button row
                            echo '<tr><td colspan="7">';
                            echo '<form method="post" style="display:inline-block; margin-right:10px;">';
                            echo '<input type="hidden" name="workbooks_sync_user_id" value="' . esc_attr($user->ID) . '">';
                            submit_button('Sync to Workbooks', 'small', 'workbooks_sync_to_workbooks', false);
                            echo '</form>';
                            echo '<form method="post" style="display:inline-block; margin-right:10px;" onsubmit="return confirm(\'Are you sure you want to delete this user?\');">';
                            echo '<input type="hidden" name="delete_user_id" value="' . esc_attr($user->ID) . '">';
                            submit_button('Delete User', 'delete', 'delete_user', false);
                            echo '</form>';
                            if (empty($object_ref) || empty($workbooks_id)) {
                                echo '<form method="post" style="display:inline-block;">';
                                echo '<input type="hidden" name="generate_workbooks_ids_user_id" value="' . esc_attr($user->ID) . '">';
                                submit_button('Generate Workbooks IDs', 'secondary', 'generate_workbooks_ids', false);
                                echo '</form>';
                            }
                            echo '</td></tr>';
                        }

                        echo '</tbody></table>';
                    }
                    ?>
                </div>

                <!-- Topics of Interest Tab -->
                <div id="workbooks-topics-content" class="workbooks-tab-content">
                    <h2>Topics of Interest (TOI) and Areas of Interest (AOI) Mapping</h2>
                    <p>This table shows all available Topics of Interest and their corresponding Areas of Interest mappings. When a user selects a TOI during registration, the corresponding AOI fields will be set to 1 in Workbooks.</p>
                    
                    <?php
                    // Include helper functions
                    if (file_exists(WORKBOOKS_NF_PATH . 'includes/helper-functions.php')) {
                        require_once WORKBOOKS_NF_PATH . 'includes/helper-functions.php';
                    }
                    
                    // Get all TOI options and AOI field names
                    $toi_options = function_exists('dtr_get_all_toi_options') ? dtr_get_all_toi_options() : [];
                    $aoi_field_names = function_exists('dtr_get_aoi_field_names') ? dtr_get_aoi_field_names() : [];
                    
                    if (empty($toi_options)) {
                        echo '<p>No TOI options available.</p>';
                    } else {
                echo '<style>
                    .toi-mapping-table th { background-color: #f1f1f1; }
                    .toi-field-name { font-family: monospace; font-size: 0.9em; color: #666; }
                    .aoi-badges { display: flex; flex-wrap: wrap; gap: 5px; }
                    .aoi-badge { background-color: #0073aa; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.85em; }
                    .no-mapping { color: #999; font-style: italic; }
                </style>';
                
                echo '<table class="wp-list-table widefat fixed striped toi-mapping-table">';
                echo '<thead>
                    <tr>
                        <th style="width: 30%;">Topic of Interest (TOI)</th>
                        <th style="width: 70%;">Mapped Areas of Interest (AOI)</th>
                    </tr>
                </thead><tbody>';

                foreach ($toi_options as $toi_field => $toi_name) {
                    // Get the AOI mapping for this single TOI
                    $aoi_mapping = function_exists('dtr_map_toi_to_aoi') ? dtr_map_toi_to_aoi([$toi_field]) : [];
                    
                    // Find which AOI fields are set to 1
                    $mapped_aois = [];
                    foreach ($aoi_mapping as $aoi_field => $value) {
                        if ($value == 1 && isset($aoi_field_names[$aoi_field])) {
                            $mapped_aois[] = $aoi_field_names[$aoi_field];
                        }
                    }
                    
                    // Count the number of mapped AOIs
                    $aoi_count = count($mapped_aois);
                    $toi_display_name = $toi_name . ' AOI (' . $aoi_count . ')';
                    
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($toi_display_name) . '</strong><br><span class="toi-field-name">' . esc_html($toi_field) . '</span></td>';
                    echo '<td>';
                    if (empty($mapped_aois)) {
                        echo '<span class="no-mapping">No AOI mappings configured</span>';
                    } else {
                        echo '<div class="aoi-badges">';
                        foreach ($mapped_aois as $aoi_name) {
                            echo '<span class="aoi-badge">' . esc_html($aoi_name) . '</span>';
                        }
                        echo '</div>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
                
                echo '<h3 style="margin-top: 30px;">Available AOI Fields</h3>';
                echo '<p>These are all the available Areas of Interest fields that can be mapped to Topics of Interest:</p>';
                echo '<ul>';
                foreach ($aoi_field_names as $aoi_field => $aoi_name) {
                    echo '<li><strong>' . esc_html($aoi_name) . '</strong> <span class="toi-field-name">(' . esc_html($aoi_field) . ')</span></li>';
                }
                echo '</ul>';
            }
            ?>
        </div>

        <!-- Tab UI Script -->
        <script>
        jQuery(document).ready(function($) {
            console.log('Workbooks admin page JavaScript loaded');
            
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                console.log('Tab clicked:', $(this).attr('id'));
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.workbooks-tab-content').removeClass('active');

                var tabId = $(this).attr('id');
                var contentId = '';
                
                if (tabId === 'workbooks-settings-tab') {
                    contentId = 'workbooks-settings-content';
                } else if (tabId === 'workbooks-person-tab') {
                    contentId = 'workbooks-person-content';
                    fetchOrganisations();
                } else if (tabId === 'workbooks-gated-content-tab') {
                    contentId = 'workbooks-gated-content';
                } else if (tabId === 'workbooks-gated-articles-tab') {
                    contentId = 'workbooks-gated-articles-content';
                } else if (tabId === 'workbooks-gated-whitepapers-tab') {
                    contentId = 'workbooks-gated-whitepapers-content';
                } else if (tabId === 'workbooks-gated-news-tab') {
                    contentId = 'workbooks-gated-news-content';
                } else if (tabId === 'workbooks-gated-events-tab') {
                    contentId = 'workbooks-gated-events-content';
                } else if (tabId === 'workbooks-webinar-tab') {
                    contentId = 'workbooks-webinar-content';
                } else if (tabId === 'workbooks-mediaplanner-tab') {
                    contentId = 'workbooks-mediaplanner-content';
                } else if (tabId === 'workbooks-membership-tab') {
                    contentId = 'workbooks-membership-content';
                } else if (tabId === 'workbooks-employers-tab') {
                    contentId = 'workbooks-employers-content';
                } else if (tabId === 'workbooks-ninja-users-tab') {
                    contentId = 'workbooks-ninja-users-content';
                } else if (tabId === 'workbooks-topics-tab') {
                    contentId = 'workbooks-topics-content';
                }
                
                if (contentId) {
                    console.log('Showing content:', contentId);
                    $('#' + contentId).addClass('active');
                } else {
                    console.error('No content ID found for tab:', tabId);
                }
            });

            // Fetch organisations for Person Record tab
            function fetchOrganisations() {
                console.log('Fetching organisations');
                if (typeof workbooks_ajax === 'undefined') {
                    console.log('workbooks_ajax not available');
                    return;
                }
                
                $('#employer-loading').show();
                $.getJSON(workbooks_ajax.plugin_url + 'employers.json', function(data) {
                    var $select = $('#employer');
                    $select.empty().append('<option value="">-- Select Employer --</option>');
                    $('#employer-loading').hide();
                    if (data && Array.isArray(data)) {
                        $.each(data, function(index, org) {
                            $select.append('<option value="' + org.name + '">' + org.name + '</option>');
                        });
                    } else {
                        console.error('Invalid or empty employers.json data');
                        fetchOrganisationsFromAjax();
                    }
                }).fail(function() {
                    console.error('Failed to load employers.json');
                    fetchOrganisationsFromAjax();
                });
            }

            // Fallback function to fetch organisations via AJAX
            function fetchOrganisationsFromAjax() {
                $('#employer-loading').show();
                $.post(workbooks_ajax.ajax_url, {
                    action: 'fetch_workbooks_organisations',
                    nonce: workbooks_ajax.nonce,
                }, function(response) {
                    var $select = $('#employer');
                    $select.empty().append('<option value="">-- Select Employer --</option>');
                    $('#employer-loading').hide();
                    if (response.success && response.data.length) {
                        $.each(response.data, function(index, org) {
                            $select.append('<option value="' + org.name + '">' + org.name + '</option>');
                        });
                    } else {
                        console.error('Error loading organisations:', response.data);
                        $select.append('<option value="">No employers found</option>');
                    }
                }).fail(function() {
                    console.error('AJAX request failed for organisations');
                    $('#employer-loading').hide();
                    $('#employer').append('<option value="">Error loading employers</option>');
                });
            }

            // Handle Generate JSON button click
            $('#workbooks_generate_json').on('click', function() {
                var $button = $(this);
                var $status = $('#employers-sync-status');
                
                $button.prop('disabled', true);
                $status.html('<span style="color:#666;">Generating JSON...</span>');
                
                $.post(workbooks_ajax.ajax_url, {
                    action: 'workbooks_generate_employers_json',
                    nonce: workbooks_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $status.html('<span style="color:green;">' + response.data + '</span>');
                    } else {
                        $status.html('<span style="color:red;">Error: ' + response.data + '</span>');
                    }
                    $button.prop('disabled', false);
                }).fail(function() {
                    $status.html('<span style="color:red;">Request failed. Please try again.</span>');
                    $button.prop('disabled', false);
                });
            });

            // Fetch logical databases
            function fetchDatabases() {
                $.post(workbooks_ajax.ajax_url, {
                    action: 'fetch_workbooks_databases',
                    nonce: workbooks_ajax.nonce,
                }, function(response) {
                    var $select = $('#workbooks_logical_database_id');
                    $select.empty();
                    if (response.success) {
                        if (response.data.no_selection_required) {
                            $select.append('<option value="">No database selection required</option>');
                            $select.prop('disabled', true);
                        } else if (response.data.length) {
                            $select.append('<option value="">-- Select a Logical Database --</option>');
                            $.each(response.data, function(index, db) {
                                $select.append('<option value="' + db.logical_database_id + '">' + db.name + '</option>');
                            });
                            $select.prop('disabled', false);
                            // Set the saved value if it exists
                            var savedValue = <?php echo json_encode(esc_attr(get_option('workbooks_logical_database_id'))); ?>;
                            if (savedValue) {
                                $select.val(savedValue);
                            }
                        } else {
                            $select.append('<option value="">No databases found</option>');
                            $select.prop('disabled', true);
                        }
                    } else {
                        $select.append('<option value="">Error loading databases</option>');
                        console.error('Error loading databases:', response.data);
                        $select.prop('disabled', true);
                    }
                });
            }
            
            // Fetch databases on page load
            fetchDatabases();
            
            // Fetch organisations when Person Record tab is active
            if ($('#workbooks-person-content').is(':visible')) {
                fetchOrganisations();
            }
            
            // Fetch organisations when Person Record tab is clicked
            $('#workbooks-person-tab').click(function() {
                fetchOrganisations();
            });
            
            // Update form submission to use AJAX
            $('#workbooks_update_user_form').on('submit', function(e) {
                e.preventDefault();
                var formData = $(this).serialize();
                formData += '&action=workbooks_update_user&nonce=' + workbooks_ajax.nonce;
                
                $.post(workbooks_ajax.ajax_url, formData, function(response) {
                    if (response.success) {
                        alert('Success: ' + response.data);
                    } else {
                        alert('Error: ' + response.data);
                    }
                }).fail(function() {
                    alert('Request failed. Please try again.');
                });
            });
            
            // Test connection button
            $('#workbooks_test_connection').on('click', function() {
                var $button = $(this);
                var $result = $('#workbooks_test_result');
                
                $button.prop('disabled', true);
                $result.html('<span style="color:#666;">Testing connection...</span>');
                
                $.post(workbooks_ajax.ajax_url, {
                    action: 'workbooks_test_connection',
                    nonce: workbooks_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $result.html('<span style="color:green;">Success: ' + response.data + '</span>');
                    } else {
                        $result.html('<span style="color:red;">Error: ' + response.data + '</span>');
                    }
                    $button.prop('disabled', false);
                }).fail(function() {
                    $result.html('<span style="color:red;">Request failed. Please check your settings and try again.</span>');
                    $button.prop('disabled', false);
                });
            });
        });
        </script>
            </div> <!-- /workbooks-content-area -->
        </div> <!-- /workbooks-admin-container -->
    </div> <!-- /wrap -->
    <?php
}

// Ajax: fetch logical databases (for dropdown)
add_action('wp_ajax_fetch_workbooks_databases', function() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $api_key = get_option('workbooks_api_key');
    $api_url = get_option('workbooks_api_url');
    if (!$api_key || !$api_url) {
        wp_send_json_error('Missing API URL or Key');
    }
    $workbooks = new WorkbooksApi([
        'application_name' => 'wp_workbooks_plugin',
        'user_agent' => 'wp_workbooks_plugin/1.0',
        'service' => $api_url,
        'json_utf8_encoding' => true,
        'request_timeout' => 30,
        'verify_peer' => false,
    ]);
    $login_response = $workbooks->login(['api_key' => $api_key]);
    if ($login_response['http_status'] == WorkbooksApi::HTTP_STATUS_FORBIDDEN && !empty($login_response['response']['databases'])) {
        wp_send_json_success($login_response['response']['databases']);
    } elseif ($login_response['http_status'] == WorkbooksApi::HTTP_STATUS_OK) {
        wp_send_json_success(['no_selection_required' => true]);
    } else {
        wp_send_json_error('Login failed: ' . print_r($login_response, true));
    }
});

// Ajax: update person record & WP user meta
add_action('wp_ajax_workbooks_update_user', function() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied.');
    }
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    if (!$user) {
        wp_send_json_error('User not found.');
    }
    // Sanitize input
    $person_id    = sanitize_text_field($_POST['person_id'] ?? '');
    $lock_version = sanitize_text_field($_POST['lock_version'] ?? '');
    $first_name = sanitize_text_field($_POST['person_first_name'] ?? '');
    $last_name  = sanitize_text_field($_POST['person_last_name'] ?? '');
    $title      = sanitize_text_field($_POST['person_personal_title'] ?? ''); // Correct field name from form
    $job_title  = sanitize_text_field($_POST['person_job_title'] ?? '');
    $email      = $user->user_email;  // readonly from WP user
    $telephone  = sanitize_text_field($_POST['telephone'] ?? '');
    $country    = sanitize_text_field($_POST['country'] ?? '');
    $town       = sanitize_text_field($_POST['town'] ?? '');
    $postcode   = sanitize_text_field($_POST['postcode'] ?? '');
    $employer_name = sanitize_text_field($_POST['employer_name'] ?? '');
    $payload = [
        'name' => trim("$first_name $last_name"),
        'person_first_name' => $first_name,
        'person_last_name' => $last_name,
        'person_personal_title' => $title, // Correct Workbooks field for title
        'person_job_title' => $job_title,
        'main_location[email]' => $email,
        'main_location[telephone]' => $telephone,
        'main_location[country]' => 'South Africa', // Always use full country name for Workbooks
        'main_location[town]' => $town,
        'main_location[postcode]' => $postcode,
        'created_through_reference' => 'wp_user_' . $user_id,
    ];
    // DTR marketing preferences
    $dtr_fields = [
        'cf_person_dtr_news',
        'cf_person_dtr_events',
        // 'cf_person_dtr_subscriber' => 'DTR Subscriber', // Not editable, set on registration
        'cf_person_dtr_third_party',
        'cf_person_dtr_webinar',
    ];
    foreach ($dtr_fields as $field) {
        $value = isset($_POST[$field]) ? 1 : 0;
        update_user_meta($user_id, $field, $value);
        $payload[$field] = $value;
    }

    // DTR areas of interest (interests checkboxes)
    $interests_fields = [
        'cf_person_business',
        'cf_person_diseases',
        'cf_person_drugs_therapies',
        'cf_person_genomics',
        'cf_person_research_development',
        'cf_person_technology',
        'cf_person_tools_techniques',
    ];
    foreach ($interests_fields as $field) {
        $value = isset($_POST[$field]) ? 1 : 0;
        update_user_meta($user_id, $field, $value);
        $payload[$field] = $value;
    }
    // Add employer organisation ID
    $organisation_id = workbooks_get_or_create_organisation_id($employer_name);
    if ($organisation_id) {
        $payload['main_employer'] = $organisation_id;
    }
    if ($person_id && $lock_version) {
        $payload['id'] = $person_id;
        $payload['lock_version'] = $lock_version;
    }
    $workbooks = get_workbooks_instance();
    try {
        $objs = [$payload];
        if ($person_id && $lock_version) {
            $response = $workbooks->assertUpdate('crm/people', $objs);
        } else {
            $response = $workbooks->assertCreate('crm/people', $objs);
        }
        // Update WP user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'person_personal_title', $title); // Save correct meta key
        update_user_meta($user_id, 'job_title', $job_title);
        update_user_meta($user_id, 'telephone', $telephone);
        update_user_meta($user_id, 'country', $country);
        update_user_meta($user_id, 'town', $town);
        update_user_meta($user_id, 'postcode', $postcode);
        if ($employer_name) {
            update_user_meta($user_id, 'employer_name', $employer_name);
        }
        wp_send_json_success('Workbooks record updated and user meta saved.');
    } catch (Exception $e) {
        wp_send_json_error('Exception: ' . $e->getMessage());
    }
});

// Include AJAX handlers for webinars and ACF fields
if (file_exists(WORKBOOKS_NF_PATH . 'includes/ajax-handlers.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/ajax-handlers.php';
}


// --- Ninja Forms User Registration and Workbooks Sync ---
// Hook into Ninja Forms submission - TEMPORARILY DISABLED
// add_action('ninja_forms_after_submission', 'dtr_ninja_forms_submission_handler', 10, 1);

// AJAX handlers for manual calls
add_action('wp_ajax_nopriv_dtr_ninja_register_user', 'dtr_ninja_register_user_handler');
add_action('wp_ajax_dtr_ninja_register_user', 'dtr_ninja_register_user_handler');

if (!function_exists('dtr_ninja_register_user_handler')) {
function dtr_ninja_register_user_handler() {
    // Add error logging
    try {
        // Log full POST for debugging
        if (function_exists('workbooks_log')) workbooks_log('NF POST: ' . print_r($_POST, true));
        
        // Validate nonce if sent
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'workbooks_nonce')) {
            wp_send_json_error('Invalid nonce.');
        }
    
    $email = sanitize_email($_POST['email'] ?? '');
    if (!$email || email_exists($email)) {
        wp_send_json_error('Email is required and must not already exist.');
    }
    
    $first = sanitize_text_field($_POST['first_name'] ?? '');
    $last = sanitize_text_field($_POST['last_name'] ?? '');
    $employer = sanitize_text_field($_POST['employer'] ?? '');
    $town = sanitize_text_field($_POST['town'] ?? '');
    $country = 'South Africa'; // Always use full country name for Workbooks
    $telephone = sanitize_text_field($_POST['telephone'] ?? '');
    $postcode = sanitize_text_field($_POST['postcode'] ?? '');
    $job_title = sanitize_text_field($_POST['job_title'] ?? '');
    
    // Handle title from field ID 291 (from debug log)
    $title = '';
    if (isset($_POST['fields']) && isset($_POST['fields']['291'])) {
        $title = sanitize_text_field($_POST['fields']['291']);
        if (function_exists('workbooks_log')) workbooks_log('Title from fields[291]: ' . $title);
    } else {
        $title = sanitize_text_field($_POST['person_personal_title'] ?? $_POST['title'] ?? '');
        if (function_exists('workbooks_log')) workbooks_log('Title from direct POST: ' . $title);
    }
    
    if (function_exists('workbooks_log')) workbooks_log('Final title value: ' . $title);
    
    $random_pass = wp_generate_password(12, false);
    $user_id = wp_create_user($email, $random_pass, $email);
    if (is_wp_error($user_id)) {
        wp_send_json_error('Could not create user: ' . $user_id->get_error_message());
    }
    
    // Map Ninja Forms checkbox values to DTR fields
    $dtr_fields = [
        'cf_person_dtr_news' => 'newsletter-news-articles-and-analysis-by-email',
        'cf_person_dtr_events' => 'event-information-about-events-by-email',
        'cf_person_dtr_third_party' => 'third-party-application-notes-product-developments-and-updates-from-our-trusted-partners-by-email',
        'cf_person_dtr_webinar' => 'webinar-information-about-webinars-by-email',
    ];
    
    $interests_fields = [
        'cf_person_business' => 'business',
        'cf_person_diseases' => 'diseases',
        'cf_person_drugs_therapies' => 'drugs-and-therapies',
        'cf_person_genomics' => 'genomics',
        'cf_person_research_development' => 'research-and-development',
        'cf_person_technology' => 'technology',
        'cf_person_tools_techniques' => 'tools-and-techniques',
    ];
    
    $meta_fields = [
        'first_name' => $first,
        'last_name' => $last,
        'employer' => $employer,
        'employer_name' => $employer, // Keep this for WP meta
        'town' => $town,
        'country' => $country,
        'telephone' => $telephone,
        'postcode' => $postcode,
        'job_title' => $job_title,
        'person_personal_title' => $title,
        'created_via_ninja_form' => 1,
        'cf_person_dtr_subscriber_type' => 'Prospect',
        'cf_person_dtr_web_member' => 1,
        'lead_source_type' => 'Online Registration',
        'cf_person_is_person_active_or_inactive' => 'Active',
        'cf_person_data_source_detail' => 'DTR Member Registration'
    ];
    
    foreach ($meta_fields as $k => $v) {
        update_user_meta($user_id, $k, $v);
    }
    
    // Handle marketing preferences from fields structure
    $nf_marketing = [];
    if (isset($_POST['fields'])) {
        // Look for marketing preference fields in the fields array
        foreach ($_POST['fields'] as $field_id => $field_value) {
            if (is_array($field_value)) {
                $nf_marketing = array_merge($nf_marketing, $field_value);
            } elseif (!empty($field_value) && in_array($field_value, array_values($dtr_fields))) {
                $nf_marketing[] = $field_value;
            }
        }
    }
    // Also check direct POST values
    if (isset($_POST['marketing_preferences'])) {
        $nf_marketing = array_merge($nf_marketing, (array)$_POST['marketing_preferences']);
    }
    
    foreach ($dtr_fields as $field => $nf_value) {
        $value = in_array($nf_value, $nf_marketing) ? 1 : 0;
        update_user_meta($user_id, $field, $value);
    }
    
    // Handle interests from fields structure
    $nf_interests = [];
    if (isset($_POST['fields'])) {
        // Look for interest fields in the fields array
        foreach ($_POST['fields'] as $field_id => $field_value) {
            if (is_array($field_value)) {
                $nf_interests = array_merge($nf_interests, $field_value);
            } elseif (!empty($field_value) && in_array($field_value, array_values($interests_fields))) {
                $nf_interests[] = $field_value;
            }
        }
    }
    // Also check direct POST values
    if (isset($_POST['select_interest'])) {
        $nf_interests = array_merge($nf_interests, (array)$_POST['select_interest']);
    }
    
    $selected_toi_fields = [];
    foreach ($interests_fields as $field => $nf_value) {
        $value = in_array($nf_value, $nf_interests) ? 1 : 0;
        update_user_meta($user_id, $field, $value);
        if ($value) $selected_toi_fields[] = $field;
    }
    
    // Map TOI to AOI and save AOI fields
    if (function_exists('dtr_map_toi_to_aoi') && !empty($selected_toi_fields)) {
        $aoi_mapping = dtr_map_toi_to_aoi($selected_toi_fields);
        foreach ($aoi_mapping as $aoi_field => $aoi_value) {
            update_user_meta($user_id, $aoi_field, $aoi_value);
        }
        if (function_exists('workbooks_log')) {
            workbooks_log('TOI to AOI mapping applied for user ' . $user_id . ': ' . print_r($aoi_mapping, true));
        }
    }
    
    // Prepare Workbooks payload
    $payload = [
        'person_first_name' => $first,
        'person_last_name' => $last,
        'name' => trim("$first $last"),
        'main_location[email]' => $email,
        'employer_name' => $employer, // Use editable employer_name field
        'created_through_reference' => 'wp_user_' . $user_id,
        'main_location[town]' => $town,
        'main_location[country]' => 'South Africa',
        'main_location[telephone]' => $telephone,
        'main_location[postcode]' => $postcode,
        'person_job_title' => $job_title,
        'person_personal_title' => $title,
        'cf_person_dtr_subscriber_type' => 'Prospect',
        'cf_person_dtr_web_member' => 1,
        'lead_source_type' => 'Online Registration',
        'cf_person_is_person_active_or_inactive' => 'Active',
        'cf_person_data_source_detail' => 'DTR Member Registration'
    ];
    
    // Add marketing preferences to payload
    foreach ($dtr_fields as $field => $nf_value) {
        $payload[$field] = get_user_meta($user_id, $field, true) ? 1 : 0;
    }
    
    // Add interests to payload
    foreach ($interests_fields as $field => $nf_value) {
        $payload[$field] = get_user_meta($user_id, $field, true) ? 1 : 0;
    }
    
    // Include AOI fields in Workbooks payload
    if (function_exists('dtr_get_aoi_field_names')) {
        $aoi_fields = array_keys(dtr_get_aoi_field_names());
        foreach ($aoi_fields as $aoi_field) {
            $payload[$aoi_field] = get_user_meta($user_id, $aoi_field, true) ? 1 : 0;
        }
    }
    
    // Add organisation if available
    $org_id = function_exists('workbooks_get_or_create_organisation_id') ? workbooks_get_or_create_organisation_id($employer) : null;
    if ($org_id) {
        $payload['main_employer'] = $org_id;
    }
    
    // Log payload for debugging
    if (function_exists('workbooks_log')) {
        workbooks_log('Workbooks payload: ' . print_r($payload, true));
    }
    
    $workbooks = get_workbooks_instance();
    if (!$workbooks) {
        wp_send_json_error('Workbooks API initialization failed.');
    }
    
    try {
        if (function_exists('workbooks_log')) workbooks_log('Attempting Workbooks API call...');
        $result = $workbooks->assertCreate('crm/people', [$payload]);
        if (function_exists('workbooks_log')) workbooks_log('Workbooks API call successful');
        
        if (!empty($result['data'][0]['id'])) {
            update_user_meta($user_id, 'workbooks_person_id', $result['data'][0]['id']);
            update_user_meta($user_id, 'workbooks_object_ref', $result['data'][0]['object_ref'] ?? '');
            if (function_exists('workbooks_log')) workbooks_log('User meta updated with Workbooks IDs');
        }
        // Log full response for debugging
        if (function_exists('workbooks_log')) workbooks_log('Workbooks person create response: ' . print_r($result, true));
        wp_send_json_success([
            'user_id' => $user_id, 
            'workbooks_person_id' => $result['data'][0]['id'] ?? '', 
            'workbooks_object_ref' => $result['data'][0]['object_ref'] ?? '', 
            'workbooks_response' => $result
        ]);
    } catch (Exception $e) {
        if (function_exists('workbooks_log')) workbooks_log('Workbooks error: ' . $e->getMessage());
        if (function_exists('workbooks_log')) workbooks_log('Full exception: ' . print_r($e, true));
        wp_send_json_error('Workbooks error: ' . $e->getMessage());
    }
} catch (Exception $global_e) {
    if (function_exists('workbooks_log')) workbooks_log('Global error in registration handler: ' . $global_e->getMessage());
    if (function_exists('workbooks_log')) workbooks_log('Global exception: ' . print_r($global_e, true));
    wp_send_json_error('Registration error: ' . $global_e->getMessage());
}
}
}

// Ninja Forms submission handler - converts form data to AJAX format and calls the main handler
if (!function_exists('dtr_ninja_forms_submission_handler')) {
function dtr_ninja_forms_submission_handler($form_data) {
    if (function_exists('workbooks_log')) workbooks_log('=== Ninja Forms submission detected ===');
    if (function_exists('workbooks_log')) workbooks_log('Form data: ' . print_r($form_data, true));
    
    // Only process form ID 15 (registration form)
    if (!isset($form_data['form_id']) || $form_data['form_id'] != 15) {
        if (function_exists('workbooks_log')) workbooks_log('Skipping form - not registration form (ID 15)');
        return;
    }
    
    // Extract fields by key
    $fields = [];
    foreach ($form_data['fields'] as $field) {
        $fields[$field['key']] = $field['value'];
    }
    
    if (function_exists('workbooks_log')) workbooks_log('Extracted fields: ' . print_r($fields, true));
    
    // Convert to $_POST format that the AJAX handler expects
    $backup_post = $_POST;
    $_POST = [
        'first' => $fields['first_name'] ?? '',
        'last' => $fields['last_name'] ?? '',
        'email' => $fields['email_address'] ?? '',
        'employer' => $fields['employer'] ?? '',
        'title' => $fields['title'] ?? '',
        'telephone' => $fields['telephone'] ?? '',
        'country' => $fields['country'] ?? '',
        'town' => $fields['town'] ?? '',
        'postcode' => $fields['postcode'] ?? '',
        'job_title' => $fields['job_title'] ?? '',
        'marketing_preferences' => $fields['marketing_preferences'] ?? [],
        'topics_of_interest' => $fields['topics_of_interest'] ?? [],
        'action' => 'dtr_ninja_register_user'
    ];
    
    if (function_exists('workbooks_log')) workbooks_log('Converted POST data: ' . print_r($_POST, true));
    
    // Capture output instead of sending JSON response
    ob_start();
    try {
        dtr_ninja_register_user_handler();
        $output = ob_get_clean();
        if (function_exists('workbooks_log')) workbooks_log('Handler completed successfully. Output: ' . $output);
    } catch (Exception $e) {
        ob_end_clean();
        if (function_exists('workbooks_log')) workbooks_log('Handler failed: ' . $e->getMessage());
    }
    
    // Restore original $_POST
    $_POST = $backup_post;
    
    if (function_exists('workbooks_log')) workbooks_log('=== Ninja Forms submission processing complete ===');
}
}

// Re-enable to load the disabled integration (action hooks are commented out)
if (file_exists(WORKBOOKS_NF_PATH . 'includes/ninjaforms-workbooks-integration.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/ninjaforms-workbooks-integration.php';
}

// Temporarily disable the old nf-user-register.php to prevent conflicts
require_once WORKBOOKS_NF_PATH . 'includes/nf-user-register.php';
require_once WORKBOOKS_NF_PATH . 'includes/workbooks-user-sync.php';

// Gated Content page functions
function workbooks_gated_content_main_page() {
    ?>
    <div class="wrap">
        <h1>Gated Content Management</h1>
        <p>Manage gated content settings for different post types. Use the submenu items to configure individual post types.</p>
        
        <div class="workbooks-gated-overview">
            <h2>Overview</h2>
            <div class="gated-stats">
                <?php
                $post_types = ['articles', 'whitepapers', 'news', 'events'];
                foreach ($post_types as $post_type) {
                    $posts = get_posts([
                        'post_type' => $post_type,
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'meta_query' => [
                            [
                                'key' => 'gate_content',
                                'value' => '1',
                                'compare' => '='
                            ]
                        ]
                    ]);
                    $count = count($posts);
                    $total = wp_count_posts($post_type)->publish;
                    
                    echo '<div class="stat-box">';
                    echo '<h3>' . ucfirst($post_type) . '</h3>';
                    echo '<p><strong>' . $count . '</strong> gated out of <strong>' . $total . '</strong> total</p>';
                    echo '<a href="admin.php?page=workbooks-gated-' . $post_type . '" class="button">Manage ' . ucfirst($post_type) . '</a>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <style>
        .gated-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        .stat-box h3 {
            margin-top: 0;
            color: #23282d;
        }
        .stat-box p {
            font-size: 16px;
            margin: 15px 0;
        }
        </style>
    </div>
    <?php
}

function workbooks_gated_articles_page() {
    workbooks_render_gated_content_page('articles');
}

function workbooks_gated_whitepapers_page() {
    workbooks_render_gated_content_page('whitepapers');
}

function workbooks_gated_news_page() {
    workbooks_render_gated_content_page('news');
}

function workbooks_gated_events_page() {
    workbooks_render_gated_content_page('events');
}

function workbooks_render_gated_content_page($current_post_type) {
    ?>
    <div class="wrap workbooks-tab-content">
        <h2 class="workbooks-tab-content"><?php echo ucfirst($current_post_type); ?> Gated Content</h2>
        <?php
        // Include the single post type template
        if (file_exists(WORKBOOKS_NF_PATH . 'admin/gated-content-single.php')) {
            include WORKBOOKS_NF_PATH . 'admin/gated-content-single.php';
        } else {
            echo '<p>Gated content template not found.</p>';
        }
        ?>
    </div>
    <?php
}

// Enqueue admin JS for gated content articles page only
add_action('admin_enqueue_scripts', function($hook) {
    if (isset($_GET['page']) && $_GET['page'] === 'workbooks-gated-articles') {
        wp_enqueue_script(
            'gated-content-admin',
            plugins_url('assets/gated-content-admin.js', __FILE__),
            array('jquery'),
            null,
            true
        );
        wp_localize_script('gated-content-admin', 'gatedContentNonce', array(
            'nonce' => wp_create_nonce('gated_content_nonce')
        ));
    }
});

// Include simple Ninja Forms webinar hook
// Clean ninja forms webinar hook that works exactly like the successful form
if (file_exists(WORKBOOKS_NF_PATH . 'includes/ninja-forms-simple-hook.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/ninja-forms-simple-hook.php';
}

?>