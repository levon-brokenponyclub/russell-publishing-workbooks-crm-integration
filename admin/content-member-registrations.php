<?php
if (!defined('ABSPATH')) exit;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                'employer_name' => $employer,
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
                'cf_person_data_source_detail' => 'DTR Web Member Signup',
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
    
    // Handle refresh all users action
    if (isset($_POST['refresh_all_users']) && current_user_can('edit_users')) {
        $refresh_count = 0;
        $error_count = 0;
        $messages = [];
        
        // Get all form-created users
        $refresh_args = [
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'created_via_ninja_form',
                    'value' => 1,
                    'compare' => '='
                ],
                [
                    'key' => 'created_via_html_form',
                    'value' => 1,
                    'compare' => '='
                ]
            ],
            'number' => 100, // Limit to prevent timeout
        ];
        $refresh_users = get_users($refresh_args);
        
        $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
        if ($workbooks) {
            foreach ($refresh_users as $refresh_user) {
                try {
                    $user_updated = false;
                    $workbooks_person_id = get_user_meta($refresh_user->ID, 'workbooks_person_id', true);
                    
                    // If user doesn't have a Workbooks ID, try to create one
                    if (!$workbooks_person_id) {
                        $first = get_user_meta($refresh_user->ID, 'first_name', true);
                        $last = get_user_meta($refresh_user->ID, 'last_name', true);
                        $email = $refresh_user->user_email;
                        $employer = get_user_meta($refresh_user->ID, 'employer_name', true);
                        $town = get_user_meta($refresh_user->ID, 'town', true);
                        $country = get_user_meta($refresh_user->ID, 'country', true) ?: 'South Africa';
                        $telephone = get_user_meta($refresh_user->ID, 'telephone', true);
                        $postcode = get_user_meta($refresh_user->ID, 'postcode', true);
                        $job_title = get_user_meta($refresh_user->ID, 'job_title', true);
                        $title = get_user_meta($refresh_user->ID, 'person_personal_title', true);
                        
                        // First try to find existing person by email
                        try {
                            $search_result = $workbooks->assertGet('crm/people', [
                                'main_location[email]' => $email, 
                                '_limit' => 1
                            ]);
                            
                            if (!empty($search_result['data'][0]['id'])) {
                                // Found existing person, link to them
                                $workbooks_person_id = $search_result['data'][0]['id'];
                                $workbooks_object_ref = $search_result['data'][0]['object_ref'] ?? '';
                                $employer_name = $search_result['data'][0]['employer_name'] ?? '';
                                update_user_meta($refresh_user->ID, 'workbooks_person_id', $workbooks_person_id);
                                update_user_meta($refresh_user->ID, 'workbooks_object_ref', $workbooks_object_ref);
                                if ($employer_name) {
                                    update_user_meta($refresh_user->ID, 'employer_name', $employer_name);
                                }
                                $messages[] = "Linked user {$refresh_user->ID} ({$email}) to existing Workbooks person {$workbooks_person_id}";
                                $refresh_count++;
                                $user_updated = true;
                            }
                        } catch (Exception $search_e) {
                            // Search failed, try to create new person
                        }
                        
                        // If no existing person found, create new one
                        if (!$user_updated) {
                            $payload = [
                                'person_first_name' => $first,
                                'person_last_name' => $last,
                                'main_location[email]' => $email,
                                'employer_name' => $employer,
                                'cf_person_claimed_employer' => $employer,
                                'created_through_reference' => 'wp_user_' . $refresh_user->ID,
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
                                'cf_person_data_source_detail' => 'DTR Web Member Signup',
                            ];
                            
                            $result = $workbooks->assertCreate('crm/people', [$payload]);
                            if (!empty($result['affected_objects'][0]['id'])) {
                                $workbooks_person_id = $result['affected_objects'][0]['id'];
                                $workbooks_object_ref = $result['affected_objects'][0]['object_ref'] ?? '';
                                update_user_meta($refresh_user->ID, 'workbooks_person_id', $workbooks_person_id);
                                update_user_meta($refresh_user->ID, 'workbooks_object_ref', $workbooks_object_ref);
                                $messages[] = "Created new Workbooks person {$workbooks_person_id} for user {$refresh_user->ID} ({$email})";
                                $refresh_count++;
                                $user_updated = true;
                            }
                        }
                    } else {
                        // User has Workbooks ID, refresh their object_ref and employer_name if missing
                        $workbooks_object_ref = get_user_meta($refresh_user->ID, 'workbooks_object_ref', true);
                        $current_employer_name = get_user_meta($refresh_user->ID, 'employer_name', true);
                        if (!$workbooks_object_ref || !$current_employer_name) {
                            try {
                                $result = $workbooks->assertGet('crm/people', [
                                    '_start' => 0,
                                    '_limit' => 1,
                                    '_ff[]' => 'id',
                                    '_ft[]' => 'eq',
                                    '_fc[]' => $workbooks_person_id
                                ]);
                                if (!empty($result['data'][0])) {
                                    $updates = [];
                                    if (!$workbooks_object_ref && !empty($result['data'][0]['object_ref'])) {
                                        $workbooks_object_ref = $result['data'][0]['object_ref'];
                                        update_user_meta($refresh_user->ID, 'workbooks_object_ref', $workbooks_object_ref);
                                        $updates[] = "object_ref";
                                    }
                                    if (!$current_employer_name && !empty($result['data'][0]['employer_name'])) {
                                        $employer_name = $result['data'][0]['employer_name'];
                                        update_user_meta($refresh_user->ID, 'employer_name', $employer_name);
                                        $updates[] = "employer_name";
                                    }
                                    if ($updates) {
                                        $messages[] = "Updated " . implode(' and ', $updates) . " for user {$refresh_user->ID} (Workbooks ID: {$workbooks_person_id})";
                                        $refresh_count++;
                                        $user_updated = true;
                                    }
                                }
                            } catch (Exception $ref_e) {
                                // Ignore fetch errors
                            }
                        }
                    }
                    
                } catch (Exception $e) {
                    $error_count++;
                    $messages[] = "Error processing user {$refresh_user->ID} ({$refresh_user->user_email}): " . $e->getMessage();
                }
            }
            
            // Show results
            if ($refresh_count > 0) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Refresh completed:</strong> Updated ' . $refresh_count . ' users.</p>';
                if (count($messages) <= 10) {
                    echo '<ul><li>' . implode('</li><li>', array_slice($messages, 0, 10)) . '</li></ul>';
                }
                echo '</div>';
            }
            if ($error_count > 0) {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>Errors:</strong> ' . $error_count . ' users had errors during refresh.</p></div>';
            }
            if ($refresh_count == 0 && $error_count == 0) {
                echo '<div class="notice notice-info is-dismissible"><p>All users already have current Workbooks information.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Workbooks API not available for refresh.</p></div>';
        }
    }
}
?>
<div class="wrap">
    <div style="margin-bottom: 20px;">
        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('refresh_all_users_action'); ?>
            <input type="submit" name="refresh_all_users" class="button button-secondary" value="Refresh All Users" onclick="return confirm('This will refresh Workbooks information for all form-created users. This may take a moment. Continue?');" />
        </form>
        <p class="description">Refreshes Workbooks Person IDs and information for all users created via forms. Links existing users to Workbooks persons or creates new ones as needed.</p>
    </div>
    
    <h2>Users Created from Form Submissions</h2>
<?php
$args = [
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => 'created_via_ninja_form',
            'value' => 1,
            'compare' => '='
        ],
        [
            'key' => 'created_via_html_form',
            'value' => 1,
            'compare' => '='
        ]
    ],
    'orderby' => 'registered',
    'order' => 'DESC',
    'number' => 50,
];
$users = get_users($args);
if (empty($users)) {
    echo '<p>No users created via form submissions found.</p>';
} else {
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Registered</th>
            <th>Source</th>
            <th>Workbooks Person ID</th>
            <th>Workbooks ID</th>
            <th>Employer</th>
        </tr>
    </thead><tbody>';
    foreach ($users as $user) {
        $workbooks_object_ref = get_user_meta($user->ID, 'workbooks_object_ref', true);
        if (!$workbooks_object_ref) {
            $workbooks_object_ref = get_user_meta($user->ID, 'person_object_ref', true);
        }
        if (!$workbooks_object_ref) {
            $workbooks_person_id = get_user_meta($user->ID, 'workbooks_person_id', true);
            $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
            if ($workbooks && $workbooks_person_id) {
                try {
                    $result = $workbooks->assertGet('crm/people', [
                        '_start' => 0,
                        '_limit' => 1,
                        '_ff[]' => 'id',
                        '_ft[]' => 'eq',
                        '_fc[]' => $workbooks_person_id
                    ]);
                    if (!empty($result['data'][0]['object_ref'])) {
                        $workbooks_object_ref = $result['data'][0]['object_ref'];
                        update_user_meta($user->ID, 'workbooks_object_ref', $workbooks_object_ref);
                    }
                    // Also update employer_name if available from Workbooks
                    if (!empty($result['data'][0]['employer_name'])) {
                        update_user_meta($user->ID, 'employer_name', $result['data'][0]['employer_name']);
                    }
                } catch (Exception $e) {}
            }
        }
        $workbooks_id = get_user_meta($user->ID, 'workbooks_person_id', true);
        if (!$workbooks_id) {
            $workbooks_id = get_user_meta($user->ID, 'workbooks_id', true);
        }
        
        // Determine source
        $source = 'Unknown';
        if (get_user_meta($user->ID, 'created_via_ninja_form', true)) {
            $source = 'Ninja Form';
        } elseif (get_user_meta($user->ID, 'created_via_html_form', true)) {
            $source = 'HTML Form';
        }
        
        $employer = get_user_meta($user->ID, 'employer_name', true);
        echo '<tr>';
        echo '<td>' . esc_html($user->ID) . '</td>';
        echo '<td>' . esc_html($user->user_login) . '</td>';
        echo '<td>' . esc_html($user->user_email) . '</td>';
        echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($user->user_registered))) . '</td>';
        echo '<td>' . esc_html($source) . '</td>';
        echo '<td>' . esc_html($workbooks_object_ref ?: '-') . '</td>';
        echo '<td>' . esc_html($workbooks_id ?: '-') . '</td>';
        echo '<td>' . esc_html($employer ?: '-') . '</td>';
        echo '</tr>';
        echo '<tr><td colspan="8">';
        echo '<form method="post" style="display:inline-block; margin-right:10px;">';
        echo '<input type="hidden" name="workbooks_sync_user_id" value="' . esc_attr($user->ID) . '">';
        submit_button('Sync to Workbooks', 'button-primary', 'workbooks_sync_to_workbooks', false);
        echo '</form>';
        echo '<form method="post" style="display:inline-block; margin-right:10px;" onsubmit="return confirm(\'Are you sure you want to delete this user?\');">';
        echo '<input type="hidden" name="delete_user_id" value="' . esc_attr($user->ID) . '">';
        submit_button('Delete User', 'delete button-secondary', 'delete_user', false);
        echo '</form>';
        if (empty($workbooks_object_ref) || empty($workbooks_id)) {
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
?>