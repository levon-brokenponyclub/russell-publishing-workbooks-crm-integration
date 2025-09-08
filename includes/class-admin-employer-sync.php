<?php
if (!class_exists('DTR_Workbooks_Employer_Sync')) {
    class DTR_Workbooks_Employer_Sync {
        /**
         * Sync all employers from Workbooks API into DB & JSON.
         * @return array Result array with 'success' and 'message'
         */
        public function sync_all_employers() {
            try {
                workbooks_sync_employers_cron();
                return [
                    'success' => true,
                    'message' => 'Employers sync completed successfully.'
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Sync failed: ' . $e->getMessage()
                ];
            }
        }

        /**
         * Generate employers.json from the database.
         * @return array Result array with 'success' and 'message'
         */
        public function generate_employers_json() {
            return workbooks_generate_employers_json_from_db();
        }

        // --- AJAX Handlers ---
        public static function ajax_sync_employers() {
            if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
            $sync = new self();
            $result = $sync->sync_all_employers();
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        }

        public static function ajax_generate_json() {
            if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
            $sync = new self();
            $result = $sync->generate_employers_json();
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        }

        public static function ajax_load_employers() {
            if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
            global $wpdb;
            $table = $wpdb->prefix . 'workbooks_employers';
            $limit = 20;
            $results = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT $limit", ARRAY_A);
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            wp_send_json_success(array('employers' => $results, 'count' => $count));
        }

        public static function ajax_search_employers() {
            if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
            global $wpdb;
            $table = $wpdb->prefix . 'workbooks_employers';
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $limit = 20;
            $sql = $wpdb->prepare("SELECT * FROM $table WHERE name LIKE %s ORDER BY id DESC LIMIT $limit", '%' . $wpdb->esc_like($search) . '%');
            $results = $wpdb->get_results($sql, ARRAY_A);
            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE name LIKE %s", '%' . $wpdb->esc_like($search) . '%'));
            wp_send_json_success(array('employers' => $results, 'count' => $count));
        }

        public static function ajax_load_more_employers() {
            if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $limit = 20;
            $offset = $page * $limit;
            global $wpdb;
            $table = $wpdb->prefix . 'workbooks_employers';
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset), ARRAY_A);
            wp_send_json_success(array('employers' => $results));
        }

        // Register AJAX handlers
        public static function register_ajax_handlers() {
            add_action('wp_ajax_workbooks_sync_employers', [self::class, 'ajax_sync_employers']);
            add_action('wp_ajax_workbooks_generate_json', [self::class, 'ajax_generate_json']);
            add_action('wp_ajax_workbooks_load_employers', [self::class, 'ajax_load_employers']);
            add_action('wp_ajax_workbooks_search_employers', [self::class, 'ajax_search_employers']);
            add_action('wp_ajax_workbooks_load_more_employers', [self::class, 'ajax_load_more_employers']);

            // Add handlers to match JS AJAX actions
            add_action('wp_ajax_fetch_workbooks_employers_paged', [self::class, 'ajax_fetch_workbooks_employers_paged']);
            // Do NOT register fetch_workbooks_organisations_batch here; let the global handler in class-employer-sync.php handle it
            add_action('wp_ajax_resync_workbooks_employer', [self::class, 'ajax_resync_workbooks_employer']);
        }

        /**
         * AJAX: Fetch paged employers (for admin table)
         */
        public static function ajax_fetch_workbooks_employers_paged() {
            if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
            global $wpdb;
            $table = $wpdb->prefix . 'workbooks_employers';
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $where = '';
            $params = [];
            if ($search !== '') {
                $where = 'WHERE name LIKE %s';
                $params[] = '%' . $wpdb->esc_like($search) . '%';
            }
            $sql = "SELECT * FROM $table $where ORDER BY id DESC LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;
            $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
            $count_sql = $where ? "SELECT COUNT(*) FROM $table $where" : "SELECT COUNT(*) FROM $table";
            $count = $where ? $wpdb->get_var($wpdb->prepare($count_sql, $params[0])) : $wpdb->get_var($count_sql);
            wp_send_json_success(['employers' => $results, 'total' => intval($count)]);
        }


        /**
         * AJAX: Resync a single employer by ID
         */
        public static function ajax_resync_workbooks_employer() {
            if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
            $employer_id = isset($_POST['employer_id']) ? intval($_POST['employer_id']) : 0;
            if (!$employer_id) wp_send_json_error('Missing employer ID');
            if (!function_exists('workbooks_resync_single_employer')) {
                wp_send_json_error('Resync function not available');
            }
            $result = workbooks_resync_single_employer($employer_id);
            if (isset($result['success']) && $result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        }
    }
    // Register handlers immediately if in admin
    if (is_admin()) {
        DTR_Workbooks_Employer_Sync::register_ajax_handlers();
    }
}