<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) {
    echo '<p>You do not have permission to use this form.</p>';
    return;
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dtr_admin_test_reg_submit'])) {
    $first = sanitize_text_field($_POST['first_name'] ?? 'TestFirst');
    $last = sanitize_text_field($_POST['last_name'] ?? 'TestLast');
    $email = sanitize_email($_POST['email'] ?? 'testuser_' . time() . '@supersonicplayground.com');
    $employer = sanitize_text_field($_POST['employer'] ?? 'Supersonic Playground Ltd');
    $town = sanitize_text_field($_POST['town'] ?? 'London');
    $country = sanitize_text_field($_POST['country'] ?? 'United Kingdom');
    $telephone = sanitize_text_field($_POST['telephone'] ?? '0123456789');
    $postcode = sanitize_text_field($_POST['postcode'] ?? 'SE10 9XY');
    $job_title = sanitize_text_field($_POST['job_title'] ?? 'Developer');
    $title = sanitize_text_field($_POST['person_personal_title'] ?? 'Mr.');
    $test_user_id = email_exists($email);

    if (!$test_user_id) {
        $random_pass = wp_generate_password(12, false);
        $test_user_id = wp_create_user($email, $random_pass, $email);
        if (is_wp_error($test_user_id)) {
            $msg = '<div class="error"><p>Could not create test user: ' . esc_html($test_user_id->get_error_message()) . '</p></div>';
            if (function_exists('workbooks_log')) workbooks_log("Test registration failed: Could not create user $email: " . $test_user_id->get_error_message());
        }
    }

    if ($test_user_id && !is_wp_error($test_user_id)) {
        $meta_fields = [
            'first_name' => $first,
            'last_name' => $last,
            'employer' => $employer,
            'employer_name' => $employer,
            'town' => $town,
            'country' => $country,
            'telephone' => $telephone,
            'postcode' => $postcode,
            'job_title' => $job_title,
            'person_personal_title' => $title,
            'created_via_ninja_form' => 1,
            'cf_person_dtr_subscriber_type' => 'Prospect',
            'cf_person_dtr_web_member' => 1,
            'lead_source_type' => 'Online Registration',
            'cf_person_is_person_active_or_inactive' => 'Active',
            'cf_person_data_source_detail' => 'DTR Member Registration'
        ];
        $subs = [
            'cf_person_dtr_news' => isset($_POST['cf_person_dtr_news']) ? 1 : 0,
            'cf_person_dtr_events' => isset($_POST['cf_person_dtr_events']) ? 1 : 0,
            'cf_person_dtr_third_party' => isset($_POST['cf_person_dtr_third_party']) ? 1 : 0,
            'cf_person_dtr_webinar' => isset($_POST['cf_person_dtr_webinar']) ? 1 : 0
        ];
        $interests = [
            'cf_person_business' => isset($_POST['cf_person_business']) ? 1 : 0,
            'cf_person_diseases' => isset($_POST['cf_person_diseases']) ? 1 : 0,
            'cf_person_drugs_therapies' => isset($_POST['cf_person_drugs_therapies']) ? 1 : 0,
            'cf_person_genomics_3774' => isset($_POST['cf_person_genomics_3774']) ? 1 : 0,
            'cf_person_research_development' => isset($_POST['cf_person_research_development']) ? 1 : 0,
            'cf_person_technology' => isset($_POST['cf_person_technology']) ? 1 : 0,
            'cf_person_tools_techniques' => isset($_POST['cf_person_tools_techniques']) ? 1 : 0
        ];
        foreach ($meta_fields as $key => $value) {
            update_user_meta($test_user_id, $key, $value);
        }
        foreach ($subs as $key => $value) {
            update_user_meta($test_user_id, $key, $value);
        }
        foreach ($interests as $key => $value) {
            update_user_meta($test_user_id, $key, $value);
        }
        $workbooks = get_workbooks_instance();
        if (!$workbooks) {
            $msg = '<div class="error"><p>Workbooks API initialization failed.</p></div>';
            if (function_exists('workbooks_log')) workbooks_log("Test registration failed: Workbooks API initialization failed for $email");
        } else {
            $person_id = get_user_meta($test_user_id, 'workbooks_person_id', true);
            $payload = [
                'person_first_name' => $first,
                'person_last_name' => $last,
                'main_location[email]' => $email,
                'cf_person_claimed_employer' => $employer,
                'created_through_reference' => 'wp_user_' . $test_user_id,
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
                'cf_person_data_source_detail' => 'DTR Member Registration'
            ];
            $payload = array_merge($payload, $subs, $interests);
            $org_id = function_exists('workbooks_get_or_create_organisation_id') ? workbooks_get_or_create_organisation_id($employer) : null;
            if ($org_id) {
                $payload['main_employer'] = $org_id;
            }
            try {
                if ($person_id) {
                    $payload['id'] = $person_id;
                    $existing = $workbooks->assertGet('crm/people', [
                        '_start' => 0,
                        '_limit' => 1,
                        '_ff[]' => 'id',
                        '_ft[]' => 'eq',
                        '_fc[]' => $person_id,
                        '_select_columns[]' => ['id', 'lock_version']
                    ]);
                    $lock_version = $existing['data'][0]['lock_version'] ?? null;
                    if ($lock_version !== null) {
                        $payload['lock_version'] = $lock_version;
                        $workbooks->assertUpdate('crm/people', [$payload]);
                        if (function_exists('workbooks_log')) workbooks_log("Updated Workbooks person ID $person_id for test user $email");
                    }
                } else {
                    $result = $workbooks->assertCreate('crm/people', [$payload]);
                    if (!empty($result['data'][0]['id'])) {
                        update_user_meta($test_user_id, 'workbooks_person_id', $result['data'][0]['id']);
                        update_user_meta($test_user_id, 'workbooks_object_ref', $result['data'][0]['object_ref'] ?? '');
                        if (function_exists('workbooks_log')) workbooks_log("Created Workbooks person ID {$result['data'][0]['id']} for test user $email");
                    }
                }
                $msg = '<div class="updated"><p>Test registration created and synced to Workbooks for user ' . esc_html($email) . '.</p></div>';
            } catch (Exception $e) {
                $msg = '<div class="error"><p>Workbooks error: ' . esc_html($e->getMessage()) . '</p></div>';
                if (function_exists('workbooks_log')) workbooks_log("Test registration failed for $email: " . $e->getMessage());
            }
        }
    }
}
echo $msg;
?>
<h2>Membership Sign Up (Test Registration)</h2>
<form method="post">
    <p><label>Title<br><input type="text" name="person_personal_title" value="Mr." readonly></label></p>
    <p><label>First Name<br><input type="text" name="first_name" value="TestFirst" readonly></label></p>
    <p><label>Last Name<br><input type="text" name="last_name" value="TestLast" readonly></label></p>
    <p><label>Email Address<br><input type="email" name="email" value="testuser_<?php echo time(); ?>@supersonicplayground.com" readonly></label></p>
    <p><label>Employer<br><input type="text" name="employer" value="Supersonic Playground Ltd" readonly></label></p>
    <p><label>Job Title<br><input type="text" name="job_title" value="Developer" readonly></label></p>
    <p><label>Town / City<br><input type="text" name="town" value="London" readonly></label></p>
    <p><label>Country<br><input type="text" name="country" value="United Kingdom" readonly></label></p>
    <p><label>Telephone<br><input type="text" name="telephone" value="0123456789" readonly></label></p>
    <p><label>Post / Zip Code<br><input type="text" name="postcode" value="SE10 9XY" readonly></label></p>
    <fieldset>
        <legend><strong>Subscriptions</strong></legend>
        <?php
        $subs = [
            'cf_person_dtr_news' => 'DTR News',
            'cf_person_dtr_events' => 'DTR Events',
            'cf_person_dtr_third_party' => 'DTR Third Party',
            'cf_person_dtr_webinar' => 'DTR Webinar',
        ];
        foreach ($subs as $k => $label): ?>
            <label style="display:block;">
                <input type="checkbox" name="<?php echo esc_attr($k); ?>" value="1" checked disabled>
                <?php echo esc_html($label); ?>
            </label>
        <?php endforeach; ?>
    </fieldset>
    <fieldset>
        <legend><strong>Interests</strong></legend>
        <?php
        $interests = [
            'cf_person_business' => 'Business',
            'cf_person_diseases' => 'Diseases',
            'cf_person_drugs_therapies' => 'Drugs & Therapies',
            'cf_person_genomics_3774' => 'Genomics',
            'cf_person_research_development' => 'Research & Development',
            'cf_person_technology' => 'Technology',
            'cf_person_tools_techniques' => 'Tools & Techniques',
        ];
        foreach ($interests as $k => $label): ?>
            <label style="display:block;">
                <input type="checkbox" name="<?php echo esc_attr($k); ?>" value="1" checked disabled>
                <?php echo esc_html($label); ?>
            </label>
        <?php endforeach; ?>
    </fieldset>
    <fieldset>
        <legend><strong>DTR Required Fields (synced, not editable)</strong></legend>
        <label>Subscriber Type: <input type="text" value="Prospect" readonly></label><br>
        <label>Web Member: <input type="text" value="1" readonly></label><br>
        <label>Lead Source Type: <input type="text" value="Online Registration" readonly></label><br>
        <label>Active/Inactive: <input type="text" value="Active" readonly></label><br>
        <label>Data Source Detail: <input type="text" value="DTR Member Registration" readonly></label>
    </fieldset>
    <input type="submit" name="dtr_admin_test_reg_submit" value="Run Test Registration" class="button button-primary">
</form>