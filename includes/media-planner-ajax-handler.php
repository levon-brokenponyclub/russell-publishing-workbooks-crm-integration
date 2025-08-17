<?php
if (!defined('ABSPATH')) exit;

class Media_Planner_Test_Ajax_Handler {
    public static function init() {
        add_action('wp_ajax_media_planner_test_submit', [self::class, 'handle']);
        add_action('wp_ajax_nopriv_media_planner_test_submit', [self::class, 'handle']);
    }

    private static function log($msg, $context = []) {
        $plugin_dir = dirname(__FILE__);
        $log_dir = $plugin_dir . '/logs';
        $log_file = $log_dir . '/media-planner-ajax-debug.log';
        $date = date('Y-m-d H:i:s');
        $entry = "[$date] $msg";
        if (!empty($context)) $entry .= "\n" . print_r($context, true);
        if (!file_exists($log_dir)) @mkdir($log_dir, 0777, true);
        @file_put_contents($log_file, $entry . "\n", FILE_APPEND);
    }

    public static function handle() {
        // Enable error display for debugging
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        header('Content-Type: application/json; charset=utf-8');
        // Nonce
        if (
            !isset($_POST['nonce']) ||
            !wp_verify_nonce($_POST['nonce'], 'workbooks_nonce')
        ) {
            self::log('❌ Invalid nonce', $_POST);
            wp_send_json_error('Security check failed (invalid nonce)');
        }
        // Validate & sanitize
        $fields = [];
        $expected = [
            'first_name', 'last_name', 'email_address',
            'job_title', 'organisation', 'town', 'country', 'telephone'
        ];
        foreach ($expected as $field) {
            if (empty($_POST[$field])) {
                self::log("❌ Missing required field: $field", $_POST);
                wp_send_json_error("Missing required field: $field");
            }
            $fields[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
        }
        $name = trim($fields['first_name'] . ' ' . $fields['last_name']);
        if (empty($name)) $name = $fields['email_address'];
        self::log('Received AJAX form submission', $fields);

        // Workbooks API
        try {
            $workbooks = get_workbooks_instance();
            // Ensure the Workbooks API client is authenticated/session is valid
            if (property_exists($workbooks, 'login_state') && !$workbooks->login_state && method_exists($workbooks, 'login')) {
                $workbooks->login();
                self::log('Re-authenticated with Workbooks API before ticket creation');
            }
        } catch (Exception $e) {
            self::log("❌ Error instantiating Workbooks API: " . $e->getMessage());
            wp_send_json_error("Could not connect to CRM");
        }

        // 1. Find or create person
        $person_id = null;
        try {
            $person_search = [
                '_ff[]' => 'main_location[email]',
                '_ft[]' => 'eq',
                '_fc[]' => $fields['email_address'],
                '_limit' => 1,
                '_select_columns[]' => ['id'],
            ];
            $search = $workbooks->assertGet('crm/people.api', $person_search);
            if (!empty($search['data'][0]['id'])) {
                $person_id = $search['data'][0]['id'];
                self::log("Found existing person in Workbooks: $person_id");
            } else {
                $person_payload = [[
                    'person_first_name' => $fields['first_name'],
                    'person_last_name' => $fields['last_name'],
                    'main_location[email]' => $fields['email_address'],
                    'main_location[telephone]' => $fields['telephone'],
                    'main_location[town]' => $fields['town'],
                    'main_location[country]' => $fields['country'],
                    'person_job_title' => $fields['job_title'],
                    'person_organisation' => $fields['organisation'],
                ]];
                $create = $workbooks->assertCreate('crm/people.api', $person_payload);
                if (!empty($create['affected_objects'][0]['id'])) {
                    $person_id = $create['affected_objects'][0]['id'];
                    self::log("Created new person in Workbooks: $person_id");
                } else {
                    self::log("❌ ERROR: Could not create person in Workbooks", $create);
                    wp_send_json_error("Could not create person in CRM");
                }
            }
        } catch (Exception $e) {
            self::log("❌ ERROR: Person lookup/create failed: " . $e->getMessage());
            wp_send_json_error("Could not create/find CRM person");
        }

        // 2. Create a Ticket (Event Registration) with minimal required data
        $event_id = 5137; // Confirmed for EVENT-2571
        $ticket_id = null;
        try {
            if (property_exists($workbooks, 'login_state') && !$workbooks->login_state && method_exists($workbooks, 'login')) {
                $workbooks->login();
                self::log('Re-authenticated with Workbooks API before ticket creation (pre-ticket)');
            }
            // Prevent duplicates
            $ticket_search_params = [
                '_limit' => 1,
                '_ff[]' => 'event_id',
                '_ft[]' => 'eq',
                '_fc[]' => $event_id,
                '_ff[]' => 'person_id',
                '_ft[]' => 'eq',
                '_fc[]' => $person_id,
                '_select_columns[]' => ['id'],
            ];
            $existing_ticket = $workbooks->assertGet('event/tickets.api', $ticket_search_params);
            $ticket_id = $existing_ticket['data'][0]['id'] ?? null;
            if (!$ticket_id) {
                $ticket_payload = [[
                    'event_id' => $event_id,
                    'person_id' => $person_id,
                    'name' => $name
                ]];
                self::log("DEBUG: About to call assertCreate on event/tickets.api", $ticket_payload);
                try {
                    if (property_exists($workbooks, 'login_state') && !$workbooks->login_state && method_exists($workbooks, 'login')) {
                        $workbooks->login();
                        self::log('Re-authenticated with Workbooks API before ticket creation (inside create try)');
                    }
                    $ticket_created = $workbooks->assertCreate('event/tickets.api', $ticket_payload);
                    self::log("Ticket creation raw response", $ticket_created);
                    self::log("Type of ticket_created", gettype($ticket_created));
                    $ticket_id = is_array($ticket_created) && isset($ticket_created['affected_objects'][0]['id'])
                        ? $ticket_created['affected_objects'][0]['id']
                        : null;
                    if (!$ticket_id) {
                        self::log("Ticket creation failed, no ID returned. Full response:", $ticket_created);
                        if (method_exists($workbooks, 'getLastResponse')) {
                            self::log("Ticket creation last response via getLastResponse", $workbooks->getLastResponse());
                        }
                        if (property_exists($workbooks, 'last_response')) {
                            self::log("Ticket creation last_response property", $workbooks->last_response);
                        }
                        ob_start();
                        var_dump($workbooks);
                        self::log("Workbooks object full dump (exception)", ob_get_clean());
                        wp_send_json_error("Could not create CRM ticket (event registration)");
                    }
                } catch (Exception $e) {
                    self::log("❌ Ticket create failed: " . $e->getMessage());
                    if (method_exists($workbooks, 'getLastResponse')) {
                        self::log("Ticket creation last response via getLastResponse (exception)", $workbooks->getLastResponse());
                    }
                    if (property_exists($workbooks, 'last_response')) {
                        self::log("Ticket creation last_response property (exception)", $workbooks->last_response);
                    }
                    ob_start();
                    var_dump($workbooks);
                    self::log("Workbooks object full dump (exception)", ob_get_clean());
                    wp_send_json_error("Could not create CRM ticket (event registration)");
                }
            } else {
                self::log("Found existing ticket", $existing_ticket);
            }
        } catch (Exception $e) {
            self::log("❌ Ticket search failed: " . $e->getMessage());
            wp_send_json_error("Could not search for existing CRM ticket");
        }

        // 3. Create a Sales Lead in Workbooks
        try {
            $lead_id = self::create_sales_lead($workbooks, $fields, $name);
            if ($lead_id) {
                self::log("Created sales lead", ['lead_id' => $lead_id]);
            }
        } catch (Exception $e) {
            self::log("❌ Lead create failed: " . $e->getMessage());
            // Optionally notify, but don't block success
        }

        self::log("✅ Media Planner Test submission processed OK", [
            'person_id' => $person_id,
            'ticket_id' => $ticket_id
        ]);
        wp_send_json_success("Submission received and sent to CRM! (Ticket and Lead created)");
    }

    /**
     * Create a sales lead in Workbooks
     * @param object $workbooks
     * @param array $fields
     * @param string $name
     * @return int|null Lead ID if created, null otherwise
     */
    private static function create_sales_lead($workbooks, $fields, $name) {
        // Assign to Unassigned queue (ID 1)
        $queue_id = 1;
        $lead_payload = [[
            'assigned_to' => $queue_id,
            'person_lead_party[name]' => $name,
            'person_lead_party[person_first_name]' => $fields['first_name'],
            'person_lead_party[person_last_name]' => $fields['last_name'],
            'person_lead_party[person_job_title]' => $fields['job_title'],
            'person_lead_party[email]' => $fields['email_address'],
            'org_lead_party[name]' => $fields['organisation'],
            'org_lead_party[main_location][town]' => $fields['town'],
            'org_lead_party[main_location][country]' => $fields['country'],
            'org_lead_party[main_location][telephone]' => $fields['telephone'],
            'cf_lead_data_source_detail' => 'DTR-MEDIA-PLANNER-2025'
        ]];
        $lead_created = $workbooks->assertCreate('crm/sales_leads.api', $lead_payload);
        self::log("Lead creation raw response", $lead_created);
        $lead_id = $lead_created['affected_objects'][0]['id'] ?? null;
        if (!$lead_id) {
            self::log("Lead creation failed, no ID returned. Full response:", $lead_created);
            return null;
        }
        return $lead_id;
    }
}

Media_Planner_Test_Ajax_Handler::init();