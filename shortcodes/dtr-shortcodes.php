<?php
// AJAX handlers
add_action('wp_ajax_update_topics', 'dtr_handle_topics_update');
add_action('wp_ajax_update_marketing', 'dtr_handle_marketing_update');

function dtr_handle_topics_update() {
    if (!check_ajax_referer('dtr_interests_update', 'dtr_interests_update_nonce', false)) {
        wp_send_json_error('Invalid nonce');
    }

    $user_id = get_current_user_id();
    // Corrected genomics key: previously mis-typed as cf_person_genomics_3744
    // Backward compatibility: accept old key in POST and map to new one.
    if (!empty($_POST['cf_person_genomics_3744']) && empty($_POST['cf_person_genomics_3774'])) {
        $_POST['cf_person_genomics_3774'] = $_POST['cf_person_genomics_3744'];
    }
    $interests_fields = [
        'cf_person_business',
        'cf_person_diseases',
        'cf_person_drugs_therapies',
        'cf_person_genomics_3774',
        'cf_person_research_development',
        'cf_person_technology',
        'cf_person_tools_techniques',
    ];

    // Use mapping and normalization from class-helper-functions.php
    if (!function_exists('dtr_normalize_toi_key') || !function_exists('dtr_get_toi_to_aoi_matrix') || !function_exists('dtr_get_aoi_field_names')) {
        wp_send_json_error('Mapping functions missing.');
        return;
    }

    $selected_interests = [];
    foreach ($interests_fields as $field) {
        $v = !empty($_POST[$field]) ? 1 : 0;
        update_user_meta($user_id, $field, $v);
        if ($v) $selected_interests[] = $field;
    }

    // Map TOI to AOI and save AOI fields
    // Map TOI to AOI using centralized mapping
    $normalized_selected = array_map('dtr_normalize_toi_key', $selected_interests);
    $matrix = dtr_get_toi_to_aoi_matrix();
    $aoi_fields = array_keys(dtr_get_aoi_field_names());
    $aoi_mapping = array_fill_keys($aoi_fields, 0);
    foreach ($normalized_selected as $toi_field) {
        if (isset($matrix[$toi_field])) {
            foreach ($matrix[$toi_field] as $aoi_field => $value) {
                $aoi_mapping[$aoi_field] = $value;
            }
        }
    }
    foreach ($aoi_mapping as $k => $v) {
        update_user_meta($user_id, $k, $v);
    }

    // Update Workbooks
    if (function_exists('get_workbooks_instance')) {
        $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
        if ($person_id) {
            try {
                $workbooks = get_workbooks_instance();
                $existing = $workbooks->assertGet('crm/people/' . $person_id . '.api', []);
                if (!isset($existing['data']['id'])) throw new Exception('No person found');
                if ($existing['data']['id'] != $person_id) throw new Exception('Wrong person record');
                
                $lock_version = isset($existing['data']['lock_version']) ? $existing['data']['lock_version'] : null;
                if ($lock_version === null) throw new Exception('No lock version');
                
                $payload = ['id' => $person_id, 'lock_version' => $lock_version];
                foreach ($interests_fields as $field) {
                    $payload[$field] = !empty($_POST[$field]) ? 1 : 0;
                }
                // Add AOI fields to payload using centralized mapping
                $normalized_selected = array_map('dtr_normalize_toi_key', $selected_interests);
                $matrix = dtr_get_toi_to_aoi_matrix();
                $aoi_fields = array_keys(dtr_get_aoi_field_names());
                $aoi_mapping = array_fill_keys($aoi_fields, 0);
                foreach ($normalized_selected as $toi_field) {
                    if (isset($matrix[$toi_field])) {
                        foreach ($matrix[$toi_field] as $aoi_field => $value) {
                            $aoi_mapping[$aoi_field] = $value;
                        }
                    }
                }
                foreach ($aoi_mapping as $k => $v) {
                    $payload[$k] = $v;
                }
                $workbooks->assertUpdate('crm/people.api', $payload);
                wp_send_json_success('Topics of Interest updated!');
            } catch (Exception $e) {
                wp_send_json_error('Workbooks error: ' . $e->getMessage());
            }
        }
    }

    wp_send_json_success('Topics of Interest updated!');
}

function dtr_handle_marketing_update() {
    if (!check_ajax_referer('dtr_marketing_update', 'dtr_marketing_update_nonce', false)) {
        wp_send_json_error('Invalid nonce');
    }

    $user_id = get_current_user_id();
    $dtr_fields = [
        'cf_person_dtr_news',
        'cf_person_dtr_third_party',
        'cf_person_dtr_webinar',
        'cf_person_dtr_events',
    ];

    foreach ($dtr_fields as $field) {
        update_user_meta($user_id, $field, !empty($_POST[$field]) ? 1 : 0);
    }

    // Update Workbooks
    if (function_exists('get_workbooks_instance')) {
        $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
        if ($person_id) {
            try {
                $workbooks = get_workbooks_instance();
                $existing = $workbooks->assertGet('crm/people/' . $person_id . '.api', []);
                if (!isset($existing['data']['id'])) throw new Exception('No person found');
                if ($existing['data']['id'] != $person_id) throw new Exception('Wrong person record');
                
                $lock_version = isset($existing['data']['lock_version']) ? $existing['data']['lock_version'] : null;
                if ($lock_version === null) throw new Exception('No lock version');
                
                $payload = ['id' => $person_id, 'lock_version' => $lock_version];
                foreach ($dtr_fields as $field) {
                    $payload[$field] = !empty($_POST[$field]) ? 1 : 0;
                }
                
                $workbooks->assertUpdate('crm/people.api', $payload);
                wp_send_json_success('Communication Preferences updated!');
            } catch (Exception $e) {
                wp_send_json_error('Workbooks error: ' . $e->getMessage());
            }
        }
    }

    wp_send_json_success('Communication Preferences updated!');
}

// Helper: Log to admin-functions-debug.log
function dtr_admin_debug_log($message, $data = null) {
    $log_file = dirname(__FILE__, 2) . '/logs/admin-functions-debug.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $entry .= ' | ' . (is_string($data) ? $data : json_encode($data));
    }
    file_put_contents($log_file, $entry . PHP_EOL, FILE_APPEND);
}
if (!defined('ABSPATH')) exit;

// --- ENFORCE AT LEAST ONE CHECKED ---
function dtr_at_least_one_checked($fields) {
    foreach ($fields as $field => $label) {
        if (!empty($_POST[$field])) return true;
    }
    return false;
}

// --- Topics of Interest Shortcode ---
add_shortcode('dtr_user_topics_of_interest', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to update your interests.</p>';
    $user_id = get_current_user_id();
    $msg = '';
    // Migration: if old meta key is set and new one not, copy it once.
    if (get_user_meta($user_id, 'cf_person_genomics_3744', true) && !get_user_meta($user_id, 'cf_person_genomics_3774', true)) {
        update_user_meta($user_id, 'cf_person_genomics_3774', 1);
    }
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
        if (!dtr_at_least_one_checked($interests_fields)) {
            $msg = '<div class="error" id="dtr-topics-checkbox-error">Please select at least one topic of interest.</div>';
        } else {
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
                    $values[$k] = $v;
                }
            }
            // Update Workbooks
            if (function_exists('get_workbooks_instance')) {
                $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
                if ($person_id) {
                    $workbooks = get_workbooks_instance();
                    try {
                        $existing = $workbooks->assertGet('crm/people/' . $person_id . '.api', []);
                        if (!isset($existing['data']['id'])) throw new Exception('No person found in Workbooks with ID: ' . $person_id);
                        if ($existing['data']['id'] != $person_id) throw new Exception('Workbooks returned wrong person record. Requested: ' . $person_id . ', Got: ' . $existing['data']['id']);
                        $lock_version = isset($existing['data']['lock_version']) ? $existing['data']['lock_version'] : null;
                        if ($lock_version === null) throw new Exception('Could not retrieve lock_version for Workbooks person ID ' . $person_id);
                        $payload = array_merge(is_array(['id' => $person_id, 'lock_version' => $lock_version]) ? ['id' => $person_id, 'lock_version' => $lock_version] : [], is_array($values) ? $values : []);
                        $workbooks->assertUpdate('crm/people.api', $payload);
                        $msg = '<div class="updated">Topics of Interest updated!</div>';
                    } catch (Exception $e) {
                        $msg = '<div class="error">Workbooks error: ' . esc_html($e->getMessage()) . '</div>';
                    }
                }
            }
            if (!$msg) $msg = '<div class="updated">Topics of Interest updated!</div>';
        }
    }
    wp_enqueue_style('dtr-shortcodes-custom', plugin_dir_url(__FILE__) . '../assets/css/dtr-shortcodes-custom.css', [], null);
    ob_start();
    echo $msg;
    ?>
    <div id="dtr-topics-checkbox-error" class="error" style="display:none;">
      Please select at least one Topic of Interest.
    </div>
    <form method="post" class="dtr-custom-checkbox-form dtr-topics-form" autocomplete="off">
        <fieldset>
            <div class="workbooks-checkboxes dtr subscription list">
            <?php $i = 0; foreach ($interests_fields as $field => $label): $i++; ?>
                <?php 
                // Backward compatibility: treat old genomics meta as selected
                $checked = get_user_meta($user_id, $field, true);
                if ($field === 'cf_person_genomics_3774' && !$checked) {
                    $checked = get_user_meta($user_id, 'cf_person_genomics_3744', true);
                }
                ?>
                <label>
                    <input type="checkbox" class="dtr-topics-checkbox" id="dtr_interest_<?php echo $i; ?>" name="<?php echo esc_attr($field); ?>" value="1" <?php checked($checked); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
            </div>
            <?php wp_nonce_field('dtr_interests_update', 'dtr_interests_update_nonce'); ?>
        </fieldset>
        <input type="submit" name="update_interests" value="Update Interests" class="dtr-topics-submit dtr-input-button custom-btn-decorated">
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.querySelector('.dtr-topics-form');
        if (!form) return;
        var checkboxes = form.querySelectorAll('.dtr-topics-checkbox');
        var btn = form.querySelector('.dtr-topics-submit');
        var errorMsg = document.getElementById('dtr-topics-checkbox-error');
        var originalBtnText = btn.value;
        var dots = 0;
        var submitting = false;
        var submitInterval;
        
        function updateButtonState() {
            var anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            btn.disabled = !anyChecked;
            errorMsg.style.display = anyChecked ? 'none' : 'block';
            // Debug: log counts
            var total = checkboxes.length;
            var selected = Array.from(checkboxes).filter(cb => cb.checked).length;
            console.log('Topics total:', total, '| Selected:', selected);
        }

        function animateSubmitting() {
            dots = (dots + 1) % 4;
            btn.value = 'Submitting' + '.'.repeat(dots);
        }
        
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent form from submitting normally
            
            if (!form.checkValidity()) return;
            
            btn.disabled = true;
            submitting = true;
            submitInterval = setInterval(animateSubmitting, 500);

            // Collect form data
            var formData = new FormData(form);
            formData.append('action', 'update_topics'); // For WordPress AJAX handling

            // Send AJAX request
            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(submitInterval);
                btn.value = 'Settings Saved!';
                
                // Show success message
                var msgDiv = document.createElement('div');
                msgDiv.className = 'updated';
                msgDiv.textContent = 'Topics of Interest updated!';
                form.insertBefore(msgDiv, form.firstChild);
                
                setTimeout(function() {
                    btn.value = originalBtnText;
                    btn.disabled = false;
                    msgDiv.remove();
                }, 2000);
            })
            .catch(error => {
                clearInterval(submitInterval);
                btn.value = originalBtnText;
                btn.disabled = false;
                
                // Show error message
                var msgDiv = document.createElement('div');
                msgDiv.className = 'error';
                msgDiv.textContent = 'Error updating settings. Please try again.';
                form.insertBefore(msgDiv, form.firstChild);
                
                setTimeout(() => msgDiv.remove(), 3000);
            });

            // Fallback timeout
            setTimeout(function() {
                if (submitting) {
                    clearInterval(submitInterval);
                    btn.disabled = false;
                    btn.value = originalBtnText;
                }
            }, 10000);
        });

        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', updateButtonState);
        });
        updateButtonState();
    });
    </script>
    <?php
    return ob_get_clean();
});

// --- Communication Preferences Shortcode ---
add_shortcode('dtr_user_marketing_preferences', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to update your Communication Preferences.</p>';
    $user_id = get_current_user_id();
    $msg = '';
    $dtr_fields = [
        'cf_person_dtr_news'        => '<strong>Newsletter:</strong><br/>News, articles and analysis by email',
        'cf_person_dtr_third_party' => '<strong>Third party:</strong><br/>Application notes, product developments and updates from our trusted partners by email',
        'cf_person_dtr_webinar'     => '<strong>Webinar:</strong><br/>Information about webinars by email',
        'cf_person_dtr_events'      => '<strong>Event:</strong><br/>Information about events by email',
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
                        $existing = $workbooks->assertGet('crm/people/' . $person_id . '.api', []);
                        if (!isset($existing['data']['id'])) throw new Exception('No person found in Workbooks with ID: ' . $person_id);
                        if ($existing['data']['id'] != $person_id) throw new Exception('Workbooks returned wrong person record. Requested: ' . $person_id . ', Got: ' . $existing['data']['id']);
                        $lock_version = isset($existing['data']['lock_version']) ? $existing['data']['lock_version'] : null;
                        if ($lock_version === null) throw new Exception('Could not retrieve lock_version for Workbooks person ID ' . $person_id);
                        $payload = array_merge(is_array(['id' => $person_id, 'lock_version' => $lock_version]) ? ['id' => $person_id, 'lock_version' => $lock_version] : [], is_array($values) ? $values : []);
                        $workbooks->assertUpdate('crm/people.api', $payload);
                        $msg = '<div class="updated">Communication Preferences updated!</div>';
                    } catch (Exception $e) {
                        $msg = '<div class="error">Workbooks error: ' . esc_html($e->getMessage()) . '</div>';
                    }
                }
            }
            if (!$msg) $msg = '<div class="updated">Communication Preferences updated!</div>';
    }
    wp_enqueue_style('dtr-shortcodes-custom', plugin_dir_url(__FILE__) . '../assets/css/dtr-shortcodes-custom.css', [], null);
    
    // Add ajaxurl to page
    wp_add_inline_script('jquery', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
    ob_start();
    echo $msg;
    ?>
    <div id="dtr-marketing-checkbox-error" class="error" style="display:none;">
      Please select at least one communication preference.
    </div>
    <form method="post" class="dtr-custom-checkbox-form dtr-marketing-form" autocomplete="off">
        <fieldset>
            <div class="workbooks-checkboxes dtr subscription list">
            <?php $i = 0; foreach ($dtr_fields as $field => $label): $i++; ?>
                <?php $checked = get_user_meta($user_id, $field, true); ?>
                <label>
                    <input type="checkbox" class="dtr-marketing-checkbox" id="dtr_marketing_<?php echo $i; ?>" name="<?php echo esc_attr($field); ?>" value="1" <?php checked($checked); ?> />
                    <?php echo wp_kses_post($label); ?>
                </label>
            <?php endforeach; ?>
            </div>
            <?php wp_nonce_field('dtr_marketing_update', 'dtr_marketing_update_nonce'); ?>
        </fieldset>
        <input type="submit" name="update_marketing" value="Update Preferences" class="dtr-marketing-submit dtr-input-button custom-btn-decorated">
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var form = document.querySelector('.dtr-marketing-form');
      if (!form) return;
      var checkboxes = form.querySelectorAll('.dtr-marketing-checkbox');
      var btn = form.querySelector('.dtr-marketing-submit');
      var errorMsg = document.getElementById('dtr-marketing-checkbox-error');
      var originalBtnText = btn.value;
      var dots = 0;
      var submitting = false;
      var submitInterval;
      
      function updateButtonState() {
        // Remove validation - button is always enabled
        btn.disabled = false;
        errorMsg.style.display = 'none';
      }

      function animateSubmitting() {
        dots = (dots + 1) % 4;
        btn.value = 'Submitting' + '.'.repeat(dots);
      }

      form.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent form from submitting normally
        
        if (!form.checkValidity()) return;
        
        btn.disabled = true;
        submitting = true;
        submitInterval = setInterval(animateSubmitting, 500);

        // Collect form data
        var formData = new FormData(form);
        formData.append('action', 'update_marketing'); // For WordPress AJAX handling

        // Send AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(submitInterval);
            btn.value = 'Settings Saved!';
            
            // Show success message
            var msgDiv = document.createElement('div');
            msgDiv.className = 'updated';
            msgDiv.textContent = 'Communication Preferences updated!';
            form.insertBefore(msgDiv, form.firstChild);
            
            setTimeout(function() {
                btn.value = originalBtnText;
                btn.disabled = false;
                msgDiv.remove();
            }, 2000);
        })
        .catch(error => {
            clearInterval(submitInterval);
            btn.value = originalBtnText;
            btn.disabled = false;
            
            // Show error message
            var msgDiv = document.createElement('div');
            msgDiv.className = 'error';
            msgDiv.textContent = 'Error updating settings. Please try again.';
            form.insertBefore(msgDiv, form.firstChild);
            
            setTimeout(() => msgDiv.remove(), 3000);
        });

        // Fallback timeout
        setTimeout(function() {
            if (submitting) {
                clearInterval(submitInterval);
                btn.disabled = false;
                btn.value = originalBtnText;
            }
        }, 10000);
      });
      
      checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateButtonState);
      });
      updateButtonState();
    });
    </script>
    <?php
    return ob_get_clean();
});