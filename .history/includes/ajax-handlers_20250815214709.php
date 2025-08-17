<?php
if (!defined('ABSPATH')) exit;

// Test connection handler
add_action('wp_ajax_workbooks_test_connection', 'workbooks_test_connection_callback');
function workbooks_test_connection_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    try {
        $workbooks = get_workbooks_instance();
        // Try a simple API call to test the connection - using crm/people endpoint
        $response = $workbooks->assertGet('crm/people.api', [ '_limit' => 1 ]);
        if (isset($response['data'])) {
            wp_send_json_success('Connection successful! API is working correctly.');
        } else {
            wp_send_json_error('API responded but data format unexpected.');
        }
    } catch (Exception $e) {
        wp_send_json_error('Connection failed: ' . $e->getMessage());
    }
}

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
        // 'campaign_reference' => $webinar_fields['campaign_reference'] ?? '', // COMMENTED OUT: Not used
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


// --- Logging functions remain unchanged ---

function dtr_log_to_file($message) {
    $log_file = dirname(__DIR__) . '/logs/workbooks-2025-06-27.log';
    
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND);
}

function dtr_webinar_debug_log($level, $message, $data = []) {
    $log_file = WORKBOOKS_NF_PATH . 'logs/webinar-debug.log';
    
    // Ensure the logs directory exists
    $logs_dir = dirname($log_file);
    if (!file_exists($logs_dir)) {
        wp_mkdir_p($logs_dir);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] [$level] $message";
    if (!empty($data)) {
        $entry .= ' | ' . json_encode($data);
    }
    file_put_contents($log_file, $entry . PHP_EOL, FILE_APPEND);
}

// --- All event/ticket/lead creation code is commented out ---


// // Webinar register handler (with nonce check) -- NOT USED ANYMORE
// add_action('wp_ajax_workbooks_webinar_register', 'workbooks_webinar_register_callback');
// add_action('wp_ajax_nopriv_workbooks_webinar_register', 'workbooks_webinar_register_callback');
// function workbooks_webinar_register_callback() { /* ... */ }

// // Function dtr_register_workbooks_webinar(...) -- NOT USED ANYMORE

// // Function dtr_register_workbooks_lead(...) -- NOT USED ANYMORE

// --- end file ---