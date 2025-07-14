<?php
if (!defined('ABSPATH')) exit;

add_shortcode('dtr_workbooks_form', 'dtr_workbooks_account_form');
function dtr_workbooks_account_form() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view this form.</p>';
    }

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $workbooks = get_workbooks_instance();
    $person = [];

    // Try to fetch person by workbooks_person_id
    $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
    if ($person_id) {
        try {
            $existing = $workbooks->assertGet('crm/people.api', [
                '_start' => 0,
                '_limit' => 1,
                '_ff[]' => 'id',
                '_ft[]' => 'eq',
                '_fc[]' => preg_replace('/^PERS-/', '', $person_id),
                '_select_columns[]' => [
                    'id', 'lock_version',
                    'person_title', 'person_first_name', 'person_last_name', 'person_job_title',
                    'main_location[email]', 'main_location[telephone]', 'main_location[country]',
                    'main_location[town]', 'main_location[postcode]',
                    'cf_person_claimed_employer', 'cf_person_dtr_areas_of_interest_v2',
                    'cf_person_dtr_news', 'cf_person_dtr_events', 'cf_person_dtr_subscriber',
                    'cf_person_dtr_third_party', 'cf_person_dtr_webinar', 'cf_person_dtr_news_and_events'
                ]
            ]);
            $person = $existing['data'][0] ?? [];
        } catch (Exception $e) {
            nf_debug_log("DTR ERROR: Failed to fetch person ID $person_id for user ID $user_id - " . $e->getMessage());
        }
    }

    // Fallback to email lookup
    if (empty($person)) {
        try {
            $existing = $workbooks->assertGet('crm/people.api', [
                '_start' => 0,
                '_limit' => 1,
                '_ff[]' => 'main_location[email]',
                '_ft[]' => 'eq',
                '_fc[]' => $user->user_email,
                '_select_columns[]' => [
                    'id', 'lock_version',
                    'person_title', 'person_first_name', 'person_last_name', 'person_job_title',
                    'main_location[email]', 'main_location[telephone]', 'main_location[country]',
                    'main_location[town]', 'main_location[postcode]',
                    'cf_person_claimed_employer', 'cf_person_dtr_areas_of_interest_v2',
                    'cf_person_dtr_news', 'cf_person_dtr_events', 'cf_person_dtr_subscriber',
                    'cf_person_dtr_third_party', 'cf_person_dtr_webinar', 'cf_person_dtr_news_and_events'
                ]
            ]);
            $person = $existing['data'][0] ?? [];
            if (!empty($person['id'])) {
                update_user_meta($user_id, 'workbooks_person_id', $person['id']);
            }
        } catch (Exception $e) {
            nf_debug_log("DTR ERROR: Failed to fetch person by email {$user->user_email} for user ID $user_id - " . $e->getMessage());
        }
    }

    function get_field_value($person, $field, $user_id, $meta_key = '') {
        if (!empty($person[$field])) {
            return esc_attr($person[$field]);
        } elseif ($meta_key && $value = get_user_meta($user_id, $meta_key, true)) {
            return esc_attr($value);
        }
        return '';
    }

    ob_start();
    ?>
    <form id="workbooks_update_user_form" method="post">
        <?php if (!empty($person['id'])): ?>
            <input type="hidden" name="person_id" value="<?php echo esc_attr($person['id']); ?>">
            <input type="hidden" name="lock_version" value="<?php echo esc_attr($person['lock_version']); ?>">
        <?php endif; ?>
        <p>
            <label for="person_title">Title<br>
                <?php
                if (function_exists('workbooks_crm_get_personal_titles')) {
                    $titles = workbooks_crm_get_personal_titles();
                    $current_title = get_field_value($person, 'person_title', $user_id, 'title');
                    echo '<select id="person_title" name="person_title">';
                    echo '<option value="">-- Select --</option>';
                    foreach ($titles as $title) {
                        $selected = ($current_title == $title) ? 'selected' : '';
                        echo '<option value="' . esc_attr($title) . '" ' . $selected . '>' . esc_html($title) . '</option>';
                    }
                    echo '</select>';
                } else {
                    echo '<input type="text" id="person_title" name="person_title" value="' . get_field_value($person, 'person_title', $user_id, 'title') . '" class="regular-text">';
                }
                ?>
            </label>
        </p>
        <p>
            <label for="person_first_name">First Name<br>
                <input type="text" id="person_first_name" name="person_first_name" value="<?php echo get_field_value($person, 'person_first_name', $user_id, 'first_name'); ?>" class="regular-text" required>
            </label>
        </p>
        <p>
            <label for="person_last_name">Last Name<br>
                <input type="text" id="person_last_name" name="person_last_name" value="<?php echo get_field_value($person, 'person_last_name', $user_id, 'last_name'); ?>" class="regular-text" required>
            </label>
        </p>
        <p>
            <label for="person_job_title">Job Title<br>
                <input type="text" id="person_job_title" name="person_job_title" value="<?php echo get_field_value($person, 'person_job_title', $user_id, 'job_title'); ?>" class="regular-text">
            </label>
        </p>
        <p>
            <label for="email">Email<br>
                <input type="email" id="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" class="regular-text" readonly>
                <small>Email is read-only and taken from your WordPress user account.</small>
            </label>
        </p>
        <p>
            <label for="telephone">Telephone<br>
                <input type="text" id="telephone" name="telephone" value="<?php echo get_field_value($person, 'main_location[telephone]', $user_id, 'telephone'); ?>" class="regular-text">
            </label>
        </p>
        <p>
            <label for="country">Country<br>
                <input type="text" id="country" name="country" value="<?php echo get_field_value($person, 'main_location[country]', $user_id, 'country'); ?>" class="regular-text">
            </label>
        </p>
        <p>
            <label for="town">Town / City<br>
                <input type="text" id="town" name="town" value="<?php echo get_field_value($person, 'main_location[town]', $user_id, 'town'); ?>" class="regular-text">
            </label>
        </p>
        <p>
            <label for="postcode">Post / Zip Code<br>
                <input type="text" id="postcode" name="postcode" value="<?php echo get_field_value($person, 'main_location[postcode]', $user_id, 'postcode'); ?>" class="regular-text">
            </label>
        </p>
        <p>
            <label for="employer">Employer<br>
                <input type="text" id="employer" name="employer" value="<?php echo get_field_value($person, 'cf_person_claimed_employer', $user_id, 'employer'); ?>" class="regular-text">
            </label>
        </p>
        <p>
            <label for="cf_person_dtr_areas_of_interest_v2">Areas of Interest<br>
                <?php
                if (function_exists('workbooks_crm_get_dtr_areas_of_interest')) {
                    $areas = workbooks_crm_get_dtr_areas_of_interest();
                    $current_areas = get_field_value($person, 'cf_person_dtr_areas_of_interest_v2', $user_id, 'cf_person_dtr_areas_of_interest_v2');
                    echo '<select id="cf_person_dtr_areas_of_interest_v2" name="cf_person_dtr_areas_of_interest_v2" multiple class="select2">';
                    foreach ($areas as $area) {
                        $selected = in_array($area, explode(',', $current_areas)) ? 'selected' : '';
                        echo '<option value="' . esc_attr($area) . '" ' . $selected . '>' . esc_html($area) . '</option>';
                    }
                    echo '</select>';
                } else {
                    echo '<input type="text" id="cf_person_dtr_areas_of_interest_v2" name="cf_person_dtr_areas_of_interest_v2" value="' . get_field_value($person, 'cf_person_dtr_areas_of_interest_v2', $user_id, 'cf_person_dtr_areas_of_interest_v2') . '" class="regular-text">';
                }
                ?>
            </label>
        </p>
        <fieldset style="margin-bottom:20px;">
            <legend><strong>Marketing Preferences</strong></legend>
            <?php
            $dtr_fields = [
                'cf_person_dtr_news' => 'DTR News',
                'cf_person_dtr_events' => 'DTR Events',
                'cf_person_dtr_subscriber' => 'DTR Subscriber',
                'cf_person_dtr_third_party' => 'DTR Third Party',
                'cf_person_dtr_webinar' => 'DTR Webinar',
                'cf_person_dtr_news_and_events' => 'DTR News & Events',
            ];
            foreach ($dtr_fields as $field => $label):
                $checked = get_user_meta($user_id, $field, true) || !empty($person[$field]);
                ?>
                <label style="display:block;">
                    <input type="checkbox" name="<?php echo esc_attr($field); ?>" value="1" <?php checked($checked); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php submit_button('Update Profile'); ?>
    </form>
    <script>
    jQuery(document).ready(function($) {
        $('.select2').select2();
    });
    </script>
    <?php
    return ob_get_clean();
}
?>