<?php
if (!defined('ABSPATH')) exit;

/**
 * DTR_Media_Planner_Handler
 *
 * Handles Workbooks CRM integration for DTR Media Planner 2025 form submissions.
 * Logs every step and every API payload/response, and handles all errors gracefully.
 * 
 * Usage:
 *   add_action('ninja_forms_after_submission', [DTR_Media_Planner_Handler::class, 'handle_form_submission'], 10, 1);
 */
class DTR_Media_Planner_Handler {

    /**
     * Log messages to /logs/media-planner-debug.log (relative to plugin base directory) and PHP error log.
     */
    private static function log($msg, $context = []) {
        $plugin_dir = dirname(__FILE__);
        $log_dir = $plugin_dir . '/logs';
        $log_file = $log_dir . '/media-planner-debug.log';
        $date = date('Y-m-d H:i:s');
        $entry = "[$date] $msg";
        if (!empty($context)) {
            $entry .= "\n" . print_r($context, true);
        }
        if (!file_exists($log_dir)) {
            @mkdir($log_dir, 0777, true);
        }
        if (!file_exists($log_dir)) {
            error_log("DTR_Media_Planner_Handler: Failed to create log directory: $log_dir");
        }
        if (!is_writable($log_dir)) {
            error_log("DTR_Media_Planner_Handler: Log directory is not writable: $log_dir");
        }
        if (@file_put_contents($log_file, $entry . "\n", FILE_APPEND) === false) {
            error_log("DTR_Media_Planner_Handler: Failed to write to log file: $log_file");
        }
    }

    /**
     * Extracts fields from Ninja Forms $form_data (array or object).
     * Logs field extraction.
     */
    private static function extract_fields($form_data) {
        $fields = [];

        // Ninja Forms 3.x+ passes $form_data as an array with 'fields'
        if (is_array($form_data) && isset($form_data['fields'])) {
            foreach ($form_data['fields'] as $field) {
                $key = isset($field['key']) ? $field['key'] : (isset($field['settings']['key']) ? $field['settings']['key'] : null);
                $value = $field['value'] ?? (isset($field['data']['value']) ? $field['data']['value'] : null);
                if ($key !== null) $fields[$key] = $value;
            }
        }
        // fallback for object style (rare)
        elseif (is_object($form_data) && method_exists($form_data, 'get_fields')) {
            foreach ($form_data->get_fields() as $field) {
                $key = $field->get_setting('key');
                $value = $field->get_value();
                $fields[$key] = $value;
            }
        }
        self::log('Extracted form fields:', $fields);
        return $fields;
    }

    /**
     * Returns a cleaned payload array for Workbooks, containing only allowed fields.
     */
    private static function build_ticket_payload($fields, $person_id, $event_id, $name, $status = 'Registered') {
        // Allowed fields for create/update (add your custom fields as needed)
        $allowed_fields = [
            'name','event_id','person_id','status',
            'cf_first_name','cf_last_name','cf_email_address','cf_job_title','cf_organisation','cf_town','cf_country','cf_telephone',
            'cf_can_we_help_further','cf_event_ticket_consent',
            // Add other cf_* fields you use here:
            'event_id_1732203940068', 'data_source_detail_1732204025306', 'download_name_1732204274421',
            'type_1732208568466', 'lead_source_type_1732208694858', 'cf_customer_order_brand_for_pdf_1732208735776',
            'campaign_name_1732208781788', 'cf_customer_order_line_item_brand_1732208868788',
            'cf_customer_order_line_item_rp_product_delegate_1732208933777', 'cf_customer_order_line_item_subproduct_event_1732208994225',
            'cf_customer_order_line_item_streams_1732209060666', 'cf_customer_order_line_item_campaign_delegate_1732209117796',
            'cf_customer_order_line_item_campaign_reference_2_1732209161771', 'cf_customer_order_line_item_delegate_type_1732209222275',
            'cf_customer_order_line_item_delegate_type_608_1732209281011', 'cf_customer_order_line_item_delegate_ticket_type_1732209328082',
            'cf_customer_order_line_item_attended_1732209540103', 'cf_customer_order_line_item_dinner_1732209600634',
            'assigned_to_1732209666137', 'web_key_1732209728272', 'success_url_1732209783513',
            'failure_url_1732209872103', 'sales_lead_rating_1732209952649', 'lead_type_1732210024689',
            'dtr_subscriber_type_1732210162873', 'product_mix_1732210297977', 'name1_1732638012825',
            'name2_1732638070889', 'org_lead_party_email_1732638130633'
        ];

        // Always required
        $payload = [
            'name' => $name,
            'event_id' => $event_id,
            'person_id' => $person_id,
            'status' => $status,
            'cf_first_name' => $fields['first_name'] ?? '',
            'cf_last_name' => $fields['last_name'] ?? '',
            'cf_email_address' => $fields['email_address'] ?? '',
            'cf_job_title' => $fields['job_title'] ?? '',
            'cf_organisation' => $fields['organisation'] ?? '',
            'cf_town' => $fields['town'] ?? '',
            'cf_country' => $fields['country'] ?? '',
            'cf_telephone' => $fields['telephone'] ?? '',
            'cf_can_we_help_further' => $fields['can_we_help_further_1731335191275'] ?? '',
        ];

        // Consent field
        if (isset($fields['i_consent_to_drug_target_review_collecting_my_data_1738172471989'])) {
            $consent_val = $fields['i_consent_to_drug_target_review_collecting_my_data_1738172471989'];
            $consent_bool = ($consent_val === 'Checked' || $consent_val === 'on' || $consent_val === '1' || $consent_val === 1 || $consent_val === true);
            $payload['cf_event_ticket_consent'] = $consent_bool ? 'yes' : 'no';
        }

        // Hidden keys as custom fields
        foreach ($allowed_fields as $key) {
            if (!isset($payload[$key]) && isset($fields[$key])) {
                $payload[$key] = $fields[$key];
            }
        }

        // Remove empty picklists (e.g. status) if necessary
        foreach (['status'] as $picklist) {
            if (isset($payload[$picklist]) && $payload[$picklist] === '') {
                unset($payload[$picklist]);
            }
        }
        return $payload;
    }

    public static function handle_form_submission($form_data) {
        self::log('=== PROCESSING MEDIA PLANNER FORM SUBMISSION ===');
        self::log('Raw $form_data:', $form_data);

        $fields = self::extract_fields($form_data);

        // Map main contact fields
        $email = $fields['email_address'] ?? '';
        $first_name = $fields['first_name'] ?? '';
        $last_name = $fields['last_name'] ?? '';
        $job_title = $fields['job_title'] ?? '';
        $organisation = $fields['organisation'] ?? '';
        $town = $fields['town'] ?? '';
        $country = $fields['country'] ?? '';
        $telephone = $fields['telephone'] ?? '';

        if (empty($email)) {
            self::log("❌ ERROR: Email address not found in submission");
            return;
        }
        $name = trim($first_name . ' ' . $last_name);
        if (empty($name)) {
            $name = "Unknown Name ({$email})";
        }

        // Authenticate Workbooks API instance
        try {
            self::log("Authenticating Workbooks API...");
            $workbooks = get_workbooks_instance();
            self::log("Workbooks instance created.");
        } catch (Exception $e) {
            self::log("❌ ERROR: Could not instantiate Workbooks API: " . $e->getMessage());
            return;
        }

        // 1. Find or create person in Workbooks
        $person_id = null;
        try {
            $person_search_params = [
                '_ff[]' => 'main_location[email]',
                '_ft[]' => 'eq',
                '_fc[]' => $email,
                '_limit' => 1,
                '_select_columns[]' => ['id'],
            ];
            self::log('Person search params:', $person_search_params);
            $search = $workbooks->assertGet('crm/people.api', $person_search_params);
            self::log('Workbooks person search result:', $search);
            if (!empty($search['data'][0]['id'])) {
                $person_id = $search['data'][0]['id'];
                self::log("Found existing person in Workbooks: $person_id");
            } else {
                $person_payload = [[
                    'person_first_name' => $first_name ?: $name,
                    'person_last_name' => $last_name ?: '',
                    'main_location[email]' => $email,
                    'main_location[telephone]' => $telephone,
                    'main_location[town]' => $town,
                    'main_location[country]' => $country,
                    'person_job_title' => $job_title,
                    'person_organisation' => $organisation,
                ]];
                self::log('Person create payload:', $person_payload);
                // Always assign array to variable before passing by reference!
                $person_objs = $person_payload;
                $create = $workbooks->assertCreate('crm/people.api', $person_objs);
                self::log('Person create result:', $create);
                if (!empty($create['affected_objects'][0]['id'])) {
                    $person_id = $create['affected_objects'][0]['id'];
                    self::log("Created new person in Workbooks: $person_id");
                } else {
                    self::log("❌ ERROR: Could not create person in Workbooks", $create);
                    return;
                }
            }
        } catch (Exception $e) {
            self::log("❌ ERROR: Person lookup/create failed: " . $e->getMessage());
            return;
        }

        // 2. Find the Media Planner event (static ID for 2025)
        $event_id = $fields['event_id_1732203940068'] ?? 5137;
        try {
            $event_search_params = [
                '_limit' => 1,
                '_ff[]' => 'id',
                '_ft[]' => 'eq',
                '_fc[]' => $event_id,
            ];
            self::log('Event search params:', $event_search_params);
            $event = $workbooks->assertGet('event/events.api', $event_search_params);
            self::log('Workbooks event search result:', $event);
            if (empty($event['data'][0])) {
                self::log("❌ ERROR: Media Planner event not found in Workbooks (ID: $event_id)");
                return;
            }
            self::log("Found Media Planner event in Workbooks: $event_id");
        } catch (Exception $e) {
            self::log("❌ ERROR: Event lookup failed: " . $e->getMessage());
            return;
        }

        // 3. Check for existing ticket/registration
        $ticket_id = null;
        $lock_version = null;
        try {
            $ticket_search_params = [
                '_limit' => 1,
                '_ff[]' => 'event_id',
                '_ft[]' => 'eq',
                '_fc[]' => $event_id,
                '_ff[]' => 'person_id',
                '_ft[]' => 'eq',
                '_fc[]' => $person_id,
                '_select_columns[]' => ['id', 'lock_version'],
            ];
            self::log('Ticket search params:', $ticket_search_params);
            $existing_ticket = $workbooks->assertGet('event/tickets.api', $ticket_search_params);
            self::log('Workbooks ticket search result:', $existing_ticket);
            $ticket_id = $existing_ticket['data'][0]['id'] ?? null;
            $lock_version = $existing_ticket['data'][0]['lock_version'] ?? null;
            if ($ticket_id) {
                self::log("Found existing ticket: $ticket_id");
            } else {
                self::log("No existing ticket found, will create new");
            }
        } catch (Exception $e) {
            self::log("❌ ERROR: Ticket lookup failed: " . $e->getMessage());
        }

        // 4. Build ticket payload
        $ticket_payload = self::build_ticket_payload($fields, $person_id, $event_id, $name, 'Registered');
        // Add id and lock_version for update
        if ($ticket_id && $lock_version) {
            $ticket_payload['id'] = $ticket_id;
            $ticket_payload['lock_version'] = $lock_version;
        }
        self::log('Final ticket payload:', $ticket_payload);

        // 5. Update or create ticket, log everything
        try {
            if ($ticket_id && $lock_version) {
                self::log("Updating existing ticket $ticket_id...");
                $ticket_objs = [ $ticket_payload ]; // assign to variable for by-ref
                $response = $workbooks->assertUpdate('event/tickets.api', $ticket_objs);
                self::log('Ticket update response:', $response);
                if (isset($response['success']) && $response['success'] === false) {
                    self::log("Update failed, will create new ticket", $response);
                    unset($ticket_payload['id'], $ticket_payload['lock_version']);
                    $ticket_objs = [ $ticket_payload ];
                    $response = $workbooks->assertCreate('event/tickets.api', $ticket_objs);
                    self::log('Ticket create fallback response:', $response);
                }
            } else {
                self::log("Creating new ticket...");
                $ticket_objs = [ $ticket_payload ]; // assign to variable for by-ref
                $response = $workbooks->assertCreate('event/tickets.api', $ticket_objs);
                self::log('Ticket create response:', $response);
            }
            // Redact sensitive fields before logging result
            $redacted_response = $response;
            if (isset($redacted_response['affected_objects'][0])) {
                foreach (['cf_email_address', 'cf_telephone', 'cf_first_name', 'cf_last_name'] as $sensitive) {
                    if (isset($redacted_response['affected_objects'][0][$sensitive])) {
                        $redacted_response['affected_objects'][0][$sensitive] = '[REDACTED]';
                    }
                }
            }
            self::log("Ticket API response (redacted):", $redacted_response);
            $final_ticket_id = $response['affected_objects'][0]['id'] ?? null;
            if ($final_ticket_id) {
                self::log("✅ SUCCESS: Ticket/registration processed successfully: $final_ticket_id");
            } else {
                self::log("❌ ERROR: Ticket/registration response missing final ID", $response);
            }
        } catch (Exception $e) {
            self::log("❌ ERROR: Ticket update/create failed: " . $e->getMessage());
            // Try to log raw response if possible (if $response exists)
            if (isset($response)) {
                self::log("❌ ERROR: Workbooks API raw response:", $response);
            }
        }
    }
}

// Usage (add to your plugin's main file or init):
// add_action('ninja_forms_after_submission', [DTR_Media_Planner_Handler::class, 'handle_form_submission'], 10, 1);