<?php
if (!defined('ABSPATH')) exit;

function dtr_my_account_debug_log($message, $data = null) {
    $log_file = WP_PLUGIN_DIR . '/logs/my-account-update-debug.log';
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0777, true);
    }
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $entry .= ' | ' . (is_string($data) ? $data : json_encode($data));
    }
    file_put_contents($log_file, $entry . PHP_EOL, FILE_APPEND);
}

add_shortcode('dtr-my-account-details', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to view your account details.</p>';

    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;

    // Get user meta for display and initial form values
    $title = get_user_meta($user_id, 'person_title', true);
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);
    $job_title = get_user_meta($user_id, 'job_title', true);
    $employer = get_user_meta($user_id, 'employer_name', true);
    $telephone = get_user_meta($user_id, 'telephone', true);
    $country = get_user_meta($user_id, 'country', true);
    $town_city = get_user_meta($user_id, 'town', true);
    $post_zip_code = get_user_meta($user_id, 'postcode', true);

    $personal_update_success = false;
    $personal_update_error = '';

    // Handle POST (for this form only)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_personal_details']) && isset($_POST['first-name']) && isset($_POST['last-name'])) {
        dtr_my_account_debug_log('Started personal details update POST', $_POST);

        // Sanitize and update user meta
        $person_title = sanitize_text_field($_POST['title-select']);
        $first = sanitize_text_field($_POST['first-name']);
        $last = sanitize_text_field($_POST['last-name']);
        $job = sanitize_text_field($_POST['job-title']);
        $employer_s = sanitize_text_field($_POST['employer']);
        $tel = sanitize_text_field($_POST['telephone']);
        $country_s = sanitize_text_field($_POST['country']);
        $town = sanitize_text_field($_POST['town-city']);
        $zip = sanitize_text_field($_POST['post-zip-code']);

        $local_changes = [
            'person_title' => $person_title,
            'first_name' => $first,
            'last_name' => $last,
            'job_title' => $job,
            'employer_name' => $employer_s,
            'telephone' => $tel,
            'country' => $country_s,
            'town' => $town,
            'postcode' => $zip,
        ];
        dtr_my_account_debug_log('Will update user meta for user', ['user_id' => $user_id, 'changes' => $local_changes]);

        foreach ($local_changes as $meta_key => $meta_val) {
            update_user_meta($user_id, $meta_key, $meta_val);
        }

        // Push to Workbooks (if available)
        if (function_exists('get_workbooks_instance')) {
            $workbooks = get_workbooks_instance();
            $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
            dtr_my_account_debug_log('Trying to update Workbooks', ["person_id" => $person_id]);
            if ($person_id) {
                try {
                    $existing = $workbooks->assertGet('crm/people/' . $person_id . '.api', []);
                    dtr_my_account_debug_log('Fetched existing Workbooks person', $existing);
                    $lock_version = isset($existing['data']['lock_version']) ? $existing['data']['lock_version'] : null;
                    if ($lock_version !== null) {
                        $payload = [
                            'id' => $person_id,
                            'lock_version' => $lock_version,
                            'person_title' => $person_title,
                            'person_first_name' => $first,
                            'person_last_name' => $last,
                            'person_job_title' => $job,
                            'main_location[telephone]' => $tel,
                            'main_location[country]' => $country_s,
                            'main_location[town]' => $town,
                            'main_location[postcode]' => $zip,
                            'cf_person_claimed_employer' => $employer_s,
                        ];
                        dtr_my_account_debug_log('Workbooks update payload', $payload);
                        $result = $workbooks->assertUpdate('crm/people.api', $payload);
                        dtr_my_account_debug_log('Workbooks update result', $result);
                        $personal_update_success = true;
                        // Refresh values for display
                        $title = $person_title;
                        $first_name = $first;
                        $last_name = $last;
                        $job_title = $job;
                        $employer = $employer_s;
                        $telephone = $tel;
                        $country = $country_s;
                        $town_city = $town;
                        $post_zip_code = $zip;
                    } else {
                        $personal_update_error = 'Could not retrieve lock_version from Workbooks.';
                        dtr_my_account_debug_log('Workbooks error', $personal_update_error);
                    }
                } catch (Exception $e) {
                    $personal_update_error = 'Workbooks update error: ' . $e->getMessage();
                    dtr_my_account_debug_log('Exception during Workbooks update', $personal_update_error);
                }
            } else {
                $personal_update_error = 'No Workbooks person_id found for user.';
                dtr_my_account_debug_log('Workbooks error', $personal_update_error);
            }
        } else {
            $personal_update_error = 'get_workbooks_instance() not found.';
            dtr_my_account_debug_log('Workbooks error', $personal_update_error);
        }
    }

    ob_start();
    ?>
    <?php if ($personal_update_success): ?>
        <div class="updated" style="margin-bottom:1em;">Personal details updated successfully!</div>
    <?php elseif ($personal_update_error): ?>
        <div class="error" style="margin-bottom:1em;"><?php echo esc_html($personal_update_error); ?></div>
    <?php endif; ?>
    <form class="dtr-account-form" method="post" action="">
        <section>
            <div class="dtr-form-group">
                <label class="dtr-form-label full-width">
                    Title<span>*</span>
                    <select name="title-select" id="title-select" class="dtr-form-input" required>
                        <option disabled="disabled" value="" <?php if (empty($title)) echo 'selected'; ?>>Select title</option>
                        <option value="Mr" <?php if ($title == 'Mr') echo 'selected'; ?>>Mr</option>
                        <option value="Mrs" <?php if ($title == 'Mrs') echo 'selected'; ?>>Mrs</option>
                        <option value="Miss" <?php if ($title == 'Miss') echo 'selected'; ?>>Miss</option>
                        <option value="Ms" <?php if ($title == 'Ms') echo 'selected'; ?>>Ms</option>
                        <option value="Mx" <?php if ($title == 'Mx') echo 'selected'; ?>>Mx</option>
                        <option value="Dr" <?php if ($title == 'Dr') echo 'selected'; ?>>Dr</option>
                        <option value="Prof" <?php if ($title == 'Prof') echo 'selected'; ?>>Prof</option>
                        <option value="Other" <?php if (empty($title) || !in_array($title, ['Mr', 'Mrs', 'Miss', 'Ms', 'Mx', 'Dr', 'Prof'])) echo 'selected'; ?>>Other</option>
                    </select>
                </label>
                <label class="dtr-form-label half-width">
                    First Name<span>*</span>
                    <input type="text" name="first-name" id="first-name" class="dtr-form-input" value="<?php echo esc_attr($first_name); ?>" required>
                </label>
                <label class="dtr-form-label half-width">
                    Last Name<span>*</span>
                    <input type="text" name="last-name" id="last-name" class="dtr-form-input" value="<?php echo esc_attr($last_name); ?>" required>
                </label>
                <label class="dtr-form-label half-width">
                    Job Title<span>*</span>
                    <input type="text" name="job-title" id="job-title" class="dtr-form-input" value="<?php echo esc_attr($job_title); ?>" required>
                </label>
                <label class="dtr-form-label half-width">
                    Employer<span>*</span>
                    <input type="text" name="employer" id="employer" class="dtr-form-input" value='<?php echo esc_attr($employer); ?>' required>
                    <!-- <div class="dtr-account-form"><?php // echo do_shortcode('[workbooks_employer_select]'); ?></div> -->
                </label>
                <label class="dtr-form-label full-width">
                    Telephone<span>*</span>
                    <input type="tel" name="telephone" id="telephone" class="dtr-form-input" value='<?php echo esc_attr($telephone); ?>' required>
                </label>
                <label class="dtr-form-label full-width">
                    Country<span>*</span>
                    <select name="country" id="country" class="dtr-form-input full-iso-country-names" required>
                        <?php echo nf_full_country_names_options($country); ?>
                    </select>
                </label>
                <label class="dtr-form-label half-width">
                    Town/City<span>*</span>
                    <input type="text" name="town-city" id="town-city" class="dtr-form-input" value='<?php echo esc_attr($town_city); ?>' required>
                </label>
                <label class="dtr-form-label half-width">
                    Postal/Zip Code<span>*</span>
                    <input type="text" name="post-zip-code" id="post-zip-code" class="dtr-form-input" value='<?php echo esc_attr($post_zip_code); ?>' required>
                </label>
            </div>
            <input type="submit" class="dtr-input-button custom-btn-decorated" name="save_personal_details" value="Update Details">
        </section>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.dtr-account-form');

        if (!form) {
            console.error('Form with class .dtr-account-form not found in the DOM.');
            return;
        }

        if (!(form instanceof HTMLFormElement)) {
            console.error('The selected element is not a valid HTMLFormElement.');
            return;
        }

        console.log('Form element found and is valid.'); // Debugging log

        const btn = form.querySelector('.dtr-input-button');
        const originalBtnText = btn.value;
        let dots = 0;
        let submitting = false;
        let submitInterval;

        function animateSubmitting() {
            dots = (dots + 1) % 4;
            btn.value = 'Submitting' + '.'.repeat(dots);
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            if (!form.checkValidity()) {
                console.error('Form validation failed. Please check the required fields.');
                return;
            }

            btn.disabled = true;
            submitting = true;
            submitInterval = setInterval(animateSubmitting, 500);

            // Simulate form submission for debugging
            setTimeout(function() {
                clearInterval(submitInterval);
                btn.value = 'Details Updated!';

                setTimeout(function() {
                    btn.value = originalBtnText;
                    btn.disabled = false;
                    submitting = false;
                }, 2000);
            }, 3000); // Simulate a 3-second submission delay

            // Fallback timeout to reset button state
            setTimeout(function() {
                if (submitting) {
                    clearInterval(submitInterval);
                    btn.disabled = false;
                    btn.value = originalBtnText;
                    submitting = false;
                    console.error('Fallback timeout triggered.');
                }
            }, 10000);
        });
    });
    </script>
    <?php
    return ob_get_clean();
});

add_shortcode('dtr-my-account-details_table', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to view your account details.</p>';

    $current_user = wp_get_current_user();
    $user_meta = [
        'title' => get_user_meta($current_user->ID, 'person_title', true),
        'first_name' => get_user_meta($current_user->ID, 'first_name', true),
        'last_name' => get_user_meta($current_user->ID, 'last_name', true),
        'email' => $current_user->user_email,
        'job_title' => get_user_meta($current_user->ID, 'job_title', true),
        'employer' => get_user_meta($current_user->ID, 'employer_name', true),
        'telephone' => get_user_meta($current_user->ID, 'telephone', true),
        'country' => get_user_meta($current_user->ID, 'country', true),
        'town_city' => get_user_meta($current_user->ID, 'town', true),
        'post_zip_code' => get_user_meta($current_user->ID, 'postcode', true),
    ];

    ob_start();
    ?>
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="2">Personal Details</th>
            </tr>
        </thead>
        <tbody>
            <tr><th>Title</th><td><?php echo esc_html($user_meta['title']); ?></td></tr>
            <tr><th>First Name</th><td><?php echo esc_html($user_meta['first_name']); ?></td></tr>
            <tr><th>Last Name</th><td><?php echo esc_html($user_meta['last_name']); ?></td></tr>
            <tr><th>Email Address</th><td><?php echo esc_html($user_meta['email']); ?></td></tr>
            <tr><th>Job Title</th><td><?php echo esc_html($user_meta['job_title']); ?></td></tr>
            <tr><th>Employer</th><td><?php echo esc_html($user_meta['employer']); ?></td></tr>
            <tr><th>Telephone</th><td><?php echo esc_html($user_meta['telephone']); ?></td></tr>
            <tr><th>Country</th><td><?php echo esc_html($user_meta['country']); ?></td></tr>
            <tr><th>Town / City</th><td><?php echo esc_html($user_meta['town_city']); ?></td></tr>
            <tr><th>Post / Zip Code</th><td><?php echo esc_html($user_meta['post_zip_code']); ?></td></tr>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
});

// Add AJAX action to fetch updated user details
add_action('wp_ajax_get_updated_user_details', function() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not logged in']);
    }

    $current_user = wp_get_current_user();
    $user_meta = [
        'title' => get_user_meta($current_user->ID, 'person_title', true),
        'first_name' => get_user_meta($current_user->ID, 'first_name', true),
        'last_name' => get_user_meta($current_user->ID, 'last_name', true),
        'job_title' => get_user_meta($current_user->ID, 'job_title', true),
        'employer' => get_user_meta($current_user->ID, 'employer_name', true),
        'telephone' => get_user_meta($current_user->ID, 'telephone', true),
        'country' => get_user_meta($current_user->ID, 'country', true),
        'town_city' => get_user_meta($current_user->ID, 'town', true),
        'post_zip_code' => get_user_meta($current_user->ID, 'postcode', true),
    ];

    wp_send_json_success(['user_meta' => $user_meta]);
});