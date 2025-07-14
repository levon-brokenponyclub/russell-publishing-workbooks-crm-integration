<?php
if (!defined('ABSPATH')) exit;

add_action('admin_init', 'workbooks_handle_user_sync_to_workbooks');

function workbooks_handle_user_sync_to_workbooks() {
    error_log('Starting workbooks_handle_user_sync_to_workbooks');
    if (!current_user_can('manage_options')) {
        error_log('User lacks manage_options permission');
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Permission denied.</p></div>';
        });
        return;
    }

    if (!isset($_POST['workbooks_sync_to_workbooks'], $_POST['workbooks_sync_user_id'])) {
        error_log('POST data missing: ' . print_r($_POST, true));
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Missing form data.</p></div>';
        });
        return;
    }

    $user_id = intval($_POST['workbooks_sync_user_id']);
    error_log('User ID: ' . $user_id);
    $user = get_userdata($user_id);
    
    if (!$user) {
        error_log('User not found for ID: ' . $user_id);
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>User not found.</p></div>';
        });
        return;
    }
    error_log('User found: ' . $user->user_email);

    if (empty($user->user_email)) {
        error_log('Email is empty for user ID: ' . $user_id);
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Email is required for Workbooks sync.</p></div>';
        });
        return;
    }

    $meta = function($key) use ($user_id) {
        return get_user_meta($user_id, $key, true);
    };

    $first_name = $meta('first_name');
    $last_name = $meta('last_name');
    $employer_name = $meta('employer_name');
    error_log('First Name: ' . ($first_name ?: 'Empty'));
    error_log('Last Name: ' . ($last_name ?: 'Empty'));
    error_log('Email: ' . $user->user_email);
    error_log('Employer Name: ' . ($employer_name ?: 'Empty'));

    if (empty($first_name) || empty($last_name)) {
        error_log('First name or last name is empty for user ID: ' . $user_id);
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>First name and last name are required for Workbooks sync.</p></div>';
        });
        return;
    }

    $payload = [
        [
            'person_first_name'         => $first_name,
            'person_last_name'          => $last_name,
            'name'                      => trim($first_name . ' ' . $last_name),
            'main_location[email]'      => $user->user_email,
            'created_through_reference' => 'wp_user_' . $user_id,
            'title'                     => $meta('title') ?: '',
            'person_job_title'          => $meta('job_title') ?: '',
            'main_location[telephone]'  => $meta('telephone') ?: '',
            'main_location[country]'    => $meta('country') ?: '',
            'main_location[town]'       => $meta('town') ?: '',
            'main_location[postcode]'   => $meta('postcode') ?: ''
        ]
    ];

    // Temporarily skip employer sync to isolate issues
    /*
    if ($employer_name) {
        if (function_exists('workbooks_get_or_create_organisation_id')) {
            try {
                error_log('Attempting to get or create organisation for employer: ' . $employer_name);
                $org_id = workbooks_get_or_create_organisation_id($employer_name);
                if ($org_id) {
                    $payload[0]['main_employer'] = $org_id;
                    error_log('Organisation ID set: ' . $org_id);
                } else {
                    error_log('Failed to get or create organisation ID for employer: ' . $employer_name);
                }
            } catch (Exception $e) {
                error_log('Employer sync error: ' . $e->getMessage());
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-warning"><p>Employer sync failed: ' . esc_html($e->getMessage()) . '</p></div>';
                });
            }
        } else {
            error_log('Function workbooks_get_or_create_organisation_id not defined');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>Employer sync function not defined.</p></div>';
            });
        }
    }
    */

    $workbooks = get_workbooks_instance();
    if (!$workbooks || !is_object($workbooks)) {
        error_log('Workbooks instance is null or invalid. API Key: ' . (get_option('workbooks_api_key') ? 'Set' : 'Not set') . ', API URL: ' . (get_option('workbooks_api_url') ? 'Set' : 'Not set'));
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Workbooks instance is null or invalid. Check API settings.</p></div>';
        });
        return;
    }
    error_log('Workbooks instance initialized');

    try {
        error_log('Checking for existing user in Workbooks');
        $search_response = $workbooks->assertGet('crm/people', [
            '_ff[]' => 'main_location[email]',
            '_ft[]' => 'eq',
            '_fc[]' => $user->user_email,
            '_select_columns[]' => ['id', 'lock_version']
        ]);
        error_log('Search response: ' . print_r($search_response, true));
        if (!empty($search_response['affected_objects']) && isset($search_response['affected_objects'][0]['id'])) {
            $wb_id = $search_response['affected_objects'][0]['id'];
            update_user_meta($user_id, 'workbooks_person_id', $wb_id);
            error_log('User already exists in Workbooks with ID: ' . $wb_id);
            add_action('admin_notices', function() use ($wb_id) {
                echo '<div class="notice notice-info"><p>User already exists in Workbooks (ID ' . esc_html($wb_id) . ').</p></div>';
            });
            return;
        }

        error_log('Payload sent to Workbooks: ' . print_r($payload, true));
        $response = $workbooks->assertCreate('crm/people', $payload);
        error_log('Workbooks API Response: ' . print_r($response, true));

        $wb_id = null;
        if (isset($response['affected_objects'][0]['id'])) {
            $wb_id = $response['affected_objects'][0]['id'];
        } elseif (isset($response['data'][0]['id'])) {
            $wb_id = $response['data'][0]['id']; // Fallback for potential API variations
        }

        if ($wb_id) {
            update_user_meta($user_id, 'workbooks_person_id', $wb_id);
            error_log('User synced to Workbooks with ID: ' . $wb_id);
            add_action('admin_notices', function() use ($wb_id) {
                echo '<div class="notice notice-success"><p>User synced to Workbooks (ID ' . esc_html($wb_id) . ').</p></div>';
            });
        } else {
            $message = isset($response['errors']) ? esc_html(print_r($response['errors'], true)) : 'Workbooks response did not include ID. Full response: ' . print_r($response, true);
            error_log('API Error: ' . $message);
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-warning"><p>' . $message . '</p></div>';
            });
        }
    } catch (Exception $e) {
        error_log('Workbooks Sync Error: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>Sync failed: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}