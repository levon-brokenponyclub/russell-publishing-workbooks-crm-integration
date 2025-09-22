<?php
/**
 * AJAX handlers for employer search autocomplete
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Log that this file is being loaded
error_log('DTR AJAX: ajax-employer-search.php loaded successfully');

/**
 * Search employers via AJAX - Optimized for large datasets
 */
function ajax_search_workbooks_employers() {
    // Debug logging
    error_log('DTR AJAX: search_workbooks_employers called');
    error_log('DTR AJAX POST data: ' . print_r($_POST, true));
    
    // Check if nonce exists
    if (!isset($_POST['nonce'])) {
        error_log('DTR AJAX: No nonce provided');
        wp_send_json_error('No security nonce provided');
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'workbooks_employer_search')) {
        error_log('DTR AJAX: Invalid nonce - provided: ' . $_POST['nonce']);
        wp_send_json_error('Security check failed');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'workbooks_employers';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) {
        error_log('DTR AJAX: Employers table does not exist: ' . $table);
        wp_send_json_error('Employers database not available');
    }
    
    $query = sanitize_text_field($_POST['query']);
    $limit = 50; // Limit results for performance
    
    if (empty($query) || strlen($query) < 2) {
        error_log('DTR AJAX: Query too short: ' . $query);
        wp_send_json_error('Query must be at least 2 characters');
    }

    // Optimized search with prepared statement and indexing considerations
    // Uses MySQL FULLTEXT search if available, falls back to LIKE
    $search_term = $wpdb->esc_like($query);
    
    // Try exact match first for better UX
    $exact_match = $wpdb->prepare(
        "SELECT name FROM $table WHERE name = %s LIMIT 1",
        $query
    );
    $exact = $wpdb->get_var($exact_match);
    
    // Main search query with scoring for relevance
    $search_query = $wpdb->prepare(
        "SELECT name,
           CASE 
             WHEN name LIKE %s THEN 1
             WHEN name LIKE %s THEN 2
             WHEN name LIKE %s THEN 3
             ELSE 4
           END as relevance
         FROM $table 
         WHERE name LIKE %s 
         ORDER BY relevance ASC, name ASC
         LIMIT %d",
        $search_term . '%',         // starts with
        '% ' . $search_term . '%',  // word boundary
        '%' . $search_term . '%',   // contains anywhere
        '%' . $search_term . '%',   // overall filter
        $limit
    );

    $employers = $wpdb->get_col($search_query);

    if ($employers === false) {
        error_log('Database error in employer search: ' . $wpdb->last_error);
        wp_send_json_error('Database error occurred');
    }

    // Add exact match at top if not already included
    if ($exact && !in_array($exact, $employers)) {
        array_unshift($employers, $exact);
        $employers = array_slice($employers, 0, $limit);
    }

    error_log('DTR AJAX: Found ' . count($employers) . ' employers for query: ' . $query);

    // Return results with metadata for debugging
    wp_send_json_success([
        'employers' => $employers,
        'count' => count($employers),
        'query' => $query
    ]);
}

// Register AJAX handlers
add_action('wp_ajax_search_workbooks_employers', 'ajax_search_workbooks_employers');
add_action('wp_ajax_nopriv_search_workbooks_employers', 'ajax_search_workbooks_employers');
error_log('DTR AJAX: search_workbooks_employers actions registered');

/**
 * Get top employers for initial dropdown display
 */
function ajax_get_top_employers() {
    // Debug logging
    error_log('DTR AJAX: get_top_employers called');
    error_log('DTR AJAX POST data: ' . print_r($_POST, true));
    
    // Check if nonce exists
    if (!isset($_POST['nonce'])) {
        error_log('DTR AJAX: No nonce provided for top employers');
        wp_send_json_error('No security nonce provided');
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'workbooks_employer_search')) {
        error_log('DTR AJAX: Invalid nonce for top employers - provided: ' . $_POST['nonce']);
        wp_send_json_error('Security check failed');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'workbooks_employers';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) {
        error_log('DTR AJAX: Employers table does not exist: ' . $table);
        wp_send_json_error('Employers database not available');
    }
    
    $limit = 20; // Show top 20 employers initially
    
    // Get popular/top employers - you can modify this logic based on your needs
    // For now, just get the first 20 alphabetically
    $query = $wpdb->prepare(
        "SELECT name FROM $table 
         ORDER BY name ASC 
         LIMIT %d",
        $limit
    );

    $employers = $wpdb->get_col($query);

    if ($employers === false) {
        error_log('Database error in top employers: ' . $wpdb->last_error);
        wp_send_json_error('Database error occurred');
    }

    error_log('DTR AJAX: Found ' . count($employers) . ' top employers');

    // Return results with consistent format
    wp_send_json_success([
        'employers' => $employers,
        'count' => count($employers),
        'type' => 'top_employers'
    ]);
}

// Register AJAX handlers
add_action('wp_ajax_get_top_employers', 'ajax_get_top_employers');
add_action('wp_ajax_nopriv_get_top_employers', 'ajax_get_top_employers');
error_log('DTR AJAX: get_top_employers actions registered');

/**
 * Get employer suggestions based on partial match
 * This is more advanced - could include popularity scoring, etc.
 */
function ajax_get_employer_suggestions() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'workbooks_employer_search')) {
        wp_die('Security check failed');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'workbooks_employers';
    
    $query = sanitize_text_field($_POST['query']);
    $limit = 10;
    
    if (empty($query) || strlen($query) < 2) {
        wp_send_json_error('Query must be at least 2 characters');
    }

    // Advanced search with weighted results
    // Exact matches first, then starts with, then contains
    $search_query = $wpdb->prepare(
        "SELECT name,
           CASE 
             WHEN name = %s THEN 1
             WHEN name LIKE %s THEN 2
             WHEN name LIKE %s THEN 3
             ELSE 4
           END as relevance
         FROM $table 
         WHERE name LIKE %s 
         ORDER BY relevance ASC, name ASC
         LIMIT %d",
        $query,
        $wpdb->esc_like($query) . '%',
        '%' . $wpdb->esc_like($query) . '%',
        '%' . $wpdb->esc_like($query) . '%',
        $limit
    );

    $results = $wpdb->get_col($search_query);

    if ($results === false) {
        error_log('Database error in employer suggestions: ' . $wpdb->last_error);
        wp_send_json_error('Database error occurred');
    }

    wp_send_json_success($results);
}

// Register AJAX handlers
add_action('wp_ajax_get_employer_suggestions', 'ajax_get_employer_suggestions');
add_action('wp_ajax_nopriv_get_employer_suggestions', 'ajax_get_employer_suggestions');

/**
 * Test AJAX endpoint to verify connectivity
 */
function ajax_test_employer_endpoint() {
    error_log('DTR AJAX: test endpoint called');
    wp_send_json_success([
        'message' => 'AJAX endpoint is working',
        'timestamp' => current_time('mysql'),
        'post_data' => $_POST
    ]);
}

// Register test AJAX handlers
add_action('wp_ajax_test_employer_endpoint', 'ajax_test_employer_endpoint');
add_action('wp_ajax_nopriv_test_employer_endpoint', 'ajax_test_employer_endpoint');
error_log('DTR AJAX: test_employer_endpoint actions registered');

/**
 * DTR Plugin test endpoint - check if plugin functions are available
 */
function ajax_test_dtr_plugin() {
    $plugin_active = function_exists('dtr_html_form_submit_handler') || 
                     has_action('wp_ajax_dtr_html_form_submit') ||
                     defined('DTR_WORKBOOKS_VERSION');
    
    $html_form_submit_registered = has_action('wp_ajax_dtr_html_form_submit');
    error_log('DTR Status Check: wp_ajax_dtr_html_form_submit registered: ' . ($html_form_submit_registered ? 'YES' : 'NO'));
    error_log('DTR Status Check: function dtr_html_form_submit_handler exists: ' . (function_exists('dtr_html_form_submit_handler') ? 'YES' : 'NO'));
    
    wp_send_json([
        'success' => true,
        'plugin_active' => $plugin_active,
        'dtr_version' => defined('DTR_WORKBOOKS_VERSION') ? DTR_WORKBOOKS_VERSION : 'not found',
        'actions_registered' => [
            'html_form_submit' => $html_form_submit_registered,
            'dtr_html_form_submit' => $html_form_submit_registered,
            'search_workbooks_employers' => has_action('wp_ajax_search_workbooks_employers'),
            'get_top_employers' => has_action('wp_ajax_get_top_employers'),
            'get_form_nonce' => has_action('wp_ajax_dtr_get_form_nonce')
        ],
        'functions_available' => [
            'wp_create_nonce' => function_exists('wp_create_nonce'),
            'wp_verify_nonce' => function_exists('wp_verify_nonce')
        ]
    ]);
}

// Register DTR test handlers
add_action('wp_ajax_test_dtr_plugin', 'ajax_test_dtr_plugin');
add_action('wp_ajax_nopriv_test_dtr_plugin', 'ajax_test_dtr_plugin');
error_log('DTR AJAX: test_dtr_plugin actions registered');

/**
 * Enqueue autocomplete assets
 */
function enqueue_workbooks_autocomplete_assets() {
    // Register but don't enqueue by default - let shortcode control this
    wp_register_style(
        'workbooks-autocomplete-css',
        '',
        array(),
        '1.0.0'
    );
    
    wp_register_script(
        'workbooks-autocomplete-js',
        '',
        array(),
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'enqueue_workbooks_autocomplete_assets');