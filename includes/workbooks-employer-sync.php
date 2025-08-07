<?php
/**
 * Workbooks CRM Employer Sync Functionality
 * 
 * Handles employer data synchronization, storage, retrieval, and JSON generation
 */
if (!defined('ABSPATH')) exit;

// Schedule the cron job on activation
register_activation_hook(__FILE__, 'workbooks_schedule_employer_sync');
function workbooks_schedule_employer_sync() {
    if (!wp_next_scheduled('workbooks_daily_employer_sync')) {
        wp_schedule_event(time(), 'daily', 'workbooks_daily_employer_sync');
    }
}

// Unschedule on deactivation
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('workbooks_daily_employer_sync');
});

// Register cron schedule
add_filter('cron_schedules', function($schedules) {
    $schedules['daily'] = [
        'interval' => DAY_IN_SECONDS,
        'display' => __('Once Daily')
    ];
    return $schedules;
});

/**
 * Save employers to the database and generate JSON file
 * 
 * @param array $employers Array of employer objects with id and name
 * @return int Number of employers saved
 */
function workbooks_save_employers($employers) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'workbooks_employers';
    
    // Begin transaction for better performance
    $wpdb->query('START TRANSACTION');
    
    foreach ($employers as $employer) {
        $wpdb->replace(
            $table_name,
            [
                'id' => $employer['id'],
                'name' => $employer['name'],
                'last_updated' => current_time('mysql')
            ],
            ['%d', '%s', '%s']
        );
    }
    
    $wpdb->query('COMMIT');
    
    // Generate JSON file for frontend dropdown
    $json_data = json_encode($employers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $json_file = WORKBOOKS_NF_PATH . 'employers.json';
    file_put_contents($json_file, $json_data);
    
    // Also update the transient for quick access
    set_transient('workbooks_organisations', $employers, 7 * DAY_IN_SECONDS);
    
    // Update last sync information
    update_option('workbooks_employers_last_sync', [
        'time' => time(),
        'count' => count($employers)
    ]);
    
    return count($employers);
}

/**
 * Generate employers.json from the database table
 * 
 * @return array Result with success status and message
 */
function workbooks_generate_employers_json_from_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'workbooks_employers';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        return [
            'success' => false,
            'message' => 'Employers table does not exist.'
        ];
    }
    
    // Fetch all employers from the database
    $employers = $wpdb->get_results("SELECT id, name FROM $table_name ORDER BY name ASC", ARRAY_A);
    
    if (empty($employers)) {
        return [
            'success' => false,
            'message' => 'No employers found in the database.'
        ];
    }
    
    // Generate JSON file
    $json_file = WORKBOOKS_NF_PATH . 'employers.json';
    $json_data = json_encode($employers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents($json_file, $json_data) === false) {
        return [
            'success' => false,
            'message' => 'Failed to write employers.json file. Check directory permissions.'
        ];
    }
    
    // Update transient for consistency
    set_transient('workbooks_organisations', $employers, 7 * DAY_IN_SECONDS);
    
    return [
        'success' => true,
        'message' => 'Successfully generated employers.json with ' . count($employers) . ' employers.'
    ];
}

/**
 * Get all employers from the database
 * 
 * @return array Array of employer objects
 */
function workbooks_get_employers() {
    // Try transient first for performance
    $cached = get_transient('workbooks_organisations');
    if ($cached !== false) {
        return $cached;
    }
    
    // Fall back to database
    global $wpdb;
    $table_name = $wpdb->prefix . 'workbooks_employers';
    $employers = $wpdb->get_results("SELECT id, name, last_updated FROM $table_name ORDER BY name ASC", ARRAY_A);
    
    // Refresh transient
    if (!empty($employers)) {
        set_transient('workbooks_organisations', $employers, 7 * DAY_IN_SECONDS);
    }
    
    return $employers;
}

/**
 * Get a single employer by ID
 * 
 * @param int $id Employer ID
 * @return array|null Employer data or null if not found
 */
function workbooks_get_employer_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'workbooks_employers';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
}

/**
 * Get a single employer by name
 * 
 * @param string $name Employer name
 * @return array|null Employer data or null if not found
 */
function workbooks_get_employer_by_name($name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'workbooks_employers';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE name = %s", $name), ARRAY_A);
}

/**
 * Search for or create organisation by name, return ID or null.
 * 
 * @param string $org_name Organisation name
 * @return int|null Organisation ID or null if not found/created
 */
function workbooks_get_or_create_organisation_id($org_name) {
    if (empty($org_name)) {
        error_log("EMPLOYER DEBUG: Empty organisation name provided");
        return null;
    }
    
    error_log("EMPLOYER DEBUG: Looking for organisation: '$org_name'");
    
    // First check our local database
    $employer = workbooks_get_employer_by_name($org_name);
    if ($employer) {
        error_log("EMPLOYER DEBUG: Found in local cache with ID: " . $employer['id']);
        return $employer['id'];
    }
    
    error_log("EMPLOYER DEBUG: Not found in local cache, querying Workbooks API...");
    
    // If not found, query Workbooks API
    $workbooks = get_workbooks_instance();
    if (!$workbooks) {
        error_log("EMPLOYER DEBUG: Failed to get Workbooks instance");
        return null;
    }
    
    try {
        $search = $workbooks->assertGet('crm/organisations.api', [
            '_ff[]' => 'name',
            '_ft[]' => 'eq',
            '_fc[]' => $org_name,
            '_limit' => 1,
            '_select_columns[]' => ['id'],
        ]);
        
        error_log("EMPLOYER DEBUG: Workbooks search response: " . print_r($search, true));
    } catch (Exception $e) {
        error_log("EMPLOYER DEBUG: Error searching Workbooks API: " . $e->getMessage());
        return null;
    }
    
    if (!empty($search['data'][0]['id'])) {
        error_log("EMPLOYER DEBUG: Found existing organisation with ID: " . $search['data'][0]['id']);
        // Save to our database and JSON file for future use
        $employer = [
            'id' => $search['data'][0]['id'],
            'name' => $org_name,
            'last_updated' => current_time('mysql')
        ];
        workbooks_save_employers([$employer]);
        return $search['data'][0]['id'];
    }
    
    error_log("EMPLOYER DEBUG: Organisation not found, creating new one...");
    
    // Create if not found
    try {
        $create = $workbooks->assertCreate('crm/organisations.api', [[
            'name' => $org_name,
        ]]);
        
        error_log("EMPLOYER DEBUG: Workbooks create response: " . print_r($create, true));
    } catch (Exception $e) {
        error_log("EMPLOYER DEBUG: Error creating organisation: " . $e->getMessage());
        return null;
    }
    
    if (!empty($create['data'][0]['id'])) {
        error_log("EMPLOYER DEBUG: Successfully created organisation with ID: " . $create['data'][0]['id']);
        // Save the newly created org to our database and JSON file
        $employer = [
            'id' => $create['data'][0]['id'],
            'name' => $org_name,
            'last_updated' => current_time('mysql')
        ];
        workbooks_save_employers([$employer]);
        return $create['data'][0]['id'];
    }
    
    error_log("EMPLOYER DEBUG: Failed to create organisation");
    return null;
}

/**
 * Daily cron job to sync employers
 */
add_action('workbooks_daily_employer_sync', 'workbooks_sync_employers_cron');
function workbooks_sync_employers_cron() {
    $workbooks = get_workbooks_instance();
    $all_employers = [];
    $start = 0;
    $batch_size = 100;
    
    try {
        do {
            $response = $workbooks->assertGet('crm/organisations.api', [
                '_start' => $start,
                '_limit' => $batch_size,
                '_select_columns[]' => ['id', 'name'],
                '_sort[]' => 'name',
                '_dir[]' => 'ASC',
            ]);
            
            $employers = $response['data'] ?? [];
            $all_employers = array_merge($all_employers, $employers);
            $start += $batch_size;
        } while (!empty($employers) && count($employers) == $batch_size);
        
        if (!empty($all_employers)) {
            workbooks_save_employers($all_employers);
            // Log the successful sync
            error_log('Workbooks employers sync completed: ' . count($all_employers) . ' employers synced.');
        }
    } catch (Exception $e) {
        error_log('Workbooks employers sync failed: ' . $e->getMessage());
    }
}

/**
 * Ajax: fetch organisations batch for sync
 */
add_action('wp_ajax_fetch_workbooks_organisations_batch', function() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 100;
    $workbooks = get_workbooks_instance();
    try {
        $response = $workbooks->assertGet('crm/organisations.api', [
            '_start' => $start,
            '_limit' => $batch_size,
            '_select_columns[]' => ['id', 'name'],
            '_sort[]' => 'name',
            '_dir[]' => 'ASC',
        ]);
        $organisations = $response['data'] ?? [];
        $total = $response['total'] ?? count($organisations);
        $has_more = count($organisations) == $batch_size && ($start + $batch_size) < $total;
        
        // Save this batch to the database and JSON file
        if (!empty($organisations)) {
            workbooks_save_employers($organisations);
        }
        
        wp_send_json_success([
            'organisations' => $organisations,
            'total' => $total,
            'has_more' => $has_more
        ]);
    } catch (Exception $e) {
        wp_send_json_error('Exception: ' . $e->getMessage());
    }
});

/**
 * Ajax: fetch organisations for employer dropdown (use JSON file if available)
 */
add_action('wp_ajax_fetch_workbooks_organisations', function() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    
    // Check JSON file first
    $json_file = WORKBOOKS_NF_PATH . 'employers.json';
    if (file_exists($json_file)) {
        $employers = json_decode(file_get_contents($json_file), true);
        if ($employers && is_array($employers)) {
            wp_send_json_success($employers);
            return;
        }
    }
    
    // Fall back to database
    $employers = workbooks_get_employers();
    if (!empty($employers)) {
        // Save to JSON file for future use
        file_put_contents($json_file, json_encode($employers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        wp_send_json_success($employers);
        return;
    }
    
    // If database is empty, query API
    $workbooks = get_workbooks_instance();
    try {
        $all_organisations = [];
        $start = 0;
        $batch_size = 100;
        do {
            $response = $workbooks->assertGet('crm/organisations.api', [
                '_start' => $start,
                '_limit' => $batch_size,
                '_select_columns[]' => ['id', 'name'],
                '_sort[]' => 'name',
                '_dir[]' => 'ASC',
            ]);
            $organisations = $response['data'] ?? [];
            $all_organisations = array_merge($all_organisations, $organisations);
            $start += $batch_size;
        } while (!empty($organisations) && count($organisations) == $batch_size);
        
        if (!empty($all_organisations)) {
            // Save to database and JSON file
            workbooks_save_employers($all_organisations);
            wp_send_json_success($all_organisations);
        } else {
            wp_send_json_error('No organisations found.');
        }
    } catch (Exception $e) {
        wp_send_json_error('Exception: ' . $e->getMessage());
    }
});

/**
 * Ajax: Generate employers.json from database
 */
add_action('wp_ajax_workbooks_generate_employers_json', function() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied.');
        return;
    }
    
    $result = workbooks_generate_employers_json_from_db();
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
});

/**
 * Debug function to log AJAX errors
 */
function workbooks_debug_log($message, $data = null) {
    if (WP_DEBUG) {
        error_log('WORKBOOKS DEBUG: ' . $message . (is_null($data) ? '' : ' - ' . print_r($data, true)));
    }
}

/**
 * Ajax: fetch employers with paging
 */
add_action('wp_ajax_fetch_workbooks_employers_paged', function() {
    workbooks_debug_log('fetch_workbooks_employers_paged called');
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'workbooks_nonce')) {
        workbooks_debug_log('Nonce verification failed');
        wp_send_json_error('Security check failed');
        return;
    }
    
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    
    workbooks_debug_log('Parameters', [
        'offset' => $offset,
        'limit' => $limit,
        'search' => $search
    ]);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'workbooks_employers';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        workbooks_debug_log('Table does not exist: ' . $table_name);
        wp_send_json_error('Employers table does not exist. Please activate the plugin again to create it.');
        return;
    }
    
    // Build the query
    $query = "SELECT id, name, last_updated FROM $table_name";
    $count_query = "SELECT COUNT(*) FROM $table_name";
    
    // Add search condition if provided
    if (!empty($search)) {
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $query .= $wpdb->prepare(" WHERE name LIKE %s", $search_term);
        $count_query .= $wpdb->prepare(" WHERE name LIKE %s", $search_term);
    }
    
    // Add order and limit
    $query .= " ORDER BY name ASC LIMIT %d OFFSET %d";
    $query = $wpdb->prepare($query, $limit, $offset);
    
    workbooks_debug_log('SQL Query', $query);
    
    // Get employers
    $employers = $wpdb->get_results($query, ARRAY_A);
    
    // Get total count
    $total = $wpdb->get_var($count_query);
    
    workbooks_debug_log('Query results', [
        'total' => $total,
        'count' => count($employers)
    ]);
    
    wp_send_json_success([
        'employers' => $employers,
        'total' => intval($total),
        'offset' => $offset,
        'limit' => $limit
    ]);
});

/**
 * Ajax: resync a single employer
 */
add_action('wp_ajax_resync_workbooks_employer', function() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    
    $employer_id = isset($_POST['employer_id']) ? intval($_POST['employer_id']) : 0;
    
    if (!$employer_id) {
        wp_send_json_error('Invalid employer ID');
        return;
    }
    
    $workbooks = get_workbooks_instance();
    
    try {
        $response = $workbooks->assertGet('crm/organisations.api', [
            '_ff[]' => 'id',
            '_ft[]' => 'eq',
            '_fc[]' => $employer_id,
            '_select_columns[]' => ['id', 'name'],
        ]);
        
        if (!empty($response['data'][0])) {
            $employer = [
                'id' => $response['data'][0]['id'],
                'name' => $response['data'][0]['name'],
                'last_updated' => current_time('mysql')
            ];
            
            // Save the updated employer
            workbooks_save_employers([$employer]);
            
            wp_send_json_success($employer);
        } else {
            wp_send_json_error('Employer not found in Workbooks CRM');
        }
    } catch (Exception $e) {
        wp_send_json_error('Exception: ' . $e->getMessage());
    }
});

/**
 * Ajax: test connection
 */
/* add_action('wp_ajax_workbooks_test_connection', function() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $workbooks = get_workbooks_instance();
    try {
        $payload = [[
            'name' => 'Connection Test ' . time(),
            'person_first_name' => 'Test',
            'person_last_name' => 'User',
            'created_through_reference' => 'wp_test_' . time(),
        ]];
        $response = $workbooks->assertCreate('crm/people', $payload);
        if (isset($response['flash']) && strpos($response['flash'], 'saved successfully') !== false) {
            wp_send_json_success('Connection successful.');
        } else {
            wp_send_json_error('Unexpected response: ' . print_r($response, true));
        }
    } catch (Exception $e) {
        wp_send_json_error('Exception: ' . $e->getMessage());
    }
}); */