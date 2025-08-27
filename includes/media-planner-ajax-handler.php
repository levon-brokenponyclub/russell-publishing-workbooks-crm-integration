<?php
/* if (!defined('ABSPATH')) exit;

class Media_Planner_Test_Ajax_Handler {
    public static function init() {
        add_action('wp_ajax_media_planner_test_submit', [self::class, 'handle']);
        add_action('wp_ajax_nopriv_media_planner_test_submit', [self::class, 'handle']);
    }

    private static function log($msg) {
        $plugin_dir = dirname(__FILE__);
        $log_dir = $plugin_dir . '/logs';
        $log_file = $log_dir . '/media-planner-ajax-debug.log';
        $date = date('Y-m-d H:i:s');
        $entry = "[$date] $msg";
        if (!file_exists($log_dir)) @mkdir($log_dir, 0777, true);
        @file_put_contents($log_file, $entry . "\n", FILE_APPEND);
    }

    public static function handle() {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        header('Content-Type: application/json; charset=utf-8');
        if (
            !isset($_POST['nonce']) ||
            !wp_verify_nonce($_POST['nonce'], 'workbooks_nonce')
        ) {
            self::log('❌ Invalid nonce');
            wp_send_json_error('Security check failed (invalid nonce)');
        }
        $fields = [];
        $expected = [
            'first_name', 'last_name', 'email_address',
            'job_title', 'organisation', 'town', 'country', 'telephone'
        ];
        foreach ($expected as $field) {
            if (empty($_POST[$field])) {
                self::log("❌ Missing required field: $field");
                wp_send_json_error("Missing required field: $field");
            }
            $fields[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
        }
        $name = trim($fields['first_name'] . ' ' . $fields['last_name']);
        if (empty($name)) $name = $fields['email_address'];

        // Early exit: skip mailing lists
        if (preg_match('/(mail(ing)?list|newsletter|bounce|noreply|no-reply|unsubscribe|@.*lists?\.)/i', $fields['email_address'])) {
            self::log("⏭️ Skipped mailing list email: {$fields['email_address']}");
            wp_send_json_error('Mailing list addresses are not processed.');
        }

        // Early exit: skip handler owner
        $owner_emails = [
            'levon.gravett@supersonic-playground.com',
            'levon-brokenponyclub@supersonic-playground.com'
        ];
        $owner_names = [
            'levon gravett'
        ];
        $input_name = strtolower(trim($fields['first_name'] . ' ' . $fields['last_name']));
        if (in_array(strtolower($fields['email_address']), $owner_emails) ||
            in_array($input_name, $owner_names)) {
            self::log("⏭️ Skipped duplicate handler owner: {$fields['email_address']} / $input_name");
            wp_send_json_error('Duplicate/handler owner details detected. Submission not processed.');
        }

        // Hardcoded campaign/event info for this form context
        $parent_event_id = 472341; // Use parent event as per Workbooks API requirements
        $child_event_id = 5137;   // For reference/reporting if needed
        $campaign_ref = 'CAMP-41496';

        self::log("✅ Workbooks Parent Event Reference: $parent_event_id");
        self::log("✅ Child Event Reference: $child_event_id");
        self::log("✅ Campaign Reference: $campaign_ref");
        self::log("🔍 Checking if person exists with email: {$fields['email_address']}");

        // Workbooks API
        try {
            $workbooks = get_workbooks_instance();
            if (!$workbooks) throw new Exception('No Workbooks API instance');
        } catch (Exception $e) {
            self::log("❌ Error instantiating Workbooks API: " . $e->getMessage());
            wp_send_json_error("Could not connect to CRM");
        }

        // Step 1: Find or create person
        $person_id = null;
        $person_object_ref = null;

        try {
            $person_search = [
                '_ff[]' => 'main_location[email]',
                '_ft[]' => 'eq',
                '_fc[]' => $fields['email_address'],
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
                    'person_first_name' => $fields['first_name'],
                    'person_last_name' => $fields['last_name'],
                    'main_location[email]' => $fields['email_address'],
                ]];
                $create = $workbooks->assertCreate('crm/people.api', $person_payload);
                if (!empty($create['affected_objects'][0]['id'])) {
                    $person_id = $create['affected_objects'][0]['id'];
                    $person_object_ref = $create['affected_objects'][0]['object_ref'] ?? '';
                    self::log("✅ Created new person: ID $person_id, Object Ref: PERS-{$person_object_ref}");
                } else {
                    self::log("❌ ERROR: Could not create person in Workbooks");
                    wp_send_json_error("Could not create person in CRM");
                }
            }
        } catch (Exception $e) {
            self::log("❌ ERROR: Person lookup/create failed: " . $e->getMessage());
            wp_send_json_error("Could not create/find CRM person");
        }

        // Step 2: Always create a new Ticket for the CHILD event (changed here)
        self::log("🎫 Creating event ticket for child event $child_event_id");
        $ticket_id = null;
        try {
            $ticket_payload = [[
                'event_id' => $child_event_id, // <--- CHANGED from $parent_event_id to $child_event_id
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
                wp_send_json_error("Could not create CRM ticket (event registration)");
            }
        } catch (Exception $e) {
            self::log("❌ Ticket create failed: " . $e->getMessage());
            wp_send_json_error("Could not create CRM ticket (event registration)");
        }

        // Step 3: Always create a Sales Lead
        self::log(" 🎉 Creating event lead generation for parent event $parent_event_id");
        try {
            $queue_id = 1;
            $lead_payload = [[
                'assigned_to' => $queue_id,
                'person_lead_party[name]' => $name,
                'person_lead_party[person_first_name]' => $fields['first_name'],
                'person_lead_party[person_last_name]' => $fields['last_name'],
                'person_lead_party[email]' => $fields['email_address'],
                'org_lead_party[name]' => $fields['organisation'],
                'org_lead_party[main_location][town]' => $fields['town'],
                'org_lead_party[main_location][country]' => $fields['country'],
                'org_lead_party[main_location][telephone]' => $fields['telephone'],
                'cf_lead_data_source_detail' => 'DTR-MEDIA-PLANNER-2025',
                'cf_lead_campaign_reference' => $campaign_ref,
            ]];
            $lead_created = $workbooks->assertCreate('crm/sales_leads.api', $lead_payload);
            $lead_id = $lead_created['affected_objects'][0]['id'] ?? null;
            if ($lead_id) {
                self::log("✅  Created lead for person $name {$fields['email_address']}");
            } else {
                self::log("❌ Lead creation failed, no ID returned.");
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
                $email = $person['data'][0]['main_location[email]'] ?? $fields['email_address'];

                // 3. Search for existing mailing list entry
                $search_params = [
                    '_start' => 0,
                    '_limit' => 1,
                    '_ff[]' => ['mailing_list_id', 'email'],
                    '_ft[]' => ['eq', 'eq'],
                    '_fc[]' => [$mailing_list_id, $email],
                    '_select_columns[]' => ['id']
                ];
                $entry_result = $workbooks->assertGet('email/mailing_list_entries.api', $search_params);
                $entry_id = $entry_result['data'][0]['id'] ?? null;

                $payload = [
                    'mailing_list_id' => $mailing_list_id,
                    'email' => $email,
                ];

                if ($entry_id) {
                    $lock_version = $entry_result['data'][0]['lock_version'] ?? 0;
                    $update_result = $workbooks->assertUpdate('email/mailing_list_entries.api', [
                        array_merge(['id' => $entry_id, 'lock_version' => $lock_version], $payload)
                    ]);
                    self::log("✅ Mailing List Entry updated for $email");
                } else {
                    $create_result = $workbooks->assertCreate('email/mailing_list_entries.api', [$payload]);
                    self::log("✅ Mailing List Entry created for $email");
                }
            }
        } catch (Exception $e) {
            self::log("❌ Mailing List Entry create/update failed: " . $e->getMessage());
        }

        self::log("🥳 EVENT REGISTRATION SUCCESSFUL - CELEBRATE");
        wp_send_json_success("Submission received and sent to CRM! (Ticket, Lead, and Mailing List entry created if applicable)");
    }
}

Media_Planner_Test_Ajax_Handler::init(); */