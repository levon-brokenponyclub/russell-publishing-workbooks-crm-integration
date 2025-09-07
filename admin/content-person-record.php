
<h2><?php _e('Use these tools to test and synchronise your Workbooks integration features.<br/>The system will use your linked Workbooks Person ID: (ID: 4318866).', 'dtr-workbooks'); ?></h2>
<?php
// Handle person record update form submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['workbooks_update_user']) &&
    isset($_POST['dtr_update_nonce']) &&
    wp_verify_nonce($_POST['dtr_update_nonce'], 'dtr_update_person')
) {
    $workbooks = get_workbooks_instance();
    $current_wp_user = get_current_user_id();
    $dynamic_person_id = (int) get_user_meta($current_wp_user, 'workbooks_person_id', true);
    $is_fallback_record = false;
    if (empty($dynamic_person_id)) {
        $dynamic_person_id = 4318866; // legacy fixed test record
        $is_fallback_record = true;
    }
    
    // Prepare the payload for Workbooks API
    $payload = [
        'id' => $dynamic_person_id,
        'lock_version' => isset($_POST['lock_version']) ? intval($_POST['lock_version']) : 0,
        'person_personal_title' => sanitize_text_field($_POST['person_personal_title'] ?? ''),
        'person_first_name' => sanitize_text_field($_POST['person_first_name'] ?? ''),
        'person_last_name' => sanitize_text_field($_POST['person_last_name'] ?? ''),
        'person_job_title' => sanitize_text_field($_POST['person_job_title'] ?? ''),
        'main_location[telephone]' => sanitize_text_field($_POST['telephone'] ?? ''),
        'main_location[country]' => sanitize_text_field($_POST['country'] ?? ''),
        'main_location[town]' => sanitize_text_field($_POST['town'] ?? ''),
        'main_location[postcode]' => sanitize_text_field($_POST['postcode'] ?? '')
    ];

    // Add marketing preferences and interests
    $dtr_fields = [
        'cf_person_dtr_news',
        'cf_person_dtr_events',
        'cf_person_dtr_third_party',
        'cf_person_dtr_webinar'
    ];
    
    $interests_fields = [
        'cf_person_business',
        'cf_person_diseases',
        'cf_person_drugs_therapies',
        'cf_person_genomics_3774',
        'cf_person_research_development',
        'cf_person_technology',
        'cf_person_tools_techniques'
    ];

    // Add checkbox fields to payload
    foreach (array_merge($dtr_fields, $interests_fields) as $field) {
        $payload[$field] = isset($_POST[$field]) ? 1 : 0;
        // Compatibility: some instances use cf_person_genomics (no suffix)
        if ($field === 'cf_person_genomics_3774') {
        $genomics_selected = isset($_POST[$field]) ? 1 : 0;
        $payload['cf_person_genomics'] = $genomics_selected;
        // Force-set legacy old key to mirror selection (and allow clearing)
        $payload['cf_person_genomics_3744'] = $genomics_selected; // ensures clearing if previously set
        }
    }

    // Derive AOI mapping from selected TOI checkboxes
    if (function_exists('dtr_map_toi_to_aoi') && function_exists('dtr_get_aoi_field_names')) {
        $selected_toi = [];
        foreach ($interests_fields as $toi_field) {
            if (!empty($payload[$toi_field])) {
                $selected_toi[] = function_exists('dtr_normalize_toi_key') ? dtr_normalize_toi_key($toi_field) : $toi_field;
            }
        }
        $aoi_mapping = dtr_map_toi_to_aoi($selected_toi);
        if (!empty($aoi_mapping) && is_array($aoi_mapping)) {
            foreach ($aoi_mapping as $aoi_field => $val) {
                $payload[$aoi_field] = $val; // ensure AOI fields included in update payload
            }
            // Debug instrumentation for missing drugs_therapies mapping issues
            if (function_exists('dtr_admin_log')) {
                $dbg = '['.date('Y-m-d H:i:s')."] [AOI MAP] Selected TOI: ".implode(',', $selected_toi)."\nDerived AOI set (first 10 shown): ".implode(',', array_slice(array_keys(array_filter($aoi_mapping)),0,10))."\nDrugs therapies selected? ".(in_array('cf_person_drugs_therapies',$selected_toi)?'yes':'no')." mapping count=".count(array_filter($aoi_mapping))."\n";
                dtr_admin_log($dbg, 'update-debug.log');
            }
        }
    }

    try {
        // Log the payload we're about to send for debugging (separate file)
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [UPDATE PERSON] user_id=" . $current_wp_user . " target_person_id=" . ($payload['id'] ?? '') . " fallback=" . ($is_fallback_record ? 'yes' : 'no') . "\nPayload:\n" . print_r($payload, true) . "\n";
        dtr_admin_log($log_entry, 'update-debug.log');

        // Update the record in Workbooks
        $result = $workbooks->assertUpdate('crm/people', [$payload]);

        // Log the API response for debugging
        $resp_entry = "[" . date('Y-m-d H:i:s') . "] [UPDATE PERSON] user_id=" . $current_wp_user . " target_person_id=" . ($payload['id'] ?? '') . "\nResponse:\n" . print_r($result, true) . "\n";
        dtr_admin_log($resp_entry, 'update-debug.log');

        // Immediately fetch the stored person record to verify persisted fields
        try {
            if (!empty($payload['id'])) {
                // Fetch the full person record (no field-limiting) to verify what was stored
                $fetched = $workbooks->assertGet('crm/people', [$payload['id']]);
                $fetch_entry = "[" . date('Y-m-d H:i:s') . "] [VERIFY FETCH] user_id=" . $current_wp_user . " target_person_id=" . $payload['id'] . "\nFetched record:\n" . print_r($fetched, true) . "\n";
                dtr_admin_log($fetch_entry, 'update-debug.log');
            }
        } catch (Exception $e) {
            $fetch_err = "[" . date('Y-m-d H:i:s') . "] [VERIFY FETCH] user_id=" . $current_wp_user . " target_person_id=" . ($payload['id'] ?? '') . "\nException: " . $e->getMessage() . "\n";
            dtr_admin_log($fetch_err, 'update-debug.log');
        }

        if (!empty($result['affected_objects']) && $result['affected_objects'] > 0) {
            // If this update is for the real user's record (not fallback) mirror values back to user meta so shortcodes reflect instantly
            if (!$is_fallback_record) {
                $mirror_fields = array_merge($dtr_fields, $interests_fields, [
                    'person_personal_title' => 'person_personal_title',
                    'person_first_name' => 'first_name',
                    'person_last_name' => 'last_name',
                    'person_job_title' => 'job_title',
                    'main_location[telephone]' => 'telephone',
                    'main_location[country]' => 'country',
                    'main_location[town]' => 'town',
                    'main_location[postcode]' => 'postcode'
                ]);
                // Also mirror AOI fields so front-end shortcodes reflect derived mapping
                if (function_exists('dtr_get_aoi_field_names')) {
                    $mirror_fields = array_merge($mirror_fields, array_keys(dtr_get_aoi_field_names()));
                }
                foreach ($mirror_fields as $payload_key => $meta_key) {
                    // For numeric indexed items (from merged arrays) keep same key
                    if (is_int($payload_key)) {
                        $payload_key = $meta_key;
                    }
                    if (isset($payload[$payload_key])) {
                        update_user_meta($current_wp_user, $meta_key, $payload[$payload_key]);
                    }
                }
                // Ensure genomics compatibility meta updated (new, canonical, legacy old, and alias)
                if (array_key_exists('cf_person_genomics_3774', $payload)) {
                    update_user_meta($current_wp_user, 'cf_person_genomics_3774', $payload['cf_person_genomics_3774']);
                }
                if (array_key_exists('cf_person_genomics', $payload)) {
                    update_user_meta($current_wp_user, 'cf_person_genomics', $payload['cf_person_genomics']);
                }
                if (array_key_exists('cf_person_genomics_3744', $payload)) {
                    update_user_meta($current_wp_user, 'cf_person_genomics_3744', $payload['cf_person_genomics_3744']);
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>Person record updated successfully in Workbooks' . ($is_fallback_record ? ' (fallback test record)' : '') . '.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>No changes were made to the Workbooks record.</p></div>';
        }
    } catch (Exception $e) {
        // Log the exception
        $err_entry = "[" . date('Y-m-d H:i:s') . "] [UPDATE PERSON] user_id=" . get_current_user_id() . " target_person_id=" . ($payload['id'] ?? '') . "\nException: " . $e->getMessage() . "\n";
        dtr_admin_log($err_entry, 'update-debug.log');
        echo '<div class="notice notice-error is-dismissible"><p>Error updating Workbooks record: ' . esc_html($e->getMessage()) . '</p></div>';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['workbooks_update_user'])) {
    echo '<div class="notice notice-error is-dismissible"><p>Security check failed. Please reload and try again.</p></div>';
}
// Handle manual update of Workbooks person ID for current user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_workbooks_person_id']) && current_user_can('edit_users')) {
    $manual_person_id = intval($_POST['manual_workbooks_person_id'] ?? 0);
    if ($manual_person_id > 0) {
        update_user_meta(get_current_user_id(), 'workbooks_person_id', $manual_person_id);
        echo '<div class="notice notice-success is-dismissible"><p>Workbooks Person ID updated to ' . esc_html($manual_person_id) . ' for your account.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Invalid Workbooks Person ID.</p></div>';
    }
}
?>
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
echo '<button type="button" id="toggle-workbooks-fields" class="button button-primary" style="margin-bottom:8px;">Show Workbooks API Fields for this User</button>';
echo '<div id="workbooks-fields-table" class="wp-list-table widefat fixed striped" style="display:none;">';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('toggle-workbooks-fields');
    var table = document.getElementById('workbooks-fields-table');
    if (btn && table) {
        btn.addEventListener('click', function() {
            if (table.style.display === 'none' || table.style.display === '') {
                table.style.display = 'block';
                btn.textContent = 'Hide Workbooks API Fields for this User';
            } else {
                table.style.display = 'none';
                btn.textContent = 'Show Workbooks API Fields for this User';
            }
        });
    }
});
</script>
<?php
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
echo '</div>';
// Manual Workbooks Person ID update form
?>
<h2 style="margin-bottom:0;">Set/Update Workbooks Person ID for your account:</h2>
<form id="workbooks_get_user_form" method="post" style="margin-bottom:40px;">
    <input type="number" name="manual_workbooks_person_id" id="manual_workbooks_person_id" min="1" step="1" value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'workbooks_person_id', true)); ?>">
    <button type="submit" name="set_workbooks_person_id" class="button">Update ID</button>
</form>
<h2 style="margin-bottom:0;">Person Record Fields</h2>
<form id="workbooks_update_user_form" method="post">
    <?php wp_nonce_field('dtr_update_person', 'dtr_update_nonce'); ?>
    <input type="hidden" name="workbooks_update_user" value="1">
    <?php
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $workbooks = get_workbooks_instance();
    $dynamic_person_id = (int) get_user_meta($user_id, 'workbooks_person_id', true);
    $is_fallback_record = false;
    if (empty($dynamic_person_id)) { $dynamic_person_id = 4318866; $is_fallback_record = true; }
    $existing = $workbooks->assertGet('crm/people.api', [
        '_start' => 0,
        '_limit' => 1,
        '_ff[]' => 'id',
        '_ft[]' => 'eq',
        '_fc[]' => $dynamic_person_id,
        '_select_columns[]' => [
            'id', 'lock_version',
            'person_title', 'person_first_name', 'person_last_name', 'person_job_title',
            'main_location[email]', 'main_location[telephone]', 'main_location[country]',
            'main_location[town]', 'main_location[postcode]', 'employer_name',
            'cf_person_dtr_news','cf_person_dtr_events','cf_person_dtr_third_party','cf_person_dtr_webinar',
            'cf_person_business','cf_person_diseases','cf_person_drugs_therapies','cf_person_genomics_3774','cf_person_genomics','cf_person_research_development','cf_person_technology','cf_person_tools_techniques'
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
    if ($is_fallback_record) {
        echo '<p style="margin:8px 0 16px 0;"><em>Using fallback fixed Workbooks record (ID 4318866). Set your own Workbooks Person ID above to mirror changes into your user meta automatically.</em></p>';
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
            // For Genomics we check both possible Workbooks field names for compatibility
            if ($field === 'cf_person_genomics_3774') {
                $checked = get_user_meta($user_id, $field, true) || !empty($person[$field]) || !empty($person['cf_person_genomics']);
            } else {
                $checked = get_user_meta($user_id, $field, true) || !empty($person[$field]);
            }
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
        <input type="text" id="emplyer" name="employer" value="Supersonic Playground Ltd" class="regular-text" readonly></label>
    </label></p>
    <p><label for="telephone">Telephone<br>
        <input type="text" id="telephone" name="telephone" value="<?php echo get_field_value($person, 'main_location[telephone]', $user_id); ?>" class="regular-text"></label></p>
    <p><label for="country">Country<br>
        <input type="text" id="country" name="country" value="<?php echo get_field_value($person, 'main_location[country]', $user_id); ?>" class="regular-text"></label></p>
    <p><label for="town">Town / City<br>
        <input type="text" id="town" name="town" value="<?php echo get_field_value($person, 'main_location[town]', $user_id); ?>" class="regular-text"></label></p>
    <p><label for="postcode">Post / Zip Code<br>
        <input type="text" id="postcode" name="postcode" value="<?php echo get_field_value($person, 'main_location[postcode]', $user_id); ?>" class="regular-text"></label></p>
    <p class="submit" style="display:flex;gap:10px;align-items:center;">
        <input type="submit" name="submit" id="submit-person-record" class="button button-primary" value="Update Person Record">
        <button type="button" id="run-genomics-cleanup" class="button">Cleanup Old Genomics Key</button>
        <span id="genomics-cleanup-result" style="color:#555;"></span>
    </p>
</form>
                
            