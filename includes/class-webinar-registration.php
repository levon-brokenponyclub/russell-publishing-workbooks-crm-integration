<?php
/* --------------------------------------------------------------------------
 * Workbooks Webinar Registration Shortcode Logic
 * -------------------------------------------------------------------------- */

/**
 * Renders the webinar registration UI with all logic for user state, event type, and form display.
 *
 * Usage: [workbooks-webinar-registration]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output for the registration UI.
 */
function workbooks_webinar_registration_shortcode($atts) {
    // --- BEGIN: Inject User & Event Data for JS (Always, if logged in) ---
    $post_id = get_the_ID();
    $user_is_logged_in = is_user_logged_in();
    // Always load webinar_fields for event ID!
    $webinar_fields = get_field('webinar_fields', $post_id);
    $event_id = '';
    if (!empty($webinar_fields['workbooks_reference'])) {
        $event_id = preg_replace('/\D+/', '', $webinar_fields['workbooks_reference']);
    }

    if ($user_is_logged_in) {
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        $person_email = $current_user->user_email;

        echo '<script>';
        echo 'window.dtr_workbooks_ajax = window.dtr_workbooks_ajax || {};';
        echo 'window.dtr_workbooks_ajax.current_user_id = ' . json_encode($person_id) . ';';
        echo 'window.dtr_workbooks_ajax.current_user_email = ' . json_encode($person_email) . ';';
        echo 'window.dtr_workbooks_ajax.current_event_id = ' . json_encode($event_id) . ';';
        // --- JS console logs as requested ---
        echo 'var personId = window.dtr_workbooks_ajax.current_user_id;';
        echo 'var personEmail = window.dtr_workbooks_ajax.current_user_email;';
        echo 'var eventId = window.dtr_workbooks_ajax.current_event_id;';
        echo 'console.log("Person ID:", personId);';
        echo 'console.log("Person Email Address:", personEmail);';
        echo 'console.log("Event ID:", eventId);';
        // Match lead generation template: Always log user login state
        echo 'console.log("User: Logged In");';
        echo '</script>';
    }
    // --- END: Inject User & Event Data for JS ---

    ob_start();
    
    // Enqueue CSS and JavaScript files for webinar registration
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    $css_version = filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/webinar-registration.css');
    $js_version = filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/webinar-registration.js');
    
    // Only enqueue once per page load
    static $assets_enqueued = false;
    if (!$assets_enqueued) {
        wp_enqueue_style(
            'dtr-webinar-registration-css',
            $plugin_url . 'assets/css/webinar-registration.css',
            array(),
            $css_version
        );
        
        wp_enqueue_script(
            'dtr-webinar-registration.js',
            $plugin_url . 'assets/js/webinar-registration.js',
            array('jquery'),
            $js_version,
            true
        );
        
        $assets_enqueued = true;
    }

    
    // Hidden login form for modal use
    echo '<div id="nf-login-modal-form" style="display:none;">';
    echo do_shortcode('[ninja_form id=3]');
    echo '</div>';

    // ----------------------------------------------------------------------
    // Step 1: Gather Event & User Data
    // ----------------------------------------------------------------------
    $registration_link = $webinar_fields['webinar_link'] ?? '';
    $start_date = $webinar_fields['webinar_date_and_time'] ?? '';
    $webinar_form = $webinar_fields['webinar_registration_form'] ?? '';
    $acf_questions = !empty($webinar_fields['add_questions']) ? $webinar_fields['add_questions'] : [];
    $add_additional_questions = !empty($webinar_fields['add_additional_questions']) ? $webinar_fields['add_additional_questions'] : false;
    $add_speaker_question = !empty($webinar_fields['add_speaker_question']) ? $webinar_fields['add_speaker_question'] : false;
    $is_on_demand = !empty($registration_link);
    $user_id = get_current_user_id();
    $user_is_registered = $user_is_logged_in ? is_user_registered_for_event($user_id, $post_id) : false;
    $ics_url = add_query_arg('ics', '1', get_permalink($post_id));

    
    // ----------------------------------------------------------------------
    // Section: Not Logged In (Split Button with 50% inline dropdown + Slide Up/Down)
    // ----------------------------------------------------------------------
    if ( ! $user_is_logged_in ) {
        $uid = 'ks' . uniqid(); // unique id for this instance
        echo <<<HTML
        <!-- <div style="font-size:2rem;font-weight:bold;color:#b00;text-align:center;margin:2em 0;">STEP: Not Logged In</div> -->
            <div class="full-page vertical-half-margin event-registration">

            <!-- split button -->
            <div class="ks-split-btn" style="position: relative;">
                <button type="button" class="ks-main-btn ks-main-btn-global btn-blue shimmer-effect shimmer-slow is-toggle text-left" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="{$uid}-menu">Login or Register Now</button>

                <ul id="{$uid}-menu" class="ks-menu" role="menu" style="z-index: 1002;">
                    <li role="none"><a role="menuitem" href="#" class="login-button" onclick="event.preventDefault(); openLoginModal();">Login</a></li>
                    <li role="none"><a role="menuitem" href="/free-membership">Become a Member</a></li>
                </ul>
            </div>

            <div class="reveal-text">Login or Register for this event</div>
        </div>
        HTML;

        return ob_get_clean();
    }

    /* ----------------------------------------------------------------------
     * Section: On Demand Webinar (Video Link Present)
     * ---------------------------------------------------------------------- */
    if ($is_on_demand) {
        echo '<div style="font-size:2rem;font-weight:bold;color:#b00;text-align:center;margin:2em 0;">STEP: On Demand Webinar (Video Link Present)</div>';
        echo "<!-- LOG: Webinar Type: On Demand Webinar -->\n";
        echo "<script>console.log('[LOG] Webinar Type: On Demand Webinar');</script>\n";
        echo "<!-- LOG: User: Logged In - Additional Questions - Post Not Saved (On Demand Logic) -->\n";
        echo "<script>console.log('[LOG] User: Logged In - Additional Questions - Post Not Saved (On Demand Logic)');</script>\n";
        echo '<div class="full-page vertical-half-margin event-registration">';
        echo '<button class="ks-main-btn-global btn-purple shimmer-effect shimmer-slow btn-loading not-registered text-left" disabled>On Demand - Register Now</button>';
        echo '</div>';
        if (!empty($webinar_form['id'])) {
            $extra_fields_markup = '';
            // Note: Speaker question is handled by Ninja Form field 387 (question_for_speaker)
            // We don't need to add it here as it would create duplicates
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
            echo '<div class="gated-lead-form-content webinars">';
            if (!empty($extra_fields_markup)) {
                echo $extra_fields_markup;
            }
            echo do_shortcode("[ninja_form id='" . esc_attr($webinar_form['id']) . "']");
            echo '</div>';
        }
        return ob_get_clean();
    }

    /* ----------------------------------------------------------------------
     * Section: Upcoming Event (Not On Demand)
     * ---------------------------------------------------------------------- */
    if (strtotime($start_date) > time()) {
        // Track user return with a cookie
        $step_status = $user_is_registered ? 'Registered' : 'Not Registered';
        $user_return = false;
        if ($user_is_registered && isset($_COOKIE['dtr_webinar_registered_' . $post_id])) {
            $user_return = true;
        }
        if ($user_is_registered && !$user_return) {
            setcookie('dtr_webinar_registered_' . $post_id, '1', time() + 60*60*24*30, "/");
        }
        $return_text = $user_return ? ' - User Return' : '';
        $form_ready = !$user_is_registered ? ' - Form Ready for Registration' : '';
        $step_message = 'STEP: Upcoming Event (Not On Demand) - ' . $step_status . $return_text . $form_ready;
        echo "<script>console.log('" . addslashes($step_message) . "');</script>\n";
        echo "<!-- LOG: Webinar Type: Live Webinar -->\n";
        echo "<script>console.log('[LOG] Webinar Type: Live Webinar');</script>\n";
        echo '<div class="full-page vertical-half-margin event-registration">';
        if ($user_is_registered) {
            $uid = 'ks' . uniqid(); // unique id for this instance
            echo <<<HTML
            <!-- split button for registered users -->
            <div class="ks-split-btn btn-green" style="position: relative;">
                <button type="button" class="ks-main-btn ks-main-btn-global btn-green shimmer-effect shimmer-slow is-toggle text-left" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="{$uid}-menu">You are registered for this Webinar</button>

                <ul id="{$uid}-menu" class="ks-menu" role="menu" style="z-index: 1002;">
                    <li role="none"><a role="menuitem" href="/my-account/?page-view=overview&ics=1&calendar-post-id={$post_id}" class="no-decoration calendar-btn">Add to Calendar</a></li>
                    <li role="none"><a role="menuitem" href="/my-account/?page-view=events-and-webinars">Events & Webinars</a></li>
                </ul>
            </div>

            <div class="reveal-text">Webinar has been added to Events & Webinars</div>
            HTML;
        } else {
            echo '<button class="ks-main-btn-global btn-blue shimmer-effect shimmer-slow not-registered webinar-registration text-left" data-event-id="' . esc_attr($post_id) . '" disabled>Register Now</button>';
        }
        echo '</div>';
        
        // Only show form if user is NOT registered
        if (!$user_is_registered && !empty($webinar_form['id'])) {
            $extra_fields_markup = '';
            // Note: Speaker question is handled by Ninja Form field 387 (question_for_speaker)
            // We don't need to add it here as it would create duplicates
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
            echo '<div class="gated-lead-form-content webinars">';
            if (!empty($extra_fields_markup)) {
                echo $extra_fields_markup;
            }
            echo do_shortcode("[ninja_form id='" . esc_attr($webinar_form['id']) . "']");
            echo '</div>';
        }
        return ob_get_clean();
    }

    /* ----------------------------------------------------------------------
     * Section: Event Has Passed (Not On Demand)
     * ---------------------------------------------------------------------- */
    echo '<div style="font-size:2rem;font-weight:bold;color:#b00;text-align:center;margin:2em 0;">STEP: Event Has Passed (Not On Demand)</div>';
    echo "<!-- LOG: Webinar Type: Live Webinar (Event Passed, Not On Demand) -->\n";
    echo "<script>console.log('[LOG] Webinar Type: Live Webinar (Event Passed, Not On Demand)');</script>\n";
    echo "<!-- LOG: User: Logged In - Registered - Post Saved To Collection (Live & On Demand Logic) -->\n";
    echo "<script>console.log('[LOG] User: Logged In - Registered - Post Saved To Collection (Live & On Demand Logic)');</script>\n";
    echo '<div class="full-page vertical-half-margin event-registration">';
    echo '<button class="ks-main-btn-global btn-purple shimmer-effect shimmer-slow btn-loading event-passed text-left" disabled>On Demand - Register Now</button>';
    echo '</div>';
    if (!empty($webinar_form['id'])) {
        $extra_fields_markup = '';
        if ($add_speaker_question) {
            $extra_fields_markup .= '<div class="webinar-speaker-question" style="margin-bottom:15px;">';
            $extra_fields_markup .= '<label for="webinar_speaker_question">Do you have a question for our speakers?</label><br />';
            $extra_fields_markup .= '<textarea name="webinar_speaker_question" id="webinar_speaker_question" rows="3" style="width:100%"></textarea>';
            $extra_fields_markup .= '</div>';
        }
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
        echo '<div class="gated-lead-form-content webinars">';
        if (!empty($extra_fields_markup)) {
            echo $extra_fields_markup;
        }
        echo do_shortcode("[ninja_form id='" . esc_attr($webinar_form['id']) . "']");
        echo '</div>';
    }
    return ob_get_clean();
}

// Add modal functionality for login button
?>