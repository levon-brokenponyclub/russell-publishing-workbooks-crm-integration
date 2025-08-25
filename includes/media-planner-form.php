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
        self::log('=== PROCESSING MEDIA PLANNER FORM SUBMISSION (AJAX-style) ===');
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
        if (empty($name)) $name = $email;

        // Early exit: skip mailing lists
        if (preg_match('/(mail(ing)?list|newsletter|bounce|noreply|no-reply|unsubscribe|@.*lists?\.)/i', $email)) {
            self::log("⏭️ Skipped mailing list email: {$email}");
            return;
        }

        // Early exit: skip handler owner
        $owner_emails = [
            'levon.gravett@supersonic-playground.com',
            'levon-brokenponyclub@supersonic-playground.com'
        ];
        $owner_names = [
            'levon gravett'
        ];
        $input_name = strtolower(trim($first_name . ' ' . $last_name));
        if (in_array(strtolower($email), $owner_emails) || in_array($input_name, $owner_names)) {
            self::log("⏭️ Skipped duplicate handler owner: {$email} / $input_name");
            return;
        }

        // Hardcoded campaign/event info for this form context
        $parent_event_id = 472341; // Use parent event as per Workbooks API requirements
        $child_event_id = 5137;   // For reference/reporting if needed
        $campaign_ref = 'CAMP-41496';

        self::log("✅ Workbooks Parent Event Reference: $parent_event_id");
        self::log("✅ Child Event Reference: $child_event_id");
        self::log("✅ Campaign Reference: $campaign_ref");
        self::log("🔍 Checking if person exists with email: {$email}");

        // Workbooks API
        try {
            $workbooks = get_workbooks_instance();
            if (!$workbooks) throw new Exception('No Workbooks API instance');
        } catch (Exception $e) {
            self::log("❌ Error instantiating Workbooks API: " . $e->getMessage());
            return;
        }

        // Step 1: Find or create person
        $person_id = null;
        $person_object_ref = null;
        try {
            $person_search = [
                '_ff[]' => 'main_location[email]',
                '_ft[]' => 'eq',
                '_fc[]' => $email,
                '_limit' => 1,
                '_select_columns[]' => ['id','object_ref'],
            ];
            $search = $workbooks->assertGet('crm/people.api', $person_search);
            if (!empty($search['data'][0]['id'])) {
                $person_id = $search['data'][0]['id'];
                $person_object_ref = $search['data'][0]['object_ref'];
                self::log("✅ Found existing person: ID $person_id, Object Ref: PERS-{$person_object_ref}");
            } else {
                $person_payload = [[
                    'person_first_name' => $first_name,
                    'person_last_name' => $last_name,
                    'main_location[email]' => $email,
                ]];
                $create = $workbooks->assertCreate('crm/people.api', $person_payload);
                if (!empty($create['affected_objects'][0]['id'])) {
                    $person_id = $create['affected_objects'][0]['id'];
                    $person_object_ref = $create['affected_objects'][0]['object_ref'] ?? '';
                    self::log("✅ Created new person: ID $person_id, Object Ref: PERS-{$person_object_ref}");
                } else {
                    self::log("❌ ERROR: Could not create person in Workbooks");
                    return;
                }
            }
        } catch (Exception $e) {
            self::log("❌ ERROR: Person lookup/create failed: " . $e->getMessage());
            return;
        }

        // Step 2: Always create a new Ticket for the CHILD event
        self::log("🎫 Creating event ticket for child event $child_event_id");
        $ticket_id = null;
        try {
            $ticket_payload = [[
                'event_id' => $child_event_id,
                'person_id' => $person_id,
                'name' => $name,
                'status' => 'Registered'
            ]];
            self::log("Ticket payload: " . print_r($ticket_payload, true));
            $ticket_created = $workbooks->create('event/tickets.api', $ticket_payload);
            self::log("Ticket API raw response: " . print_r($ticket_created, true));
            try {
                $workbooks->assertResponse($ticket_created);
            } catch (Exception $e) {
                self::log("❌ Ticket API assertResponse failed: " . $e->getMessage());
            }
            $ticket_id = is_array($ticket_created) && isset($ticket_created['affected_objects'][0]['id'])
                ? $ticket_created['affected_objects'][0]['id']
                : null;
            if ($ticket_id) {
                self::log("✅ Created event ticket: ID $ticket_id");
            } else {
                self::log("❌ Ticket creation failed, no ID returned. Response: " . print_r($ticket_created, true));
            }
        } catch (Exception $e) {
            self::log("❌ Ticket create failed: " . $e->getMessage());
        }

        // Step 3: Always create a Sales Lead
        self::log(" 🎉 Creating event lead generation for parent event $parent_event_id");
        try {
            $queue_id = 1;
            $lead_payload = [[
                'assigned_to' => $queue_id,
                'person_lead_party[name]' => $name,
                'person_lead_party[person_first_name]' => $first_name,
                'person_lead_party[person_last_name]' => $last_name,
                'person_lead_party[email]' => $email,
                'org_lead_party[name]' => $organisation,
                'org_lead_party[main_location][town]' => $town,
                'org_lead_party[main_location][country]' => $country,
                'org_lead_party[main_location][telephone]' => $telephone,
                'cf_lead_data_source_detail' => 'DTR-MEDIA-PLANNER-2025',
                'cf_lead_campaign_reference' => $campaign_ref,
            ]];
            self::log('Lead payload:', $lead_payload);
            $lead_created = $workbooks->assertCreate('crm/sales_leads.api', $lead_payload);
            self::log('Lead API raw response:', $lead_created);
            $lead_id = $lead_created['affected_objects'][0]['id'] ?? null;
            if ($lead_id) {
                self::log("✅  Created lead for person $name {$email}");
            } else {
                self::log("❌ Lead creation failed, no ID returned. Response:", $lead_created);
            }
        } catch (Exception $e) {
            self::log("❌ Lead create failed: " . $e->getMessage());
        }

        // Step 4: Add or update Mailing List Entry for reporting (ONLY if mailing_list_id found)
        self::log("📧 Adding/updating mailing list entry for reporting");
        try {
            $event = $workbooks->assertGet('event/events.api', [
                '_start' => 0,
                '_limit' => 1,
                '_ff[]' => 'id',
                '_ft[]' => 'eq',
                '_fc[]' => $parent_event_id,
                '_select_columns[]' => ['id', 'mailing_list_id']
            ]);
            $mailing_list_id = $event['data'][0]['mailing_list_id'] ?? null;
            if (!$mailing_list_id) {
                self::log("❌ No mailing_list_id found for event_id=$parent_event_id");
            } else {
                // 2. Get email for person
                $person = $workbooks->assertGet('crm/people.api', [
                    '_start' => 0,
                    '_limit' => 1,
                    '_ff[]' => 'id',
                    '_ft[]' => 'eq',
                    '_fc[]' => $person_id,
                    '_select_columns[]' => ['id', 'main_location[email]']
                ]);
                $email_actual = $person['data'][0]['main_location[email]'] ?? $email;

                // 3. Search for existing mailing list entry
                $search_params = [
                    '_start' => 0,
                    '_limit' => 1,
                    '_ff[]' => ['mailing_list_id', 'email'],
                    '_ft[]' => ['eq', 'eq'],
                    '_fc[]' => [$mailing_list_id, $email_actual],
                    '_select_columns[]' => ['id']
                ];
                $entry_result = $workbooks->assertGet('email/mailing_list_entries.api', $search_params);
                $entry_id = $entry_result['data'][0]['id'] ?? null;

                $payload = [
                    'mailing_list_id' => $mailing_list_id,
                    'email' => $email_actual,
                ];

                if ($entry_id) {
                    $lock_version = $entry_result['data'][0]['lock_version'] ?? 0;
                    $update_result = $workbooks->assertUpdate('email/mailing_list_entries.api', [
                        array_merge(['id' => $entry_id, 'lock_version' => $lock_version], $payload)
                    ]);
                    self::log("✅ Mailing List Entry updated for $email_actual");
                } else {
                    $create_result = $workbooks->assertCreate('email/mailing_list_entries.api', [$payload]);
                    self::log("✅ Mailing List Entry created for $email_actual");
                }
            }
        } catch (Exception $e) {
            self::log("❌ Mailing List Entry create/update failed: " . $e->getMessage());
        }

        self::log("🥳 EVENT REGISTRATION SUCCESSFUL - CELEBRATE");
    }
}

// Usage (add to your plugin's main file or init):
// add_action('ninja_forms_after_submission', [DTR_Media_Planner_Handler::class, 'handle_form_submission'], 10, 1);