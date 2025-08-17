<?php
if (!defined('ABSPATH')) exit;

// Shortcode: [dtr_user_topics_of_interest]
add_shortcode('dtr_user_topics_of_interest', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to update your interests.</p>';
    $user_id = get_current_user_id();
    $msg = '';
    $interests_fields = [
        'cf_person_business' => 'Business',
        'cf_person_diseases' => 'Diseases',
        'cf_person_drugs_therapies' => 'Drugs & Therapies',
        'cf_person_genomics_3774' => 'Genomics',
        'cf_person_research_development' => 'Research & Development',
        'cf_person_technology' => 'Technology',
        'cf_person_tools_techniques' => 'Tools & Techniques',
    ];
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_interests']) && isset($_POST['dtr_interests_update_nonce']) && wp_verify_nonce($_POST['dtr_interests_update_nonce'], 'dtr_interests_update')) {
        $values = [];
        $selected_interests = [];
        foreach ($interests_fields as $field => $label) {
            $v = !empty($_POST[$field]) ? 1 : 0;
            update_user_meta($user_id, $field, $v);
            $values[$field] = $v;
            if ($v) $selected_interests[] = $field;
        }
        
        // Map TOI to AOI and save AOI fields
        if (function_exists('dtr_map_toi_to_aoi')) {
            $aoi_mapping = dtr_map_toi_to_aoi($selected_interests);
            foreach ($aoi_mapping as $k => $v) {
                update_user_meta($user_id, $k, $v);
                $values[$k] = $v; // Include AOI in Workbooks payload
            }
        }
        
        // Update Workbooks
        if (function_exists('get_workbooks_instance')) {
            $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
            if ($person_id) {
                $workbooks = get_workbooks_instance();
                try {
                    // Fetch current lock_version using direct endpoint (same as marketing preferences)
                    error_log('DTR SHORTCODE DEBUG: Fetching lock_version for person_id: ' . $person_id);
                    $existing = $workbooks->assertGet('crm/people/' . $person_id . '.api', []);
                    error_log('DTR SHORTCODE DEBUG: lock_version fetch response: ' . json_encode($existing));
                    
                    // Check if we got the correct person ID in response
                    if (!isset($existing['data']['id'])) {
                        error_log('DTR SHORTCODE ERROR: No person found with ID ' . $person_id);
                        throw new Exception('No person found in Workbooks with ID: ' . $person_id);
                    }
                    
                    if ($existing['data']['id'] != $person_id) {
                        error_log('DTR SHORTCODE ERROR: Requested person ID ' . $person_id . ' but got ID ' . $existing['data']['id']);
                        throw new Exception('Workbooks returned wrong person record. Requested: ' . $person_id . ', Got: ' . $existing['data']['id']);
                    }
                    
                    $lock_version = isset($existing['data']['lock_version']) ? $existing['data']['lock_version'] : null;
                    if ($lock_version === null) {
                        throw new Exception('Could not retrieve lock_version for Workbooks person ID ' . $person_id);
                    }
                    $payload = array_merge(['id' => $person_id, 'lock_version' => $lock_version], $values);
                    $workbooks->assertUpdate('crm/people.api', $payload);
                    $msg = '<div class="updated">Topics of interest updated!</div>';
                } catch (Exception $e) {
                    $msg = '<div class="error">Workbooks error: ' . esc_html($e->getMessage()) . '</div>';
                }
            }
        }
        if (!$msg) $msg = '<div class="updated">Topics of interest updated!</div>';
    }
    // Get current values from WordPress user meta only
    wp_enqueue_style('dtr-shortcodes-custom', plugin_dir_url(__FILE__) . '../assets/dtr-shortcodes-custom.css', [], null);
    ob_start();
    echo $msg;
    ?>
    <form method="post" class="dtr-custom-checkbox-form">
        <fieldset>
            
            <div class="workbooks-checkboxes dtr subscription list">
            <?php $i = 0; foreach ($interests_fields as $field => $label): $i++; ?>
                <?php $checked = get_user_meta($user_id, $field, true); ?>
                <label>
                    <input type="checkbox" id="dtr_interest_<?php echo $i; ?>" name="<?php echo esc_attr($field); ?>" value="1" <?php checked($checked); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
            </div>
            <?php wp_nonce_field('dtr_interests_update', 'dtr_interests_update_nonce'); ?>
        </fieldset>
        <input type="submit" name="update_interests" value="Update Interests">
    </form>
    <?php
    return ob_get_clean();
});

// Shortcode: [dtr_user_marketing_preferences]
add_shortcode('dtr_user_marketing_preferences', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to update your marketing preferences.</p>';
    $user_id = get_current_user_id();
    $msg = '';
    $dtr_fields = [
        'cf_person_dtr_news'        => 'Newsletter: News, articles and analysis by email',
        'cf_person_dtr_third_party' => 'Third party: Application notes, product developments and updates from our trusted partners by email',
        'cf_person_dtr_webinar'     => 'Webinar: Information about webinars by email',
        'cf_person_dtr_events'      => 'Event: Information about events by email',
    ];
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_marketing']) && isset($_POST['dtr_marketing_update_nonce']) && wp_verify_nonce($_POST['dtr_marketing_update_nonce'], 'dtr_marketing_update')) {
        $values = [];
        foreach ($dtr_fields as $field => $label) {
            $v = !empty($_POST[$field]) ? 1 : 0;
            update_user_meta($user_id, $field, $v);
            $values[$field] = $v;
        }
        // Update Workbooks
        if (function_exists('get_workbooks_instance')) {
            $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
            if ($person_id) {
                $workbooks = get_workbooks_instance();
                try {
                    // Fetch current lock_version using direct endpoint (same as marketing preferences)
                    error_log('DTR SHORTCODE DEBUG: Fetching lock_version for person_id: ' . $person_id);
                    $existing = $workbooks->assertGet('crm/people/' . $person_id . '.api', []);
                    error_log('DTR SHORTCODE DEBUG: lock_version fetch response: ' . json_encode($existing));
                    
                    // Check if we got the correct person ID in response
                    if (!isset($existing['data']['id'])) {
                        error_log('DTR SHORTCODE ERROR: No person found with ID ' . $person_id);
                        throw new Exception('No person found in Workbooks with ID: ' . $person_id);
                    }
                    
                    if ($existing['data']['id'] != $person_id) {
                        error_log('DTR SHORTCODE ERROR: Requested person ID ' . $person_id . ' but got ID ' . $existing['data']['id']);
                        throw new Exception('Workbooks returned wrong person record. Requested: ' . $person_id . ', Got: ' . $existing['data']['id']);
                    }
                    
                    $lock_version = isset($existing['data']['lock_version']) ? $existing['data']['lock_version'] : null;
                    if ($lock_version === null) {
                        throw new Exception('Could not retrieve lock_version for Workbooks person ID ' . $person_id);
                    }
                    $payload = array_merge(['id' => $person_id, 'lock_version' => $lock_version], $values);
                    $workbooks->assertUpdate('crm/people.api', $payload);
                    $msg = '<div class="updated">Marketing preferences updated!</div>';
                } catch (Exception $e) {
                    $msg = '<div class="error">Workbooks error: ' . esc_html($e->getMessage()) . '</div>';
                }
            }
        }
        if (!$msg) $msg = '<div class="updated">Marketing preferences updated!</div>';
    }
    // Get current values from WordPress user meta only
    wp_enqueue_style('dtr-shortcodes-custom', plugin_dir_url(__FILE__) . '../assets/dtr-shortcodes-custom.css', [], null);
    ob_start();
    echo $msg;
    ?>
    <form method="post" class="dtr-custom-checkbox-form">
        <fieldset>
            
            <div class="workbooks-checkboxes dtr subscription list">
            <?php $i = 0; foreach ($dtr_fields as $field => $label): $i++; ?>
                <?php $checked = get_user_meta($user_id, $field, true); ?>
                <label>
                    <input type="checkbox" id="dtr_marketing_<?php echo $i; ?>" name="<?php echo esc_attr($field); ?>" value="1" <?php checked($checked); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
            </div>
            <?php wp_nonce_field('dtr_marketing_update', 'dtr_marketing_update_nonce'); ?>
        </fieldset>
        <input type="submit" name="update_marketing" value="Update Preferences">
    </form>
    <?php
    return ob_get_clean();
});


// Shortcode: [dtr_interests_form] (DEPRECATED, returns nothing)
add_shortcode('dtr_interests_form', function() {
    return '';
});

// Shortcode: [dtr_marketing_preferences_form] (DISPLAYS CHECKBOXES AND UPDATES WORKBOOKS)
add_shortcode('dtr_marketing_preferences_form', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to update your marketing preferences.</p>';
    $user_id = get_current_user_id();
    $msg = '';
    $fields = [
        'cf_person_dtr_news'        => 'Newsletter: News, articles and analysis by email',
        'cf_person_dtr_third_party' => 'Third party: Application notes, product developments and updates from our trusted partners by email',
        'cf_person_dtr_webinar'     => 'Webinar: Information about webinars by email',
        'cf_person_dtr_events'      => 'Event: Information about events by email',
    ];
    // Handle form submission
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['update_marketing']) &&
        isset($_POST['dtr_marketing_update_nonce']) &&
        wp_verify_nonce($_POST['dtr_marketing_update_nonce'], 'dtr_marketing_update')
    ) {
        $values = [];
        foreach ($fields as $k => $label) {
            $v = !empty($_POST[$k]) ? 1 : 0;
            update_user_meta($user_id, $k, $v);
            $values[$k] = $v;
        }
        // Update Workbooks
        if (function_exists('get_workbooks_instance')) {
            $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
            error_log('DTR SHORTCODE DEBUG: User ID: ' . $user_id . ', stored workbooks_person_id: ' . $person_id);
            if ($person_id) {
                $workbooks = get_workbooks_instance();
                try {
                    // Try a direct approach - fetch the specific record by ID using the .api endpoint
                    error_log('DTR SHORTCODE DEBUG: Fetching lock_version for person_id: ' . $person_id);
                    $existing = $workbooks->assertGet('crm/people/' . $person_id . '.api', []);
                    error_log('DTR SHORTCODE DEBUG: lock_version fetch response: ' . json_encode($existing));
                    
                    // Check if we got the correct person ID in response
                    if (!isset($existing['data']['id'])) {
                        error_log('DTR SHORTCODE ERROR: No person found with ID ' . $person_id);
                        throw new Exception('No person found in Workbooks with ID: ' . $person_id);
                    }
                    
                    if ($existing['data']['id'] != $person_id) {
                        error_log('DTR SHORTCODE ERROR: Requested person ID ' . $person_id . ' but got ID ' . $existing['data']['id']);
                        throw new Exception('Workbooks returned wrong person record. Requested: ' . $person_id . ', Got: ' . $existing['data']['id']);
                    }
                    
                    $lock_version = isset($existing['data']['lock_version']) ? $existing['data']['lock_version'] : null;
                    if ($lock_version === null) {
                        throw new Exception('Could not retrieve lock_version for Workbooks person ID ' . $person_id);
                    }
                    $payload = array_merge(['id' => $person_id, 'lock_version' => $lock_version], $values);
                    error_log('DTR SHORTCODE DEBUG: About to update with payload: ' . json_encode($payload));
                    
                    // Use regular update instead of assertUpdate to capture the raw response
                    $result = $workbooks->update('crm/people.api', $payload);
                    
                    error_log('DTR SHORTCODE DEBUG: Raw update result: ' . json_encode($result));
                    error_log('DTR SHORTCODE DEBUG: Result condensed status: ' . $workbooks->condensedStatus($result));
                    
                    // Now check if it's successful
                    if ($workbooks->condensedStatus($result) !== 'ok') {
                        error_log('DTR SHORTCODE ERROR: Update failed with status: ' . $workbooks->condensedStatus($result));
                        if (isset($result['errors'])) {
                            error_log('DTR SHORTCODE ERROR: API errors: ' . json_encode($result['errors']));
                        }
                        if (isset($result['affected_object_information'])) {
                            error_log('DTR SHORTCODE ERROR: Affected object info: ' . json_encode($result['affected_object_information']));
                        }
                        throw new Exception('Workbooks update failed: ' . $workbooks->condensedStatus($result));
                    }
                    $msg = '<div class="updated">Marketing preferences updated!</div>';
                } catch (Exception $e) {
                    error_log('DTR SHORTCODE ERROR: Exception details: ' . $e->getMessage());
                    error_log('DTR SHORTCODE ERROR: Exception trace: ' . $e->getTraceAsString());
                    $msg = '<div class="error">Workbooks error: ' . esc_html($e->getMessage()) . '</div>';
                }
            }
        }
        if (!$msg) $msg = '<div class="updated">Marketing preferences updated!</div>';
    }
    // Get current values (from WP only, not Workbooks)
    wp_enqueue_style('dtr-shortcodes-custom', plugin_dir_url(__FILE__) . '../assets/dtr-shortcodes-custom.css', [], null);
    ob_start();
    echo $msg;
    ?>
    <form method="post" class="dtr-custom-checkbox-form">
        <fieldset>
            
            <div class="workbooks-checkboxes dtr subscription list">
            <?php $i = 0; foreach ($fields as $k => $label): $i++; ?>
                <?php $checked = get_user_meta($user_id, $k, true); ?>
                <label>
                    <input type="checkbox" id="dtr_marketing_<?php echo $i; ?>" name="<?php echo esc_attr($k); ?>" value="1" <?php checked($checked); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
            </div>
            <?php wp_nonce_field('dtr_marketing_update', 'dtr_marketing_update_nonce'); ?>
        </fieldset>
        <input type="submit" name="update_marketing" value="Update Preferences">
    </form>
    <?php
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('.dtr-custom-checkbox-form');
      if (form) {
        form.addEventListener('submit', function(e) {
          const checkboxes = form.querySelectorAll('input[type="checkbox"]');
          let changed = [];
          checkboxes.forEach(cb => {
            changed.push({name: cb.name, checked: cb.checked});
          });
          console.log('Submitting marketing preferences:', changed);
        });
      }
    });
    </script>
    <?php
    return ob_get_clean();
});
