<?php
if (!defined('ABSPATH')) exit;

// === Webinar AJAX Handlers ===
add_action('wp_ajax_fetch_webinar_acf_data', 'fetch_webinar_acf_data_callback');
add_action('wp_ajax_nopriv_fetch_webinar_acf_data', 'fetch_webinar_acf_data_callback');
function fetch_webinar_acf_data_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $post_id = absint($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error('Invalid webinar selected.');
    }
    $webinar_fields = get_field('webinar_fields', $post_id);
    wp_send_json_success([
        'workbooks_reference' => $webinar_fields['workbook_reference'] ?? '',
        'campaign_reference' => $webinar_fields['campaign_reference'] ?? '',
    ]);
}

// === Fetch Workbooks Event Handler ===
add_action('wp_ajax_fetch_workbooks_event', 'fetch_workbooks_event_callback');
add_action('wp_ajax_nopriv_fetch_workbooks_event', 'fetch_workbooks_event_callback');
function fetch_workbooks_event_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $event_ref = sanitize_text_field($_POST['event_ref'] ?? '');
    if (!$event_ref) {
        wp_send_json_error('Missing event reference.');
    }
    $workbooks = get_workbooks_instance();
    $params = [
        '_limit' => 1,
        '_ff[]' => is_numeric($event_ref) ? 'id' : 'reference',
        '_ft[]' => 'eq',
        '_fc[]' => $event_ref,
    ];
    $response = $workbooks->assertGet('event/events.api', $params);
    if (!empty($response['data'][0])) {
        wp_send_json_success($response['data'][0]);
    } else {
        wp_send_json_error('Event not found.');
    }
}

// Webinar register handler (with nonce check)
add_action('wp_ajax_workbooks_webinar_register', 'workbooks_webinar_register_callback');
add_action('wp_ajax_nopriv_workbooks_webinar_register', 'workbooks_webinar_register_callback');

function workbooks_webinar_register_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');
    $result = dtr_register_workbooks_webinar($_POST);
    if (isset($result['success'])) {
        wp_send_json_success($result['success']);
    } else {
        wp_send_json_error($result['error'] ?? 'Unknown error');
    }
}

// === Fetch employers (no nonce check, public) ===
add_action('wp_ajax_fetch_workbooks_employers', 'fetch_workbooks_employers_callback');
add_action('wp_ajax_nopriv_fetch_workbooks_employers', 'fetch_workbooks_employers_callback');

function fetch_workbooks_employers_callback() {
    check_ajax_referer('workbooks_nonce', 'nonce');

    global $wpdb;

    $table_name = $wpdb->prefix . 'workbooks_employers';

    $results = $wpdb->get_results("SELECT id, name FROM $table_name ORDER BY name ASC", ARRAY_A);

    if ($results) {
        wp_send_json_success($results);
    } else {
        wp_send_json_error('No employers found');
    }
}


function dtr_log_to_file($message) {
    $log_file = dirname(__DIR__) . '/logs/workbooks-2025-06-27.log';
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND);
}

function dtr_register_workbooks_webinar($data) {
    $webinar_id = absint($data['webinar_post_id'] ?? 0);
    $email = sanitize_email($data['participant_email'] ?? '');
    $question = sanitize_textarea_field($data['participant_question'] ?? '');
    $speaker_question = sanitize_textarea_field($data['speaker_question'] ?? '');
    $sponsor_optin = isset($data['sponsor_optin']) && $data['sponsor_optin'] == '1' ? 1 : 0;
    if (!$webinar_id || empty($email)) {
        dtr_log_to_file('Webinar Registration Error: Webinar ID or email missing.');
        return ['error' => 'Webinar selection and participant email are required.'];
    }
    $webinar_fields = get_field('webinar_fields', $webinar_id);
    $workbook_reference = $webinar_fields['workbook_reference'] ?? '';
    $campaign_reference = $webinar_fields['campaign_reference'] ?? '';
    if (empty($workbook_reference) || !is_numeric($workbook_reference)) {
        dtr_log_to_file('Webinar Registration Error: Invalid or missing Workbooks Webinar Reference for post ID ' . $webinar_id);
        return ['error' => 'Invalid or missing Workbooks Webinar Reference.'];
    }
    $user = wp_get_current_user();
    $name = trim($user->first_name . ' ' . $user->last_name);
    if (empty($name)) {
        $name = $email;
        dtr_log_to_file('Webinar Registration Warning: User name empty, using email as fallback: ' . $email);
    }
    $workbooks = get_workbooks_instance();

    // Find or create person in Workbooks
    $person_id = null;
    try {
        // Search for person by email
        $search = $workbooks->assertGet('crm/people.api', [
            '_ff[]' => 'main_location[email]',
            '_ft[]' => 'eq',
            '_fc[]' => $email,
            '_limit' => 1,
            '_select_columns[]' => ['id'],
        ]);
        if (!empty($search['data'][0]['id'])) {
            $person_id = $search['data'][0]['id'];
            dtr_log_to_file('Found existing person in Workbooks: ' . $person_id);
        } else {
            // Create person if not found
            $person_payload = [[
                'person_first_name' => $user->first_name ?: $name,
                'person_last_name' => $user->last_name ?: '',
                'main_location[email]' => $email,
            ]];
            $create = $workbooks->assertCreate('crm/people.api', $person_payload);
            if (!empty($create['data'][0]['id'])) {
                $person_id = $create['data'][0]['id'];
                dtr_log_to_file('Created new person in Workbooks: ' . $person_id);
            } else {
                dtr_log_to_file('Failed to create person in Workbooks.');
                return ['error' => 'Could not create person in Workbooks.'];
            }
        }
    } catch (Exception $e) {
        dtr_log_to_file('Person search/create exception: ' . $e->getMessage());
        return ['error' => 'Exception creating/finding person: ' . $e->getMessage()];
    }

    try {
        // Check for existing ticket for this person/event
        $existing_ticket = $workbooks->assertGet('event/tickets.api', [
            '_limit' => 1,
            '_ff[]' => 'event_id',
            '_ft[]' => 'eq',
            '_fc[]' => (int)$workbook_reference,
            '_ff[]' => 'person_id',
            '_ft[]' => 'eq',
            '_fc[]' => $person_id,
            '_select_columns[]' => ['id', 'lock_version'],
        ]);
        $ticket_id = $existing_ticket['data'][0]['id'] ?? null;
        $lock_version = $existing_ticket['data'][0]['lock_version'] ?? null;

        $ticket_payload = [[
            'event_id' => (int)$workbook_reference,
            'person_id' => $person_id,
            'name' => $name,
            'status' => 'Registered',
            'cf_event_ticket_speaker_questions' => $speaker_question,
            'cf_event_ticket_sponsor_optin' => $sponsor_optin,
        ]];
        if ($ticket_id && $lock_version) {
            $ticket_payload[0]['id'] = $ticket_id;
            $ticket_payload[0]['lock_version'] = $lock_version;
            dtr_log_to_file('Updating existing ticket: ' . $ticket_id);
            $response = $workbooks->assertUpdate('event/tickets.api', $ticket_payload);
        } else {
            dtr_log_to_file('Creating new ticket');
            $response = $workbooks->create('event/tickets.api', $ticket_payload);
        }
        dtr_log_to_file('Webinar Registration Ticket Payload: ' . print_r($ticket_payload, true));
        dtr_log_to_file('Workbooks API Response: ' . print_r($response, true));
        $workbooks->assertResponse($response, 'ok', 'Unexpected response from Workbooks API');
        $final_ticket_id = $response['affected_objects'][0]['id'] ?? null;
        if ($final_ticket_id) {
            return ['success' => 'Webinar registration submitted successfully. Ticket ID: ' . $final_ticket_id];
        } else {
            dtr_log_to_file('Webinar Registration Error: No ticket ID in response.');
            return ['error' => 'Unexpected ticket response: ' . json_encode($response)];
        }
    } catch (Exception $e) {
        dtr_log_to_file('Webinar Registration Exception: ' . $e->getMessage());
        return ['error' => 'Exception: ' . $e->getMessage()];
    }
}
