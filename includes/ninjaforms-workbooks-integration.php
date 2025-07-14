<?php
if (!defined('ABSPATH')) exit;

// DISABLED: This integration has been replaced by the new AJAX handler in the main plugin file
// The new handler is more robust and handles user registration + Workbooks sync properly

/*
add_action('ninja_forms_after_submission', function($form_data) {
    $fields = [];
    foreach ($form_data['fields'] as $field) {
        $fields[$field['key']] = $field['value'];
    }

    // Map Ninja Forms field keys to expected keys for registration
    $webinar_post_id = $fields['post_id'] ?? '';
    // Always use the logged-in user's email
    $participant_email = '';
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $participant_email = $user->user_email;
    }
    $speaker_question = $fields['speaker_question'] ?? '';
    $participant_question = $fields['participant_question'] ?? '';
    // Opt-in checkbox key from your form config
    $sponsor_optin = (isset($fields['sponsor_optin']) && $fields['sponsor_optin']) ? 1 : 0;

    // Log the front-end submission
    if (function_exists('dtr_log_to_file')) {
        dtr_log_to_file('Ninja Forms submission: ' . print_r($fields, true));
    }

    // Call the shared registration logic
    $result = dtr_register_workbooks_webinar([
        'webinar_post_id' => $webinar_post_id,
        'participant_email' => $participant_email,
        'participant_question' => $participant_question,
        'speaker_question' => $speaker_question,
        'sponsor_optin' => $sponsor_optin,
    ]);

    // Optionally, you can handle $result['success'] or $result['error'] here
    // For example, add a note to the submission or trigger a custom action
}, 10, 1);
*/
