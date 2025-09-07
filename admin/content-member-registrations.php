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
}
?>
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
        $workbooks_object_ref = get_user_meta($user->ID, 'workbooks_object_ref', true);
        if (!$workbooks_object_ref) {
            $workbooks_object_ref = get_user_meta($user->ID, 'person_object_ref', true);
        }
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
    // $subscriptions = get_user_meta($user->ID, 'subscriptions', true);
    // if (is_array($subscriptions)) {
    //     $subscriptions = implode(', ', $subscriptions);
    // }
    echo '<tr>';
    echo '<td>' . esc_html($user->ID) . '</td>';
    echo '<td>' . esc_html($user->user_login) . '</td>';
    echo '<td>' . esc_html($user->user_email) . '</td>';
    echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($user->user_registered))) . '</td>';
    echo '<td>' . esc_html($workbooks_object_ref ?: '-') . '</td>';
    echo '<td>' . esc_html($workbooks_id ?: '-') . '</td>';
    echo '<td>' . esc_html($employer ?: '-') . '</td>';
    echo '</tr>';
    echo '<tr><td colspan="7">';
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