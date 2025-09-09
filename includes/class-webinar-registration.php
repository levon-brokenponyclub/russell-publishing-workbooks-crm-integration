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
    ob_start();
    // ----------------------------------------------------------------------
    // Step 1: Gather Event & User Data
    // ----------------------------------------------------------------------
    $post_id = get_the_ID();
    $webinar_fields = get_field('webinar_fields', $post_id);
    $registration_link = $webinar_fields['webinar_link'] ?? '';
    $start_date = $webinar_fields['webinar_date_and_time'] ?? '';
    $webinar_form = $webinar_fields['webinar_registration_form'] ?? '';
    $acf_questions = !empty($webinar_fields['add_questions']) ? $webinar_fields['add_questions'] : [];
    $add_additional_questions = !empty($webinar_fields['add_additional_questions']) ? $webinar_fields['add_additional_questions'] : false;
    $add_speaker_question = !empty($webinar_fields['add_speaker_question']) ? $webinar_fields['add_speaker_question'] : false;
    $is_on_demand = !empty($registration_link);
    $user_id = get_current_user_id();
    $user_is_logged_in = is_user_logged_in();
    $user_is_registered = $user_is_logged_in ? is_user_registered_for_event($user_id, $post_id) : false;
    $ics_url = add_query_arg('ics', '1', get_permalink($post_id));

    // ----------------------------------------------------------------------
    // Section: Not Logged In
    // ----------------------------------------------------------------------
    if (!$user_is_logged_in) {
        echo "<!-- LOG: User: Not Logged In -->\n";
        echo "<script>console.log('[LOG] User: Not Logged In');</script>\n";
        echo '<div class="full-page vertical-half-margin event-registration">';
        echo '<button class="event-register-button reveal-login-modal">Register Now</button>';
        echo '<div class="reveal-text">Login to register for this event</div>';
        echo '</div>';
        return ob_get_clean();
    }

    /* ----------------------------------------------------------------------
     * Section: On Demand Webinar (Video Link Present)
     * ---------------------------------------------------------------------- */
    if ($is_on_demand) {
        echo "<!-- LOG: Webinar Type: On Demand Webinar -->\n";
        echo "<script>console.log('[LOG] Webinar Type: On Demand Webinar');</script>\n";
        echo "<!-- LOG: User: Logged In - Additional Questions - Post Not Saved (On Demand Logic) -->\n";
        echo "<script>console.log('[LOG] User: Logged In - Additional Questions - Post Not Saved (On Demand Logic)');</script>\n";
        echo '<div class="full-page vertical-half-margin event-registration">';
        echo '<button class="event-register-button event-passed">On Demand - Register Now</button>';
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

    /* ----------------------------------------------------------------------
     * Section: Upcoming Event (Not On Demand)
     * ---------------------------------------------------------------------- */
    if (strtotime($start_date) > time()) {
        echo "<!-- LOG: Webinar Type: Live Webinar -->\n";
        echo "<script>console.log('[LOG] Webinar Type: Live Webinar');</script>\n";
        if ($user_is_registered) {
            echo "<!-- LOG: User: Logged In - Registered - Post Not Saved (Live Webinar Logic) -->\n";
            echo "<script>console.log('[LOG] User: Logged In - Registered - Post Not Saved (Live Webinar Logic)');</script>\n";
            echo '<div class="full-page vertical-half-margin event-registration">';
            echo '<button class="event-register-button event-deregistration" data-event-id="' . esc_attr($post_id) . '">Registered</button>';
            echo '<a href="' . esc_url($ics_url) . '" class="add-to-cal-btn">Add to my personal calendar</a>';
            echo '</div>';
        } else {
            echo "<!-- LOG: User: Logged In - Not Registered (Live & On Demand Logic) -->\n";
            echo "<script>console.log('[LOG] User: Logged In - Not Registered (Live & On Demand Logic)');</script>\n";
            echo '<div class="full-page vertical-half-margin event-registration">';
            echo '<button class="webinar-register-button webinar-registration" data-event-id="' . esc_attr($post_id) . '">Register Now</button>';
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
        }
        return ob_get_clean();
    }

    /* ----------------------------------------------------------------------
     * Section: Event Has Passed (Not On Demand)
     * ---------------------------------------------------------------------- */
    echo "<!-- LOG: Webinar Type: Live Webinar (Event Passed, Not On Demand) -->\n";
    echo "<script>console.log('[LOG] Webinar Type: Live Webinar (Event Passed, Not On Demand)');</script>\n";
    echo "<!-- LOG: User: Logged In - Registered - Post Saved To Collection (Live & On Demand Logic) -->\n";
    echo "<script>console.log('[LOG] User: Logged In - Registered - Post Saved To Collection (Live & On Demand Logic)');</script>\n";
    echo '<div class="full-page vertical-half-margin event-registration">';
    echo '<button class="event-register-button event-passed">On Demand - Register Now</button>';
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