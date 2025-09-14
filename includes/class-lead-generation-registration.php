<?php
/**
 * Workbooks Lead Generation Registration Shortcode Logic
 * Usage: [workbooks-lead-generation-registration control_content="true"]
 * Renders the lead generation registration UI with all logic for user state, ACF-powered questions, and form display.
 * When control_content="true", it controls the entire page content display logic.
 */
function workbooks_lead_generation_registration_shortcode($atts = []) {
    // Parse shortcode attributes first
    $atts = shortcode_atts([
        'control_content' => 'false',
        'lead_generation_id' => '31'
    ], $atts);

    $form_id = intval($atts['lead_generation_id']);
    $post_id = get_the_ID();
    $user_id = get_current_user_id();
    
    // Enqueue CSS and JavaScript files for lead generation registration
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    $plugin_path = plugin_dir_path(dirname(__FILE__));
    
    // Get file modification times for cache-busting
    $css_file = $plugin_path . 'assets/css/lead-generation-registration.css';
    $js_file = $plugin_path . 'assets/js/lead-generation-registration.js';
    $css_version = file_exists($css_file) ? filemtime($css_file) : '1.0.0';
    $js_version = file_exists($js_file) ? filemtime($js_file) : '1.0.0';
    
    // Only enqueue once per page load
    static $assets_enqueued = false;
    if (!$assets_enqueued) {
        // Enqueue CSS
        wp_enqueue_style(
            'dtr-lead-generation-registration-css',
            $plugin_url . 'assets/css/lead-generation-registration.css',
            array(),
            $css_version
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'dtr-lead-generation-registration-js',
            $plugin_url . 'assets/js/lead-generation-registration.js',
            array('jquery'),
            $js_version,
            true
        );
        
        // Get ACF questions to determine if we need ACF integration
        $restricted = function_exists('get_field') ? get_field('restricted_content_fields', $post_id) : [];
        $acf_questions = !empty($restricted['add_questions']) ? $restricted['add_questions'] : [];
        
        // Localize script with PHP data
        wp_localize_script(
            'dtr-lead-generation-registration-js',
            'ajax_object',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'user_id' => $user_id,
                'form_id' => $form_id,
                'post_id' => $post_id,
                'nonce' => wp_create_nonce('mark_ninja_form_completed'),
                'has_acf_questions' => !empty($acf_questions) ? '1' : '0'
            )
        );
        
        $assets_enqueued = true;
    }
    
    // Add hidden login form for modal use (only for non-logged-in users)
    if (!is_user_logged_in()) {
        echo '<div id="nf-login-modal-form" style="display:none;">';
        echo do_shortcode('[ninja_form id=3]');
        echo '</div>';
    }
    
    $control_content = filter_var($atts['control_content'], FILTER_VALIDATE_BOOLEAN);

    ob_start();
    // Re-declare variables since we moved the parsing above
    $post_id = get_the_ID();
    $lead_fields = function_exists('get_field') ? get_field('lead_fields', $post_id) : [];
    $lead_form = $lead_fields['lead_generation_form'] ?? ['id' => $form_id];

    // Get ACF questions from restricted_content_fields (not lead_fields)
    $restricted = function_exists('get_field') ? get_field('restricted_content_fields', $post_id) : [];
    $acf_questions = !empty($restricted['add_questions']) ? $restricted['add_questions'] : [];
    $add_additional_questions = !empty($restricted['add_additional_questions']) ? $restricted['add_additional_questions'] : false;

    // --- BEGIN: Only essential logging for person/event/acf ---
    // Use the new field: restricted['workbooks_reference'] for event ID (from Gated Content ACF group)
    $event_id = '';
    if (!empty($restricted['workbooks_reference'])) {
        $event_id = preg_replace('/\D+/', '', $restricted['workbooks_reference']);
    }
    $user_is_logged_in = is_user_logged_in();
    if ($user_is_logged_in) {
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        $person_email = $current_user->user_email;

        echo '<script>';
        echo 'console.log("Person ID:", ' . json_encode($person_id) . ');';
        echo 'console.log("Person Email Address:", ' . json_encode($person_email) . ');';
        echo 'console.log("Event ID:", ' . json_encode($event_id) . ');';
        if (!empty($acf_questions)) {
            echo 'console.log("ACF Questions Debug:", ' . json_encode($acf_questions) . ');';
        }
        echo '</script>';
    }
    // --- END essential logging only ---

    $user_id = get_current_user_id();
    
    // Check if form 31 has been completed - multiple methods for reliability
    $has_completed_form = false;
    
    if ($user_is_logged_in) {
        // Method 1: Check for form completion in user meta
        $completed_forms = get_user_meta($user_id, 'completed_ninja_forms', true);
        if (is_array($completed_forms)) {
            $has_completed_form = in_array($form_id, $completed_forms) || in_array("{$form_id}_{$post_id}", $completed_forms);
        }
        
        // Method 2: Check for specific form completion meta
        if (!$has_completed_form) {
            $form_completed_meta = get_user_meta($user_id, "completed_form_{$form_id}_{$post_id}", true);
            $has_completed_form = !empty($form_completed_meta);
        }
        
        // Method 3: Check if success message exists on page (JavaScript detection handled in external JS file)
    }

    // Check if user has saved to collection
    $saved_to_collection = false;
    if ($user_is_logged_in) {
        $saved = get_user_meta($user_id, 'saved_collection', true);
        if (is_array($saved)) {
            $saved_to_collection = in_array($post_id, $saved);
        }
    }

    // If control_content is true, handle the main content logic
    if ($control_content) {
        global $post;
        $restrict_post = get_field('restrict_post', $post->ID);

        if (!$restrict_post) {
            // Not gated, show full content
            get_template_part('components/global/main-content');
            return ob_get_clean();
        }

        if (!$user_is_logged_in) {
            // Show preview content for guests
            get_template_part('components/single-content/gated-content');
            return ob_get_clean();
        } else {
            if ($has_completed_form) {
                // Show full content for users who completed form
                // Static notice is hidden by default, toast notification will be shown via JavaScript
                echo '<div class="form-completion-notice" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; display: none;">
                    <strong>&#10003; Content Unlocked:</strong> You have completed the required form and can now access this content.
                </div>';
                get_template_part('components/global/main-content');
                return ob_get_clean();
            } else {
                // Show logged-in user content before form submission (WITHOUT the form - form should only be in sidebar)
                $logged_in_content = !empty($restricted['logged_in_user_-_before_form_submission']) 
                    ? $restricted['logged_in_user_-_before_form_submission'] 
                    : '';
                
                if (!empty($logged_in_content)) {
                    // Display the custom logged-in user content before form submission
                    echo '<div class="gated-logged-in-content full-page main-body-content" style="margin-bottom:65px;">';
                    echo wpautop($logged_in_content);
                    echo '</div>';
                } else {
                    // Fallback to existing gated content template if no custom content
                    get_template_part('components/single-content/gated-content-logged-in');
                }
                
                return ob_get_clean();
            }
        }
        
        return ob_get_clean();
    }

    // Original sidebar logic (when control_content is false or not set)
    // Generate dynamic button based on user state
    $button_html = '';
    $reveal_text = '';

    if (!$user_is_logged_in) {
        // Not logged in: "Login or Register Now" (with link) + "Login or Register for this event"
        $button_html = '<a href="/free-membership" class="event-register-button">Login or Register Now</a>';
        $reveal_text = '<div class="reveal-text">Login or Register for this event</div>';
    } elseif (!$has_completed_form) {
        // Logged in, no form submission: "Register Now" (no link, triggers form)
        $button_html = '<button class="event-register-button not-registered" onclick="document.querySelector(\'.gated-lead-form-content\').scrollIntoView({behavior: \'smooth\'});">Register Now</button>';
        $reveal_text = '';
    } elseif (!$saved_to_collection) {
        // Logged in, form submitted: "Save to Collection"
        $button_html = '<button class="event-register-button save-to-collection" data-post-id="' . esc_attr($post_id) . '">Save to Collection</button>';
        $reveal_text = '<div class="reveal-text">You have registered for this event</div>';
    } else {
        // Logged in, saved to collection: Split button with options
        $uid = 'ks' . uniqid(); // unique id for this instance
        $button_html = '<div class="ks-split-btn">
                <a href="/my-account/?page-view=my-collection" class="ks-main-btn" role="button">Saved to Collection</a>
                <button type="button" class="ks-toggle-btn" aria-haspopup="true" aria-expanded="false" aria-controls="' . $uid . '-menu" title="Open menu">
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <ul id="' . $uid . '-menu" class="ks-menu" role="menu">
                    <li role="none"><a role="menuitem" href="#" class="no-decoration remove-from-collection-btn">Remove</a></li>
                    <li role="none"><a role="menuitem" href="/my-account/?page-view=my-collection">View My Collection</a></li>
                </ul>
            </div>';
        $reveal_text = '<div class="reveal-text">Event has been saved to collection</div>';
    }

    // Save to Collection functionality is handled in external JavaScript file

    // Not logged in: show login/register CTA with split button
    if (!$user_is_logged_in) {
        $uid = 'ks' . uniqid(); // unique id for this instance
        echo <<<HTML
        <div class="full-page vertical-half-margin event-registration lead-generation">
            <!-- split button -->
            <div class="ks-split-btn">
                <a href="/free-membership" class="ks-main-btn" role="button">Login or Register Now</a>
                <button type="button" class="ks-toggle-btn" aria-haspopup="true" aria-expanded="false" aria-controls="{$uid}-menu" title="Open menu">
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <ul id="{$uid}-menu" class="ks-menu" role="menu">
                    <li role="none"><a role="menuitem" href="#" class="login-button" onclick="event.preventDefault(); openLoginModal();">Login</a></li>
                    <li role="none"><a role="menuitem" href="/free-membership">Become a Member</a></li>
                </ul>
            </div>

            <div class="reveal-text">Login or Register for this event</div>
        </div>

        
HTML;
        return ob_get_clean();
    }

    // Show form and button for logged-in users
    echo '<div class="full-page vertical-half-margin event-registration">';
    echo $button_html;
    echo $reveal_text;
    
    // Show form for logged-in users who haven't completed it
    if ($user_is_logged_in && !$has_completed_form) {
        echo '<div class="gated-lead-form-content">';
        
        $extra_fields_markup = '';
        if ($add_additional_questions && !empty($acf_questions)) {
            $extra_fields_markup .= '<form id="acf-questions-form" style="margin-bottom:15px;" onsubmit="return false;">';
            foreach ($acf_questions as $i => $question) {
                $type = isset($question['type_of_question']) ? $question['type_of_question'] : 'text';
                $title = isset($question['question_title']) ? $question['question_title'] : '';
                $extra_fields_markup .= '<div style="margin-bottom:10px;">';
                $extra_fields_markup .= '<label class="question-label" for="acf_question_' . $i . '">' . esc_html($title) . '</label><br />';
                if ($type === 'dropdown' && !empty($question['dropdown_options'])) {
                    $extra_fields_markup .= '<select name="acf_question_' . $i . '" id="acf_question_' . $i . '">';
                    foreach ($question['dropdown_options'] as $opt) {
                        $extra_fields_markup .= '<option value="' . esc_attr($opt['option']) . '">' . esc_html($opt['option']) . '</option>';
                    }
                    $extra_fields_markup .= '</select>';
                } elseif ($type === 'checkbox' && !empty($question['checkbox_options'])) {
                    foreach ($question['checkbox_options'] as $j => $opt) {
                        $extra_fields_markup .= '<label class="answer-label"><input type="checkbox" name="acf_question_' . $i . '[]" value="' . esc_attr($opt['checkbox']) . '"> ' . esc_html($opt['checkbox']) . '</label> ';
                    }
                } elseif ($type === 'radio' && !empty($question['radio_options'])) {
                    foreach ($question['radio_options'] as $j => $opt) {
                        $extra_fields_markup .= '<label class="answer-label"><input type="radio" name="acf_question_' . $i . '" value="' . esc_attr($opt['radio']) . '"> ' . esc_html($opt['radio']) . '</label> ';
                    }
                } elseif ($type === 'textarea') {
                    $extra_fields_markup .= '<textarea name="acf_question_' . $i . '" id="acf_question_' . $i . '" rows="4" style="width:100%"></textarea>';
                } else {
                    $extra_fields_markup .= '<input type="text" name="acf_question_' . $i . '" id="acf_question_' . $i . '" value="" />';
                }
                $extra_fields_markup .= '</div>';
            }
            $extra_fields_markup .= '</form>';
        }
        if (!empty($extra_fields_markup)) {
            echo $extra_fields_markup;
        }
        echo do_shortcode('[ninja_form id="' . esc_attr($lead_form['id']) . '"]');
        echo '</div>'; // close .gated-lead-form-content
        
        // ACF integration and form success detection is handled in external JavaScript file
    }
    
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('workbooks-lead-generation-registration', 'workbooks_lead_generation_registration_shortcode');

/**
 * AJAX handler to mark a Ninja Form as completed for a user
 */
function mark_ninja_form_completed_ajax_handler() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'mark_ninja_form_completed')) {
        wp_die('Security check failed');
    }
    
    $user_id = intval($_POST['user_id']);
    $form_id = intval($_POST['form_id']);
    $post_id = intval($_POST['post_id']);
    
    // Verify user
    if ($user_id !== get_current_user_id()) {
        wp_die('Invalid user');
    }
    
    // Get existing completed forms
    $completed_forms = get_user_meta($user_id, 'completed_ninja_forms', true);
    if (!is_array($completed_forms)) {
        $completed_forms = [];
    }
    
    // Add this form to completed list (both with and without post ID)
    $form_key = $form_id;
    $form_post_key = "{$form_id}_{$post_id}";
    
    if (!in_array($form_key, $completed_forms)) {
        $completed_forms[] = $form_key;
    }
    if (!in_array($form_post_key, $completed_forms)) {
        $completed_forms[] = $form_post_key;
    }
    
    // Update user meta
    update_user_meta($user_id, 'completed_ninja_forms', $completed_forms);
    update_user_meta($user_id, "completed_form_{$form_id}_{$post_id}", current_time('mysql'));
    
    wp_send_json_success([
        'message' => 'Form marked as completed',
        'form_id' => $form_id,
        'post_id' => $post_id,
        'completed_forms' => $completed_forms
    ]);
}
add_action('wp_ajax_mark_ninja_form_completed', 'mark_ninja_form_completed_ajax_handler');
add_action('wp_ajax_nopriv_mark_ninja_form_completed', 'mark_ninja_form_completed_ajax_handler');

/**
 * AJAX handler to remove a post from user's collection
 */
function remove_from_collection_ajax_handler() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'mark_ninja_form_completed')) {
        wp_die('Security check failed');
    }
    
    $post_id = intval($_POST['post_id']);
    $user_id = get_current_user_id();
    
    // Verify user is logged in
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    // Get current saved collection
    $saved_collection = get_user_meta($user_id, 'saved_collection', true);
    if (!is_array($saved_collection)) {
        $saved_collection = [];
    }
    
    // Remove post from collection
    $key = array_search($post_id, $saved_collection);
    if ($key !== false) {
        unset($saved_collection[$key]);
        $saved_collection = array_values($saved_collection); // Re-index array
        
        // Update user meta
        update_user_meta($user_id, 'saved_collection', $saved_collection);
        
        wp_send_json_success([
            'message' => 'Post removed from collection successfully',
            'post_id' => $post_id,
            'collection' => $saved_collection
        ]);
    } else {
        wp_send_json_error('Post not found in collection');
    }
}
add_action('wp_ajax_remove_from_collection', 'remove_from_collection_ajax_handler');
add_action('wp_ajax_nopriv_remove_from_collection', 'remove_from_collection_ajax_handler');

/**
 * Helper function to check if a user has completed a specific form
 * @param int $user_id User ID
 * @param int $form_id Form ID
 * @param int $post_id Optional post ID for form-post combination
 * @return bool
 */
function user_has_completed_form($user_id, $form_id, $post_id = null) {
    if (!$user_id || !$form_id) {
        return false;
    }
    
    // Check user meta for completed forms
    $completed_forms = get_user_meta($user_id, 'completed_ninja_forms', true);
    if (is_array($completed_forms)) {
        // Check for form ID alone
        if (in_array($form_id, $completed_forms)) {
            return true;
        }
        
        // Check for form ID + post ID combination
        if ($post_id && in_array("{$form_id}_{$post_id}", $completed_forms)) {
            return true;
        }
    }
    
    // Check specific meta key if post ID provided
    if ($post_id) {
        $form_completed = get_user_meta($user_id, "completed_form_{$form_id}_{$post_id}", true);
        if (!empty($form_completed)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Clear form completion status for a user (for testing purposes)
 * @param int $user_id User ID
 * @param int $form_id Form ID to clear (optional - if not provided, clears all)
 * @param int $post_id Post ID (optional)
 * @return bool Success status
 */
function clear_user_form_completion($user_id, $form_id = null, $post_id = null) {
    if (!$user_id) {
        return false;
    }
    
    if ($form_id && $post_id) {
        // Clear specific form-post combination
        delete_user_meta($user_id, "completed_form_{$form_id}_{$post_id}");
        
        // Remove from completed forms array
        $completed_forms = get_user_meta($user_id, 'completed_ninja_forms', true);
        if (is_array($completed_forms)) {
            $completed_forms = array_diff($completed_forms, [$form_id, "{$form_id}_{$post_id}"]);
            update_user_meta($user_id, 'completed_ninja_forms', $completed_forms);
        }
        
        return true;
    } elseif ($form_id) {
        // Clear specific form ID from all posts
        $completed_forms = get_user_meta($user_id, 'completed_ninja_forms', true);
        if (is_array($completed_forms)) {
            $completed_forms = array_filter($completed_forms, function($item) use ($form_id) {
                return $item != $form_id && !preg_match("/^{$form_id}_\d+$/", $item);
            });
            update_user_meta($user_id, 'completed_ninja_forms', $completed_forms);
        }
        
        // Clear all form-specific meta keys
        global $wpdb;
        $wpdb->delete(
            $wpdb->usermeta,
            [
                'user_id' => $user_id,
                'meta_key' => $wpdb->prepare('completed_form_%d_%%', $form_id)
            ],
            ['%d', '%s']
        );
        
        return true;
    } else {
        // Clear all form completions
        delete_user_meta($user_id, 'completed_ninja_forms');
        
        // Clear all completed_form_* meta keys
        global $wpdb;
        $wpdb->delete(
            $wpdb->usermeta,
            [
                'user_id' => $user_id,
                'meta_key' => 'completed_form_%'
            ],
            ['%d', '%s']
        );
        
        return true;
    }
}

/**
 * Admin function to clear form completions via URL parameter
 * Usage: add ?clear_form_completion=1 to any page URL (admin only)
 */
function handle_clear_form_completion_request() {
    // Only allow for administrators
    if (!current_user_can('administrator')) {
        return;
    }
    
    if (isset($_GET['clear_form_completion'])) {
        $user_id = get_current_user_id();
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 31; // Default to form 31
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : get_the_ID();
        
        $success = clear_user_form_completion($user_id, $form_id, $post_id);
        
        if ($success) {
            // Add success message
            add_action('wp_footer', function() use ($form_id, $post_id) {
                echo '<div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); 
                           background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; 
                           z-index: 9999; font-weight: bold;">
                    Form completion cleared (Form ID: ' . $form_id . ', Post ID: ' . $post_id . ')
                </div>';
                echo '<script>setTimeout(function() {
                    var msg = document.querySelector("div[style*=\'position: fixed\']");
                    if (msg) msg.remove();
                }, 3000);</script>';
            });
        }
        
        // Redirect to clean URL (remove query parameters)
        $clean_url = remove_query_arg(['clear_form_completion', 'form_id', 'post_id']);
        if ($clean_url !== $_SERVER['REQUEST_URI']) {
            wp_redirect($clean_url);
            exit;
        }
    }
}
add_action('template_redirect', 'handle_clear_form_completion_request');

/**
 * AJAX handler to clear form completion (admin only)
 */
function clear_form_completion_ajax_handler() {
    // Only allow for administrators
    if (!current_user_can('administrator')) {
        wp_die('Access denied');
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'clear_form_completion')) {
        wp_die('Security check failed');
    }
    
    $user_id = intval($_POST['user_id']) ?: get_current_user_id();
    $form_id = intval($_POST['form_id']) ?: 31;
    $post_id = intval($_POST['post_id']) ?: null;
    
    $success = clear_user_form_completion($user_id, $form_id, $post_id);
    
    if ($success) {
        wp_send_json_success([
            'message' => 'Form completion cleared successfully',
            'form_id' => $form_id,
            'post_id' => $post_id,
            'user_id' => $user_id
        ]);
    } else {
        wp_send_json_error('Failed to clear form completion');
    }
    
}

add_action('wp_ajax_clear_form_completion', 'clear_form_completion_ajax_handler');
