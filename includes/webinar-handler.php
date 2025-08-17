<?php
/**
 * Workbooks Webinar Registration Handler
 * Handles registering webinar attendees in Workbooks from Ninja Forms or AJAX submissions.
 * 
 * This function should be included on every request where webinar registration may be triggered.
 */

if (!defined('ABSPATH')) exit;

/**
 * Write a detailed debug log entry for webinar registration.
 * Writes to /logs/webinar-debug.log in the plugin root.
 */
if (!function_exists('dtr_webinar_debug_log')) {
    function dtr_webinar_debug_log($level, $message, $data = []) {
        $log_file = defined('WORKBOOKS_NF_PATH')
            ? WORKBOOKS_NF_PATH . 'logs/webinar-debug.log'
            : __DIR__ . '/../logs/webinar-debug.log';

        // Ensure the logs directory exists
        $logs_dir = dirname($log_file);
        if (!file_exists($logs_dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($logs_dir);
            } else {
                mkdir($logs_dir, 0777, true);
            }
        }

        $timestamp = date('Y-m-d H:i:s');
        $entry = "[$timestamp] [$level] $message";
        if (!empty($data)) {
            $entry .= ' | ' . json_encode($data);
        }
        file_put_contents($log_file, $entry . PHP_EOL, FILE_APPEND);
    }
}

/**
 * Registers a webinar attendee in Workbooks.
 *
 * @param array $registration_data Associative array with keys:
 *  - 'webinar_post_id' (int)
 *  - 'participant_email' (string)
 *  - 'speaker_question' (string, optional)
 *  - 'privacy_consent' (bool/int, optional)
 *  - 'first_name' (string, optional)
 *  - 'last_name' (string, optional)
 * @return array|false Registration result array on success, false on failure.
 */
function dtr_register_workbooks_webinar($registration_data) {
    $post_id = $registration_data['webinar_post_id'] ?? 0;
    $email = $registration_data['participant_email'] ?? '';
    $speaker_question = $registration_data['speaker_question'] ?? '';
    $privacy_consent = !empty($registration_data['privacy_consent']) ? 1 : 0;
    $first_name = $registration_data['first_name'] ?? '';
    $last_name = $registration_data['last_name'] ?? '';

    // If user is logged in, use WP user info as fallback for name/email
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (empty($email)) {
            $email = $current_user->user_email;
        }
        if (empty($first_name)) {
            $first_name = $current_user->user_firstname ?: $current_user->display_name;
        }
        if (empty($last_name)) {
            $last_name = $current_user->user_lastname;
        }
    }

    dtr_webinar_debug_log('INFO', 'Webinar registration attempt', [
        'post_id' => $post_id,
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'wp_user_id' => is_user_logged_in() ? get_current_user_id() : null,
    ]);

    if (!$post_id || !$email) {
        dtr_webinar_debug_log('ERROR', 'Missing post_id or email for webinar registration');
        return false;
    }

    // Try to get Workbooks event reference from ACF or post meta or inside the 'webinar_fields' group
    $event_ref = get_field('workbooks_reference', $post_id)
        ?: get_post_meta($post_id, 'workbooks_reference', true)
        ?: get_field('reference', $post_id)
        ?: get_post_meta($post_id, 'reference', true);

    // Look inside 'webinar_fields' group if not found above (most likely for your setup)
    if (!$event_ref) {
        $webinar_fields = get_field('webinar_fields', $post_id);
        if (is_array($webinar_fields) && !empty($webinar_fields['workbook_reference'])) {
            $event_ref = $webinar_fields['workbook_reference'];
            dtr_webinar_debug_log('INFO', "Found event ref inside webinar_fields group", ['event_ref' => $event_ref]);
        }
    }

    if (!$event_ref) {
        dtr_webinar_debug_log('ERROR', "Missing Workbooks event reference for post $post_id");
        return false;
    }

    // Extract numeric ID from reference (e.g., EVENT-1234 or just 1234)
    if (!preg_match('/(\d+)$/', $event_ref, $matches)) {
        dtr_webinar_debug_log('ERROR', "Could not extract event_id from reference", ['event_ref' => $event_ref]);
        return false;
    }
    $event_id = $matches[1];

    // Look up or create the person in Workbooks
    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_webinar_debug_log('ERROR', "Workbooks instance not available.");
        return false;
    }

    // Search for person by email
    $person_result = $workbooks->assertGet('crm/people.api', [
        '_start' => 0, '_limit' => 1,
        '_ff[]' => 'main_location[email]', '_ft[]' => 'eq', '_fc[]' => $email,
        '_select_columns[]' => ['id', 'object_ref']
    ]);
    $person_id = $person_result['data'][0]['id'] ?? null;

    // If not found, create person
    if (!$person_id) {
        $create_person = $workbooks->assertCreate('crm/people', [[
            'main_location[email]' => $email,
            'person_first_name' => $first_name,
            'person_last_name' => $last_name,
        ]]);
        $person_id = $create_person['data'][0]['id'] ?? null;
        dtr_webinar_debug_log('INFO', 'Created new person in Workbooks', [
            'person_id' => $person_id,
            'email' => $email,
        ]);
    } else {
        dtr_webinar_debug_log('INFO', 'Found existing person in Workbooks', [
            'person_id' => $person_id,
            'email' => $email,
        ]);
    }
    if (!$person_id) {
        dtr_webinar_debug_log('ERROR', "Failed to create or find person for webinar", ['email' => $email]);
        return false;
    }

    // Create the event ticket in Workbooks
    $ticket_payload = [[
        'event_id' => $event_id,
        'person_id' => $person_id,
        'name' => trim(($first_name . ' ' . $last_name)) ?: $email, // Workbooks requires a name
        'status' => 'Registered',
        'cf_event_ticket_speaker_questions' => $speaker_question,
        'cf_event_ticket_sponsor_optin' => $privacy_consent
    ]];
    $ticket_result = $workbooks->create('event/tickets.api', $ticket_payload);

    if (!empty($ticket_result['affected_objects'][0]['id'])) {
        dtr_webinar_debug_log('SUCCESS', 'Webinar ticket created', [
            'ticket_id' => $ticket_result['affected_objects'][0]['id'],
            'person_id' => $person_id,
            'event_id' => $event_id,
        ]);
        return [
            'success' => true,
            'ticket_id' => $ticket_result['affected_objects'][0]['id'],
            'person_id' => $person_id,
            'event_id' => $event_id,
        ];
    } else {
        dtr_webinar_debug_log('ERROR', 'Failed to create webinar ticket', [
            'person_id' => $person_id,
            'event_id' => $event_id,
            'ticket_result' => $ticket_result,
        ]);
        return false;
    }
}