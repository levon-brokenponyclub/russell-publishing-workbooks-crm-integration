<?php
if (!defined('ABSPATH')) exit;

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

function get_field_value($person, $field, $user_id, $meta_key = '') {
    if (!empty($person[$field])) {
        return esc_attr($person[$field]);
    } elseif ($meta_key && $value = get_user_meta($user_id, $meta_key, true)) {
        return esc_attr($value);
    }
    return '';
}
?>
<h2>Update Fixed Workbooks Person Record (ID: 4208693)</h2>
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
            'person_personal_title', 'person_first_name', 'person_last_name', 'person_job_title',
            'main_location[email]', 'main_location[telephone]', 'main_location[country]',
            'main_location[town]', 'main_location[postcode]',
            'employer_name'
        ]
    ]);
    $person = $existing['data'][0] ?? [];
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