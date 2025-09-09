<?php
/**
 * Workbooks Lead Generation Registration Shortcode Logic
 * Usage: [workbooks-lead-generation-registration]
 * Renders the lead generation registration UI with all logic for user state, ACF-powered questions, and form display.
 */
function workbooks_lead_generation_registration_shortcode($atts = []) {
    ob_start();
    $post_id = get_the_ID();
    $lead_fields = function_exists('get_field') ? get_field('lead_fields', $post_id) : [];
    $lead_form = $lead_fields['lead_generation_form'] ?? ['id' => 31];
    $acf_questions = !empty($lead_fields['add_questions']) ? $lead_fields['add_questions'] : [];
    $add_additional_questions = !empty($lead_fields['add_additional_questions']) ? $lead_fields['add_additional_questions'] : false;
    $user_id = get_current_user_id();
    $user_is_logged_in = is_user_logged_in();
    $has_completed_form = $user_is_logged_in && function_exists('user_has_completed_form')
        ? user_has_completed_form($user_id, $lead_form['id'], $post_id)
        : false;

    // Not logged in: show login/register CTA
    if (!$user_is_logged_in) {
        echo '<div class="sidebar-leadgen-login-cta">';
        echo '<button class="event-register-button reveal-login-modal">Register Now</button>';
        echo '<div class="reveal-text">Login to register</div>';
        echo '</div>';
        return ob_get_clean();
    }

    // Always show form to logged-in users
    echo '<div class="ggated-lead-form-content">';

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
    echo '</div>'; // close .form-slide-content

    return ob_get_clean();
}
add_shortcode('workbooks-lead-generation-registration', 'workbooks_lead_generation_registration_shortcode');