<?php
if (!defined('ABSPATH')) exit;

/**
 * Logging helper for debugging.
 */
if (!function_exists('nf_debug_log')) {
    function nf_debug_log($message) {
        $log_file = plugin_dir_path(__FILE__) . 'register-debug.log';
        $time = date('Y-m-d H:i:s');
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        file_put_contents($log_file, "[$time] $message\n", FILE_APPEND);
    }
}

/**
 * Adds custom user meta fields to the WordPress user profile page (admin).
 */

if (!function_exists('workbooks_crm_get_personal_titles')) {
    function workbooks_crm_get_personal_titles() {
        $titles = get_transient('workbooks_personal_titles');
        if ($titles !== false && is_array($titles)) {
            return $titles;
        }
        if (function_exists('get_workbooks_instance')) {
            try {
                $workbooks = get_workbooks_instance();
                $meta = $workbooks->assertGet('crm/people/metadata.api', []);
                if (!empty($meta['data']['fields']['person_title']['picklist_values'])) {
                    $titles = $meta['data']['fields']['person_title']['picklist_values'];
                    set_transient('workbooks_personal_titles', $titles, 12 * HOUR_IN_SECONDS);
                    return $titles;
                }
            } catch (Exception $e) {}
        }
        return ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof'];
    }
}

function workbooks_crm_get_dtr_memberships() {
    $cache = get_transient('workbooks_dtr_memberships');
    if ($cache !== false && is_array($cache)) return $cache;
    if (function_exists('get_workbooks_instance')) {
        try {
            $workbooks = get_workbooks_instance();
            $campaigns = $workbooks->assertGet('crm/campaigns.api', [
                '_limit' => 100,
                '_ff[]' => 'type',
                '_ft[]' => 'eq',
                '_fc[]' => 'DTR Membership',
                '_select_columns[]' => ['id', 'name'],
            ]);
            $list = [];
            foreach (($campaigns['data'] ?? []) as $c) {
                $list[$c['id']] = $c['name'];
            }
            set_transient('workbooks_dtr_memberships', $list, 12 * HOUR_IN_SECONDS);
            return $list;
        } catch (Exception $e) {}
    }
    return [];
}

function workbooks_crm_get_user_memberships($user_email) {
    if (function_exists('get_workbooks_instance') && $user_email) {
        try {
            $workbooks = get_workbooks_instance();
            $memberships = $workbooks->assertGet('crm/campaign_membership.api', [
                '_limit' => 100,
                '_ff[]' => 'person[email]',
                '_ft[]' => 'eq',
                '_fc[]' => $user_email,
                '_select_columns[]' => ['campaign_id'],
            ]);
            $ids = [];
            foreach (($memberships['data'] ?? []) as $m) {
                if (!empty($m['campaign_id'])) $ids[] = $m['campaign_id'];
            }
            return $ids;
        } catch (Exception $e) {}
    }
    return [];
}

if (!function_exists('workbooks_crm_get_dtr_areas_of_interest')) {
    function workbooks_crm_get_dtr_areas_of_interest() {
        $cache = get_transient('workbooks_dtr_areas_of_interest');
        if ($cache !== false && is_array($cache)) return $cache;
        if (function_exists('get_workbooks_instance')) {
            try {
                $workbooks = get_workbooks_instance();
                $picklist = $workbooks->assertGet('picklist_data/Private_PicklistEntry/value/value.wbjson', []);
                $list = [];
                foreach (($picklist['data'] ?? []) as $item) {
                    if (!empty($item['value'])) {
                        $list[$item['value']] = $item['value'];
                    }
                }
                set_transient('workbooks_dtr_areas_of_interest', $list, 12 * HOUR_IN_SECONDS);
                return $list;
            } catch (Exception $e) {}
        }
        return [];
    }
}

function workbooks_crm_log_all_user_info($user_id) {
    if (!function_exists('get_workbooks_instance')) return;
    $user = get_userdata($user_id);
    if (!$user || empty($user->user_email)) return;
    $workbooks = get_workbooks_instance();
    $person = $workbooks->assertGet('crm/people.api', [
        '_limit' => 1,
        '_ff[]' => 'main_location[email]',
        '_ft[]' => 'eq',
        '_fc[]' => $user->user_email,
    ]);
    nf_debug_log('Workbooks person record: ' . print_r($person, true));
    if (!empty($person['data'][0]['id'])) {
        $person_id = $person['data'][0]['id'];
        $memberships = $workbooks->assertGet('crm/campaign_membership.api', [
            '_ff[]' => 'person_id',
            '_ft[]' => 'eq',
            '_fc[]' => $person_id,
        ]);
        nf_debug_log('Workbooks campaign memberships: ' . print_r($memberships, true));
        $fields = [
            'cf_person_dtr_areas_of_interest_v2',
            'cf_person_dtr_news',
            'cf_person_dtr_events',
            'cf_person_dtr_subscriber',
            'cf_person_dtr_third_party',
            'cf_person_dtr_webinar',
            'cf_person_dtr_news_and_events',
        ];
        $custom = [];
        foreach ($fields as $f) {
            $custom[$f] = $person['data'][0][$f] ?? null;
        }
        nf_debug_log('Workbooks custom fields: ' . print_r($custom, true));
        $picklists = [
            'DTR Areas of Interest' => 'picklist_data/Private_PicklistEntry/value/value.wbjson?picklist_id=161',
            'DTR Subscription Types' => 'picklist_data/Private_PicklistEntry/value/value.wbjson?picklist_id=340',
            'DTR Advertiser Type' => 'picklist_data/Private_PicklistEntry/value/value.wbjson?picklist_id=314',
        ];
        foreach ($picklists as $label => $endpoint) {
            try {
                $plist = $workbooks->assertGet($endpoint, []);
                nf_debug_log("Workbooks picklist [$label]: " . print_r($plist, true));
            } catch (Exception $e) {}
        }
    }
}

function workbooks_crm_show_custom_user_profile_fields($user) {
    workbooks_crm_log_all_user_info($user->ID);
    $current_title = get_user_meta($user->ID, 'person_title', true);
    $titles = workbooks_crm_get_personal_titles();
    $user_email = $user->user_email;
    $all_memberships = workbooks_crm_get_dtr_memberships();
    $user_memberships = workbooks_crm_get_user_memberships($user_email);
    $all_areas = workbooks_crm_get_dtr_areas_of_interest();
    $user_areas = get_user_meta($user->ID, 'dtr_areas_of_interest', true);
    if (!is_array($user_areas)) $user_areas = [];

    // Fetch and display Workbooks person record and custom fields
    $workbooks_data = null;
    $custom_fields = [];
    if (function_exists('get_workbooks_instance') && $user_email) {
        try {
            $workbooks = get_workbooks_instance();
            $person = $workbooks->assertGet('crm/people.api', [
                '_limit' => 1,
                '_ff[]' => 'main_location[email]',
                '_ft[]' => 'eq',
                '_fc[]' => $user_email,
            ]);
            if (!empty($person['data'][0])) {
                $workbooks_data = $person['data'][0];
                // List of custom fields to display
                $fields = [
                    'cf_person_dtr_areas_of_interest_v2',
                    'cf_person_dtr_news',
                    'cf_person_dtr_events',
                    'cf_person_dtr_subscriber',
                    'cf_person_dtr_third_party',
                    'cf_person_dtr_webinar',
                    'cf_person_dtr_news_and_events',
                ];
                foreach ($fields as $f) {
                    $custom_fields[$f] = $workbooks_data[$f] ?? '';
                }
            }
        } catch (Exception $e) {}
    }
    ?>
    <h2>Workbooks CRM - Additional Info</h2>
    <?php if ($workbooks_data): ?>
    <div style="background:#f8f8f8; border:1px solid #ccc; padding:10px; margin-bottom:20px;">
    <strong>Workbooks Person Record:</strong>
    <table style="width:100%;">
    <?php foreach ($workbooks_data as $k => $v): ?>
    <tr><td style="width:220px;"><code><?php echo esc_html($k); ?></code></td><td><?php echo is_array($v) ? esc_html(json_encode($v)) : esc_html($v); ?></td></tr>
    <?php endforeach; ?>
    </table>
    <?php if (!empty($custom_fields)): ?>
    <strong>Custom Fields:</strong>
    <ul>
    <?php foreach ($custom_fields as $k => $v): ?>
    <li><code><?php echo esc_html($k); ?></code>: <?php echo is_array($v) ? esc_html(json_encode($v)) : esc_html($v); ?></li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <h3>DTR Marketing Preferences (from Workbooks)</h3>
    <table style="width:100%; background:#f8f8f8; border:1px solid #ccc;">
    <tr><td><strong>Areas of Interest</strong></td><td><?php echo esc_html($workbooks_data['cf_person_dtr_areas_of_interest_v2'] ?? ''); ?></td></tr>
    <tr><td><strong>DTR News</strong></td><td><?php echo esc_html($workbooks_data['cf_person_dtr_news'] ?? ''); ?></td></tr>
    <tr><td><strong>DTR Events</strong></td><td><?php echo esc_html($workbooks_data['cf_person_dtr_events'] ?? ''); ?></td></tr>
    <tr><td><strong>DTR Subscriber</strong></td><td><?php echo esc_html($workbooks_data['cf_person_dtr_subscriber'] ?? ''); ?></td></tr>
    <tr><td><strong>DTR Third Party</strong></td><td><?php echo esc_html($workbooks_data['cf_person_dtr_third_party'] ?? ''); ?></td></tr>
    <tr><td><strong>DTR Webinar</strong></td><td><?php echo esc_html($workbooks_data['cf_person_dtr_webinar'] ?? ''); ?></td></tr>
    <tr><td><strong>DTR News & Events</strong></td><td><?php echo esc_html($workbooks_data['cf_person_dtr_news_and_events'] ?? ''); ?></td></tr>
    </table>
    </div>
    <?php endif; ?>
    <table class="form-table">
    <h2>Workbooks CRM - Additional Info</h2>
    <table class="form-table">
        <tr>
            <th><label for="person_title">Title</label></th>
            <td>
                <select name="person_title" id="person_title">
                    <option value="">-- Select --</option>
                    <?php foreach ($titles as $title): ?>
                        <option value="<?php echo esc_attr($title); ?>" <?php selected($current_title, $title); ?>><?php echo esc_html($title); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        
        <tr>
            <th>Marketing Preferences</th>
            <td>
                <?php
                $dtr_fields = [
                    'cf_person_dtr_news' => 'DTR News',
                    'cf_person_dtr_events' => 'DTR Events',
                    'cf_person_dtr_subscriber' => 'DTR Subscriber',
                    'cf_person_dtr_third_party' => 'DTR Third Party',
                    'cf_person_dtr_webinar' => 'DTR Webinar',
                    'cf_person_dtr_news_and_events' => 'DTR News & Events',
                ];
                $selected = [];
                foreach ($dtr_fields as $field => $label) {
                    if (!empty($workbooks_data[$field]) || get_user_meta($user->ID, $field, true)) {
                        $selected[] = esc_html($label);
                    }
                }
                if ($selected) {
                    echo implode('<br>', $selected);
                } else {
                    echo '<em>None selected</em>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <th><label for="job_title">Job Title</label></th>
            <td><input type="text" name="job_title" id="job_title" value="<?php echo esc_attr(get_user_meta($user->ID, 'job_title', true)); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="employer">Employer</label></th>
            <td>
                <input type="text" name="employer" id="employer" value="<?php echo esc_attr($workbooks_data['employer_name'] ?? get_user_meta($user->ID, 'employer', true)); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="telephone">Telephone</label></th>
            <td><input type="text" name="telephone" id="telephone" value="<?php echo esc_attr(get_user_meta($user->ID, 'telephone', true)); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="country">Country</label></th>
            <td><input type="text" name="country" id="country" value="<?php echo esc_attr(get_user_meta($user->ID, 'country', true)); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="town">Town / City</label></th>
            <td><input type="text" name="town" id="town" value="<?php echo esc_attr(get_user_meta($user->ID, 'town', true)); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="postcode">Post / Zip Code</label></th>
            <td><input type="text" name="postcode" id="postcode" value="<?php echo esc_attr(get_user_meta($user->ID, 'postcode', true)); ?>" class="regular-text" /></td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'workbooks_crm_show_custom_user_profile_fields');
add_action('edit_user_profile', 'workbooks_crm_show_custom_user_profile_fields');

// Save fields on profile update
function workbooks_crm_save_custom_user_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) return false;

    $fields = [
        'person_title', 'job_title', 'employer_name',
        'telephone', 'country', 'town', 'postcode'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_user_meta($user_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
    
    // DTR marketing preferences sync to usermeta and Workbooks
    $dtr_fields = [
        'cf_person_dtr_news',
        'cf_person_dtr_events',
        'cf_person_dtr_subscriber',
        'cf_person_dtr_third_party',
        'cf_person_dtr_webinar',
        'cf_person_dtr_news_and_events',
    ];
    foreach ($dtr_fields as $field) {
        $value = isset($_POST[$field]) ? 1 : 0;
        update_user_meta($user_id, $field, $value);
    }
    
    // Sync to Workbooks
    $user = get_userdata($user_id);
    $email = $user->user_email;
    if (function_exists('get_workbooks_instance') && $email) {
        try {
            $workbooks = get_workbooks_instance();
            $person = $workbooks->assertGet('crm/people.api', [
                '_limit' => 1,
                '_ff[]' => 'main_location[email]',
                '_ft[]' => 'eq',
                '_fc[]' => $email,
            ]);
            if (!empty($person['data'][0]['id'])) {
                $person_id = $person['data'][0]['id'];
                $lock_version = $person['data'][0]['lock_version'] ?? null;
                $payload = [
                    'id' => $person_id,
                    'lock_version' => $lock_version,
                ];
                foreach ($dtr_fields as $field) {
                    $payload[$field] = get_user_meta($user_id, $field, true) ? 1 : 0;
                }
                if (function_exists('nf_debug_log')) {
                    nf_debug_log('DTR sync payload to Workbooks: ' . print_r($payload, true));
                }
                $objs = [$payload];
                $response = $workbooks->assertUpdate('crm/people.api', $objs);
                if (function_exists('nf_debug_log')) {
                    nf_debug_log('DTR sync response from Workbooks: ' . print_r($response, true));
                }
            }
        } catch (Exception $e) {
            if (function_exists('nf_debug_log')) {
                nf_debug_log('Error syncing user profile to Workbooks: ' . $e->getMessage());
            }
        }
    }
    
    // Save DTR memberships to usermeta
    $selected = isset($_POST['dtr_memberships']) && is_array($_POST['dtr_memberships']) ? array_map('intval', $_POST['dtr_memberships']) : [];
    update_user_meta($user_id, 'dtr_memberships', $selected);
    
    // Optionally, sync to Workbooks
    if (function_exists('get_workbooks_instance') && $email) {
        try {
            $workbooks = get_workbooks_instance();
            $current = workbooks_crm_get_user_memberships($email);
            $to_add = array_diff($selected, $current);
            $to_remove = array_diff($current, $selected);
            // Add new memberships
            foreach ($to_add as $campaign_id) {
                $payload = [[
                    'person[email]' => $email,
                    'campaign_id' => $campaign_id,
                ]];
                $workbooks->assertCreate('crm/campaign_membership.api', $payload);
            }
            // Remove unchecked memberships
            foreach ($to_remove as $campaign_id) {
                // Find the membership ID to delete
                $memberships = $workbooks->assertGet('crm/campaign_membership.api', [
                    '_limit' => 1,
                    '_ff[]' => 'person[email]',
                    '_ft[]' => 'eq',
                    '_fc[]' => $email,
                    '_ff[]' => 'campaign_id',
                    '_ft[]' => 'eq',
                    '_fc[]' => $campaign_id,
                    '_select_columns[]' => ['id'],
                ]);
                if (!empty($memberships['data'][0]['id'])) {
                    $workbooks->assertDelete('crm/campaign_membership.api', [$memberships['data'][0]['id']]);
                }
            }
        } catch (Exception $e) {
            if (function_exists('nf_debug_log')) {
                nf_debug_log('Error syncing user memberships to Workbooks: ' . $e->getMessage());
            }
        }
    }
}
add_action('personal_options_update', 'workbooks_crm_save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'workbooks_crm_save_custom_user_profile_fields');
