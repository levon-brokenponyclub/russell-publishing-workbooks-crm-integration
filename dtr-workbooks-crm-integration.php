<?php
/**
 * Plugin Name: DTR Workbooks CRM Integration
 * Description: Enhanced WordPress plugin for DTR Workbooks CRM integration with comprehensive form handling, user registration, and lead management.
 * Version: 2.0.0
 * Author: SuperSonic Playground
 * Author URI: https://www.supersonicplayground.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dtr-workbooks
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 *
 * @package DTR/WorkbooksIntegration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}


// Plugin constants
// Bumped to 2.0.1 for cache-busting updated employer field script
error_log('DTR TEST LOG: Main plugin file is loading - dtr-workbooks-crm-integration.php');
define('DTR_WORKBOOKS_VERSION', '2.0.1');
define('DTR_WORKBOOKS_PLUGIN_FILE', __FILE__);
define('DTR_WORKBOOKS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DTR_WORKBOOKS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DTR_WORKBOOKS_INCLUDES_DIR', DTR_WORKBOOKS_PLUGIN_DIR . 'includes/');
define('DTR_WORKBOOKS_SHORTCODES_DIR', DTR_WORKBOOKS_PLUGIN_DIR . 'shortcodes/');
define('DTR_WORKBOOKS_ASSETS_URL', DTR_WORKBOOKS_PLUGIN_URL . 'assets/');
define('DTR_WORKBOOKS_LOG_DIR', DTR_WORKBOOKS_PLUGIN_DIR . 'logs/');


// Include Workbooks API if available
$workbooks_api = DTR_WORKBOOKS_PLUGIN_DIR . 'lib/workbooks_api.php';
if (file_exists($workbooks_api)) {
    require_once $workbooks_api;
} else {
    error_log('[DTR Workbooks] Workbooks API file not found: ' . $workbooks_api);
}

// Include helper functions (canonical file only)
$helper_class  = DTR_WORKBOOKS_INCLUDES_DIR . 'class-helper-functions.php';
if (file_exists($helper_class)) {
    require_once $helper_class;
} else {
    error_log('[DTR Workbooks] Helper functions file missing: ' . $helper_class);
}

// Include the admin employer sync wrapper class
$admin_employer_sync_class = DTR_WORKBOOKS_INCLUDES_DIR . 'class-admin-employer-sync.php';
if (file_exists($admin_employer_sync_class)) {
    require_once $admin_employer_sync_class;
} else {
    error_log('[DTR Workbooks] Admin Employer Sync class file missing: ' . $admin_employer_sync_class);
}

// Early define core logging helper so it exists before any init hooks fire
if (!function_exists('dtr_custom_log')) {
    function dtr_custom_log($message, $level = 'info') {
        // Ensure log directory constant exists
        if (!defined('DTR_WORKBOOKS_LOG_DIR')) return; // safety
        $log_dir = DTR_WORKBOOKS_LOG_DIR;
        if (!is_dir($log_dir)) {
            // Attempt to create directory quietly
            @wp_mkdir_p($log_dir);
        }
        $date = date('Y-m-d');
        $file = $log_dir . 'dtr-workbooks-' . $date . '.log';
        $entry = '[' . date('Y-m-d H:i:s') . "] [$level] " . (is_scalar($message) ? $message : print_r($message, true)) . "\n";
        @file_put_contents($file, $entry, FILE_APPEND);
    }
}

// Legacy file loading removed - all files now loaded through proper plugin class methods

/**
 * Main DTR Workbooks Integration Class
 */
class DTR_Workbooks_Integration {

    /**
     * Plugin instance
     *
     * @var DTR_Workbooks_Integration
     */
    private static $instance = null;

    /**
     * Plugin options
     *
     * @var array
     */
    private $options = [];

    /**
     * Debug mode status
     *
     * @var bool
     */
    private $debug_mode = false;

    /**
     * Missing dependencies
     *
     * @var array
     */
    private $missing_dependencies = [];

    /**
     * Get plugin instance
     *
     * @return DTR_Workbooks_Integration
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     *
     * @return void
     */
    private function init() {
        // Load options
        $this->options = get_option('dtr_workbooks_options', []);
        $this->debug_mode = !empty($this->options['debug_mode']);

        // Add text domain loading at the right time
        add_action('init', function() {
            // Load plugin text domains in the correct order
            load_plugin_textdomain('dtr-workbooks', false, dirname(plugin_basename(__FILE__)) . '/languages');

            if (defined('NINJA_FORMS_DIR_PATH')) {
                load_plugin_textdomain('ninja-forms', false, basename(NINJA_FORMS_DIR_PATH) . '/languages');
            }

            if (defined('ACF_PATH')) {
                load_plugin_textdomain('acf', false, basename(ACF_PATH) . '/languages');
            }
        }, 1);

        // Add Ninja Forms submission data filter
        add_filter('ninja_forms_submit_data', [$this, 'prepare_ninja_forms_submission'], 10, 1);

        // Fix for array merge issues in Ninja Forms
        add_filter('ninja_forms_submission_array_merge', [$this, 'fix_array_merge'], 10, 2);

        // Register hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);

        // Initialize plugin components
        add_action('plugins_loaded', [$this, 'load_plugin_components']);
        add_action('init', [$this, 'init_plugin']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // AJAX handlers
        add_action('wp_ajax_sspg_test_workbooks_connection', [$this, 'test_workbooks_connection']);
        add_action('wp_ajax_dtr_clear_logs', [$this, 'clear_logs']);
        add_action('wp_ajax_dtr_export_logs', [$this, 'export_logs']);
        add_action('wp_ajax_dtr_sync_test', [$this, 'sync_test']);
        add_action('wp_ajax_dtr_retry_submission', [$this, 'retry_submission']);
        add_action('wp_ajax_dtr_get_submission_details', [$this, 'get_submission_details']);
        
        // Note: Webinar registration AJAX handlers are now managed by the shortcode file
        // Legacy genomics key cleanup (cf_person_genomics_3744 -> cf_person_genomics_3774)
        add_action('wp_ajax_dtr_cleanup_genomics_meta', [$this, 'cleanup_genomics_meta']);
        
        // HTML form nonce endpoint - authenticated users
        add_action('wp_ajax_dtr_get_form_nonce', [$this, 'get_form_nonce']);
        // HTML form nonce endpoint - non-authenticated users
        add_action('wp_ajax_nopriv_dtr_get_form_nonce', [$this, 'get_form_nonce']);

        // Member Registration Test AJAX handlers
        add_action('wp_ajax_dtr_test_member_registration', [$this, 'handle_test_member_registration']);
        add_action('wp_ajax_dtr_sync_user_to_workbooks', [$this, 'handle_sync_user_to_workbooks']);
        add_action('wp_ajax_dtr_search_test_users', [$this, 'handle_search_test_users']);
        add_action('wp_ajax_dtr_load_user_meta', [$this, 'handle_load_user_meta']);
        add_action('wp_ajax_dtr_get_workbooks_fields', [$this, 'handle_get_workbooks_fields']);
        add_action('wp_ajax_dtr_regenerate_employer_data', [$this, 'handle_regenerate_employer_data']);

        // Custom logging
        add_action('init', [$this, 'setup_custom_logging']);

        // Scheduled events
        add_action('dtr_workbooks_cleanup', [$this, 'cleanup_old_logs']);

        // Initialize cleanup schedule
        if (!wp_next_scheduled('dtr_workbooks_cleanup')) {
            wp_schedule_event(time(), 'daily', 'dtr_workbooks_cleanup');
        }
    }

    /**
     * Load plugin components
     *
     * @return void
     */
    public function load_plugin_components() {
        // Always load employer sync endpoints early so public AJAX (ping, select2) works even if other deps missing
        $employer_sync_path = DTR_WORKBOOKS_INCLUDES_DIR . 'class-employer-sync.php';
        if (file_exists($employer_sync_path)) {
            require_once $employer_sync_path; // safe to include twice later via load_includes (require_once)
        }

        // Load AJAX handlers first (they don't depend on NF or ACF)
        $this->load_ajax_handlers();

        // Load shortcodes (they can work independently of other dependencies)
        $this->load_shortcodes();

        // Check dependencies (Ninja Forms / ACF) for the rest of the plugin
        if (!$this->check_dependencies()) {
            // Still provide notice in admin but keep lightweight endpoints active
            add_action('admin_notices', [$this, 'dependency_notice']);
            return; // Do not load heavy form handlers if deps missing
        }

        // Load required files (now that dependencies satisfied)
        $this->load_includes();

        // Initialize integrations
        $this->init_integrations();
    }

    /**
     * Load AJAX handlers - these work independently of NF/ACF dependencies
     *
     * @return void
     */
    private function load_ajax_handlers() {
        $ajax_file = DTR_WORKBOOKS_INCLUDES_DIR . 'ajax-employer-search.php';
        if (file_exists($ajax_file)) {
            require_once $ajax_file;
            error_log('DTR Plugin: Successfully loaded ajax-employer-search.php');
        } else {
            error_log('DTR Plugin: FAILED to load ajax-employer-search.php - file not found at ' . $ajax_file);
        }

        // Load HTML form handler - works independently of NF/ACF dependencies
        $html_form_file = DTR_WORKBOOKS_INCLUDES_DIR . 'form-handler-html-membership-registration.php';
        if (file_exists($html_form_file)) {
            require_once $html_form_file;
            error_log('DTR Plugin: Successfully loaded form-handler-html-membership-registration.php');
        } else {
            error_log('DTR Plugin: FAILED to load form-handler-html-membership-registration.php - file not found at ' . $html_form_file);
        }

        // Load media planner form handler - works independently of NF/ACF dependencies
        $media_planner_file = DTR_WORKBOOKS_INCLUDES_DIR . 'form-handler-media-planner.php';
        if (file_exists($media_planner_file)) {
            require_once $media_planner_file;
            error_log('DTR Plugin: Successfully loaded form-handler-media-planner.php');
        } else {
            error_log('DTR Plugin: FAILED to load form-handler-media-planner.php - file not found at ' . $media_planner_file);
        }
    }

    /**
     * Check plugin dependencies
     *
     * @return bool
     */
    private function check_dependencies() {
        $dependencies = [
            'ninja-forms/ninja-forms.php' => 'Ninja Forms'
        ];

        $missing = [];
        foreach ($dependencies as $plugin => $name) {
            if (!is_plugin_active($plugin)) {
                $missing[] = $name;
            }
        }

        // Check for ACF (either free or Pro version)
        $acf_active = is_plugin_active('advanced-custom-fields/acf.php') || 
                     is_plugin_active('advanced-custom-fields-pro/acf.php');
        
        if (!$acf_active) {
            $missing[] = 'Advanced Custom Fields';
        }

        if (!empty($missing)) {
            $this->missing_dependencies = $missing;
            return false;
        }

        return true;
    }

    /**
     * Load include files
     *
     * @return void
     */
    private function load_includes() {
        $includes = [
            // Core safety utilities (must load first)
            'class-array-merge-safety.php',
            // Ninja Forms submission override layer
            'class-form-submission-override.php',
            // Form handlers (live webinar registration - new clean implementation)
            'form-handler-live-webinar-registration.php',
            'form-handler-membership-registration.php',
            'form-handler-media-planner.php',
            // AJAX handlers
            'ajax-employer-search.php',
            // Submission processors
            'form-submission-processors-submission-fix.php',
            'form-submission-processors-ninjaform-hooks.php',
            // Support classes
            'class-acf-ninjaforms-merge.php',
            'class-employer-sync.php',
            'class-helper-functions.php',
            // Database storage helpers
            'submission-storage-helpers.php',
            // Note: Webinar form shortcode is loaded from shortcodes directory
        ];

        foreach ($includes as $file) {
            $file_path = DTR_WORKBOOKS_INCLUDES_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                error_log("DTR Plugin: Successfully loaded {$file}");
            } else {
                $this->log_error("Failed to load include file: {$file}");
                error_log("DTR Plugin: FAILED to load {$file} - file not found at {$file_path}");
            }
        }
    }

    /**
     * Load Shortcodes files
     *
     * @return void
     */
    private function load_shortcodes() {
        $shortcode_files = [
            'dtr-shortcodes.php',
            'dtr-my-account-details.php',
            'login-forgot-password-shortcode.php',
            'workbooks-employer-select.php',
            'membership-registration-shortcode.php',
            'media-planner-registration.php',
            'webinar-registration-form-shortcode.php',
            'lead-generation-form-shortcode.php',
        ];

        foreach ($shortcode_files as $file) {
            $file_path = DTR_WORKBOOKS_SHORTCODES_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                error_log('DTR: Loaded shortcode file: ' . $file);
            } else {
                error_log('DTR: Shortcode file not found: ' . $file_path);
                $this->log_error("Failed to load shortcode file: {$file}");
            }
        }
    }
    
    /**
     * Initialize integrations
     *
     * @return void
     */
    private function init_integrations() {
        error_log('DTR TEST LOG: init_integrations() called');
        // Initialize form processors and handlers
        if (function_exists('dtr_init_ninja_forms_hooks')) {
            error_log('DTR TEST LOG: dtr_init_ninja_forms_hooks function exists, calling it');
            dtr_init_ninja_forms_hooks();
        } else {
            error_log('DTR TEST LOG: dtr_init_ninja_forms_hooks function does NOT exist');
        }
        
        if (function_exists('dtr_init_gated_content_hooks')) {
            dtr_init_gated_content_hooks();
        }
        
        if (function_exists('dtr_init_acf_questions_hooks')) {
            dtr_init_acf_questions_hooks();
        }
    }
    
    /**
     * Plugin initialization
     *
     * @return void
     */
    public function init_plugin() {
        // Add proper text domain loading after init
        add_action('init', function() {
            load_plugin_textdomain('dtr-workbooks', false, dirname(plugin_basename(__FILE__)) . '/languages');
        });
        
        // Create log directory if it doesn't exist
        if (!is_dir(DTR_WORKBOOKS_LOG_DIR)) {
            wp_mkdir_p(DTR_WORKBOOKS_LOG_DIR);
            
            // Add .htaccess for security
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents(DTR_WORKBOOKS_LOG_DIR . '.htaccess', $htaccess_content);
            
            // Add index.php for security
            file_put_contents(DTR_WORKBOOKS_LOG_DIR . 'index.php', '<?php // Silence is golden');
        }
        
        // Setup custom capabilities
        $this->setup_capabilities();

        // One-time migration: move legacy admin log files into central logs directory
        $legacy_admin_dir = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/';
        if (is_dir($legacy_admin_dir)) {
            $candidates = ['connection-debug.log','update-debug.log'];
            foreach ($candidates as $fname) {
                $old_path = $legacy_admin_dir . $fname;
                $new_path = DTR_WORKBOOKS_LOG_DIR . $fname;
                if (file_exists($old_path) && !file_exists($new_path)) {
                    @rename($old_path, $new_path);
                    dtr_custom_log("Migrated legacy admin log {$fname} to logs/ directory", 'info');
                }
            }
        }
        
        // Add admin-post handler for admin webinar test form
        add_action('admin_post_dtr_admin_test_webinar', [$this, 'handle_admin_test_webinar']);
        
        // Add admin-post handler for admin lead generation test form
        add_action('admin_post_dtr_admin_test_lead_generation', [$this, 'handle_admin_test_lead_generation']);
    }
    
    /**
     * Admin initialization
     *
     * @return void
     */
    public function admin_init() {
        // Register settings
        register_setting('dtr_workbooks_options', 'dtr_workbooks_options', [
            'sanitize_callback' => [$this, 'validate_options'],
            'default' => [
                'api_url' => '',
                'api_key' => '',
                'debug_mode' => false,
                'enabled_forms' => [2, 15, 31, 'media_planner', 'membership_registration'],
                'form_dev_modes' => [],
                'api_timeout' => 30,
                'retry_attempts' => 3,
                'log_retention_days' => 30
            ]
        ]);
        
        // Add settings sections and fields
        $this->setup_admin_settings();
    }
    
    /**
     * Setup admin settings sections and fields
     *
     * @return void
     */
    private function setup_admin_settings() {
        // Workbooks API settings
        add_settings_section(
            'dtr_workbooks_api',
            __('Workbooks API Configuration', 'dtr-workbooks'),
            [$this, 'api_section_callback'],
            'dtr_workbooks'
        );
        
        add_settings_field(
            'api_url',
            __('API URL', 'dtr-workbooks'),
            [$this, 'api_url_field_callback'],
            'dtr_workbooks',
            'dtr_workbooks_api',
            [
                'label_for' => 'api_url',
                'description' => __('The URL of your Workbooks API endpoint', 'dtr-workbooks'),
                'placeholder' => 'https://russellpublishing-live.workbooks.com/'
            ]
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'dtr-workbooks'),
            [$this, 'api_key_field_callback'],
            'dtr_workbooks',
            'dtr_workbooks_api'
        );
        
        // Form settings
        add_settings_section(
            'dtr_workbooks_forms',
            __('Form Configuration', 'dtr-workbooks'),
            [$this, 'forms_section_callback'],
            'dtr_workbooks'
        );
        
        add_settings_field(
            'enabled_forms',
            __('Enabled Forms', 'dtr-workbooks'),
            [$this, 'enabled_forms_field_callback'],
            'dtr_workbooks',
            'dtr_workbooks_forms'
        );
        
        // Debug settings
        add_settings_section(
            'dtr_workbooks_debug',
            __('Debug & Logging', 'dtr-workbooks'),
            [$this, 'debug_section_callback'],
            'dtr_workbooks'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'dtr-workbooks'),
            [$this, 'debug_mode_field_callback'],
            'dtr_workbooks',
            'dtr_workbooks_debug'
        );
    }
    
    /**
     * Enqueue frontend scripts and styles
     *
     * @return void
     */
    public function enqueue_scripts() {
        $post_name = get_post_field('post_name');
        $post_title = get_the_title();
        $post_id = get_the_ID();
        $post_type = get_post_type();
        error_log('DTR TEST LOG: enqueue_scripts called on page: "' . $post_title . '" (slug: "' . $post_name . '", ID: ' . $post_id . ', type: ' . $post_type . ')');

        // Check if this is a single webinar page
        $is_single_webinar = is_singular('webinar') || $post_type === 'webinar' || has_shortcode(get_post()->post_content ?? '', 'webinar_registration');
        
        // Enqueue frontend styles
        wp_enqueue_style(
            'dtr-workbooks-frontend',
            DTR_WORKBOOKS_ASSETS_URL . 'css/frontend.css',
            [],
            DTR_WORKBOOKS_VERSION
        );
        wp_enqueue_style(
            'dtr-workbooks-buttons',
            DTR_WORKBOOKS_ASSETS_URL . 'css/global-buttons.css',
            [],
            DTR_WORKBOOKS_VERSION
        );
        
        // Enqueue frontend.js for webinar pages and other relevant pages
        if ($is_single_webinar || in_array($post_type, array('webinar', 'post', 'page'))) {
            // Ensure Ninja Forms core dependencies are loaded first
            if (defined('NINJA_FORMS_VERSION')) {
                wp_enqueue_script('backbone');
                wp_enqueue_script('underscore');
                wp_enqueue_script('nf-front-end', 
                    NINJA_FORMS_URL . 'assets/js/min/front-end.min.js',
                    array('jquery', 'backbone', 'underscore', 'jquery-form'),
                    NINJA_FORMS_VERSION
                );
            }

            wp_enqueue_script(
                'dtr-workbooks-frontend',
                DTR_WORKBOOKS_ASSETS_URL . 'js/frontend.js',
                array('jquery', 'backbone', 'underscore', 'nf-front-end'),
                DTR_WORKBOOKS_VERSION,
                true
            );

            // Localize script for webinar form AJAX
            wp_localize_script('dtr-workbooks-frontend', 'dtrWebinarAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dtr_webinar_nonce'),
                'debug_mode' => $this->debug_mode,
                'current_user_id' => get_current_user_id(),
                'is_user_logged_in' => is_user_logged_in()
            ));
        }
        
        // Frontend JS with file modification time for cache-busting
        
        /* 
        // Localize script for AJAX
        wp_localize_script('dtr-workbooks-frontend', 'dtr_workbooks_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dtr_workbooks_nonce'),
            'debug_mode' => $this->debug_mode
        ]); */
        

        // Conditional enqueue for employer select (registration forms)
        // Check for membership pages or pages with gravity forms
        $is_membership_page = is_page(array('free-membership','membership','register','membership-sign-up'));
        $has_gravity_form = has_shortcode(get_post()->post_content ?? '', 'gravityform');
        $should_enqueue = $is_membership_page || $has_gravity_form || isset($_GET['dtr_reg_debug']);
        
        error_log('DTR TEST LOG: Should enqueue scripts? ' . ($should_enqueue ? 'YES' : 'NO') . ' - Page check: ' . ($is_membership_page ? 'MATCH' : 'NO MATCH') . ', Has GF: ' . ($has_gravity_form ? 'YES' : 'NO'));
        if ($should_enqueue) {
            // Ensure Select2 (try using WP core registered if available or fallback)
            if (!wp_script_is('select2', 'registered')) {
                wp_register_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
                wp_register_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
            }
            wp_enqueue_script('select2');
            wp_enqueue_style('select2');

            // Use file modification time for more aggressive cache busting of employer field logic
            $employers_js_path = DTR_WORKBOOKS_PLUGIN_DIR . 'js/ninjaform-employers-field.js';
            $employers_js_ver = file_exists($employers_js_path) ? filemtime($employers_js_path) : DTR_WORKBOOKS_VERSION;
            if (isset($_GET['dtr_employers_debug'])) { // force unique version when debugging
                $employers_js_ver = time();
            }
            wp_enqueue_script(
                'dtr-nf-employers-field',
                DTR_WORKBOOKS_PLUGIN_URL . 'js/ninjaform-employers-field.js',
                ['jquery','select2'],
                $employers_js_ver,
                true
            );

            wp_localize_script('dtr-nf-employers-field','workbooks_ajax',[
                'ajax_url' => admin_url('admin-ajax.php'),
                // Use the same nonce action string that server endpoints verify ('workbooks_nonce')
                'nonce' => wp_create_nonce('workbooks_nonce'),
                'plugin_url' => DTR_WORKBOOKS_PLUGIN_URL,
                'debug_mode' => $this->debug_mode
            ]);

            // Gravity Forms employer field support
            $gf_employers_js_path = DTR_WORKBOOKS_PLUGIN_DIR . 'js/gravityforms-employers-field.js';
            $gf_employers_js_ver = file_exists($gf_employers_js_path) ? filemtime($gf_employers_js_path) : DTR_WORKBOOKS_VERSION;
            if (isset($_GET['dtr_employers_debug'])) {
                $gf_employers_js_ver = time();
            }
            error_log('DTR TEST LOG: Enqueuing Gravity Forms employer script - version: ' . $gf_employers_js_ver);
            wp_enqueue_script(
                'dtr-gf-employers-field',
                DTR_WORKBOOKS_PLUGIN_URL . 'js/gravityforms-employers-field.js',
                ['jquery'],
                $gf_employers_js_ver,
                true
            );

            wp_localize_script('dtr-gf-employers-field','workbooks_ajax',[
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('workbooks_nonce'),
                'plugin_url' => DTR_WORKBOOKS_PLUGIN_URL,
                'debug_mode' => $this->debug_mode
            ]);
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Page hook
     * @return void
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'dtr-workbooks') === false) {
            return;
        }

        wp_enqueue_style(
            'dtr-workbooks-admin',
            DTR_WORKBOOKS_ASSETS_URL . 'css/admin.css',
            [],
            DTR_WORKBOOKS_VERSION
        );

        wp_enqueue_script(
            'dtr-workbooks-admin',
            DTR_WORKBOOKS_ASSETS_URL . 'js/admin.js',
            ['jquery', 'jquery-ui-tabs', 'jquery-ui-dialog'],
            DTR_WORKBOOKS_VERSION,
            true
        );

        wp_localize_script('dtr-workbooks-admin', 'dtr_workbooks_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('workbooks_nonce'),
            'strings' => [
                'testing_connection' => __('Testing connection...', 'dtr-workbooks'),
                'connection_success' => __('Connection successful!', 'dtr-workbooks'),
                'connection_failed' => __('Connection failed!', 'dtr-workbooks'),
                'clearing_logs' => __('Clearing logs...', 'dtr-workbooks'),
                'logs_cleared' => __('Logs cleared successfully!', 'dtr-workbooks'),
                'cleanup_running' => __('Running genomics key cleanup...', 'dtr-workbooks'),
                'cleanup_done' => __('Genomics key cleanup completed.', 'dtr-workbooks')
            ]
        ]);

        // Enqueue admin-employers-sync.js only on Employer Sync page
        if ($hook === 'toplevel_page_dtr-workbooks-employer-sync' || $hook === 'dtr-workbooks_page_dtr-workbooks-employer-sync') {
            wp_enqueue_script(
                'admin-employers-sync',
                DTR_WORKBOOKS_ASSETS_URL . 'js/admin-employers-sync.js',
                ['jquery'],
                DTR_WORKBOOKS_VERSION,
                true
            );
        }
    }
    
    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('DTR Workbooks', 'dtr-workbooks'),
            __('DTR Workbooks', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks',
            [$this, 'admin_page'],
            'dashicons-database-import',
            30
        );

    // (No explicit 'Welcome' submenu item; top-level menu opens the Welcome tab)

        // API Settings (new separate settings form page)
        add_submenu_page(
            'dtr-workbooks',
            __('API Settings', 'dtr-workbooks'),
            __('API Settings', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-api-settings',
            [$this, 'admin_api_settings_page']
        );

        // Person Record (direct link to testing tab / person record tools)
        add_submenu_page(
            'dtr-workbooks',
            __('Person Record', 'dtr-workbooks'),
            __('Person Record', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-person',
            [$this, 'admin_person_record_page']
        );

        // Registered Users listing
        add_submenu_page(
            'dtr-workbooks',
            __('Registered Users', 'dtr-workbooks'),
            __('Registered Users', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-users',
            [$this, 'admin_registered_users_page']
        );

        // Form Loader
        add_submenu_page(
            'dtr-workbooks',
            __('Form Loader', 'dtr-workbooks'),
            __('Form Loader', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-form-loader',
            [$this, 'admin_form_loader_page']
        );

        // Employer Sync page
        add_submenu_page(
            'dtr-workbooks',
            __('Employer Sync', 'dtr-workbooks'),
            __('Employer Sync', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-employer-sync',
            [$this, 'admin_employer_sync_page']
        );

        // Gated Content overview
        add_submenu_page(
            'dtr-workbooks',
            __('Gated Content', 'dtr-workbooks'),
            __('Gated Content', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-gated',
            [$this, 'admin_gated_content_page']
        );

        // TOI & AOI Mapping display
        add_submenu_page(
            'dtr-workbooks',
            __('TOI & AOI Mapping', 'dtr-workbooks'),
            __('TOI & AOI Mapping', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-mapping',
            [$this, 'admin_toi_aoi_mapping_page']
        );

        // Webinar Registration page
        add_submenu_page(
            'dtr-workbooks',
            __('Webinar Registration', 'dtr-workbooks'),
            __('Webinar Registration', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-webinar-test',
            [$this, 'admin_webinar_test_page']
        );

        // Lead Registration page
        add_submenu_page(
            'dtr-workbooks',
            __('Lead Registration', 'dtr-workbooks'),
            __('Lead Registration', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-lead-generation-test',
            [$this, 'admin_lead_generation_test_page']
        );

        // Member Registration Test page
        add_submenu_page(
            'dtr-workbooks',
            __('Member Registration Test', 'dtr-workbooks'),
            __('Member Registration Test', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-member-test',
            [$this, 'admin_member_registration_test_page']
        );

        // Registered Events page
        add_submenu_page(
            'dtr-workbooks',
            __('Registered Events', 'dtr-workbooks'),
            __('Registered Events', 'dtr-workbooks'),
            'manage_options',
            'dtr-workbooks-registered-events',
            [$this, 'admin_registered_events_page']
        );
    }

    /**
     * API Settings page (separate from Welcome)
     */
    public function admin_api_settings_page() {
        echo '<div class="wrap plugin-admin-content">';
        echo '<h1>'.esc_html__('API Settings','dtr-workbooks').'</h1>';
        $api_settings_file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/content-api-settings.php';
        if (file_exists($api_settings_file)) {
            // Include settings form markup
            include $api_settings_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
        } else {
            echo '<p>'.esc_html__('API Settings content file missing (admin/content-api-settings.php).','dtr-workbooks').'</p>';
        }
        // Provide minimal inline JS for Test Connection button (reused from main page)
        ?>
        <script>
        jQuery(function($){
            var ajaxurl = dtr_workbooks_admin.ajax_url; var nonce = dtr_workbooks_admin.nonce;
            $('#test-connection').off('click.dtr').on('click.dtr', function(){
                var $button = $(this); var $result = $('#connection-result');
                $button.prop('disabled', true); $result.html('<span style="color:#666;">Testing connection...</span>');
                $.ajax({
                    url: ajaxurl, type: 'POST', data: {action:'sspg_test_workbooks_connection', nonce: nonce}, dataType:'json'
                }).done(function(resp){
                    if(resp && resp.success){
                        $result.html('<span style="color:green;">✓ '+(resp.data && resp.data.message ? resp.data.message : 'Connection successful')+'</span>');
                    } else {
                        var msg = resp && resp.data && resp.data.message ? resp.data.message : 'Connection test failed';
                        $result.html('<span style="color:red;">✗ '+msg+'</span>');
                    }
                }).fail(function(){
                    $result.html('<span style="color:red;">✗ Connection test failed</span>');
                }).always(function(){
                    $button.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
        echo '</div>';
    }
    
    /**
     * Main admin page
     *
     * @return void
     */
    public function admin_page() {
        ?>
        <div class="wrap plugin-admin-content">
            <h1><?php _e('DTR Workbooks CRM Integration', 'dtr-workbooks'); ?></h1>
            <div class="nav-tab-wrapper">
                <a href="#welcome" class="nav-tab nav-tab-active"><?php _e('Welcome', 'dtr-workbooks'); ?></a>
                <a href="#knowledge-base" class="nav-tab"><?php _e('Knowledge Base', 'dtr-workbooks'); ?></a>
                <a href="#test-webinar" class="nav-tab"><?php _e('Test Webinar Registration', 'dtr-workbooks'); ?></a>
            </div>
            <div id="dtr-toi-aoi-matrix" style="display:none;"><?php echo json_encode(function_exists('dtr_get_toi_to_aoi_matrix') ? dtr_get_toi_to_aoi_matrix() : new stdClass()); ?></div>
            <div id="welcome" class="tab-content active">
                <h2>Welcome to the DTR Workbooks Integration System</h2>
                <p>To confirm that everything is working correctly, you need to complete these steps always</p>
                <ol>
                    <li>Test API Connection</li>
                    <li>My Account - Update Profile Details</li>
                    <li>My Account - Update Marketing Preferences</li>
                    <li>My Account - Update Topics of Interest</li>
                </ol>
                <p>If all is working correctly, then you can rest assured that you are in safe hands and all systems are a go…</p>
            </div>
            <!-- Knowledge Base -->
            <div id="knowledge-base" class="tab-content" style="margin-top:0; display:none;">
                <?php
                $kb_file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/content-knowledge-base.php';
                if (file_exists($kb_file)) {
                    ob_start();
                    include $kb_file;
                    $kb_html = ob_get_clean();
                    if (strpos($kb_html, '<body') !== false && preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $kb_html, $m)) {
                        $kb_html = $m[1];
                    }
                    echo $kb_html;
                } else {
                    echo '<p>'.esc_html__('Knowledge Base file missing: admin/content-knowledge-base.php','dtr-workbooks').'</p>';
                }
                ?>
            </div>
            <!-- Test Webinar Registration -->
            <div id="test-webinar" class="tab-content" style="margin-top:0; display:none;">
                <?php
                $test_file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/content-test-webinar.php';
                if (file_exists($test_file)) {
                    include $test_file;
                } else {
                    echo '<p>'.esc_html__('Test Webinar file missing: admin/content-test-webinar.php','dtr-workbooks').'</p>';
                }
                ?>
            </div>
        </div>

        <style>
        #knowledge-base, #test-webinar {padding-top:0 !important;}
        .tab-content { padding-top: 20px; }
        .test-section { margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; }
        .test-section h3 { margin-top: 0; }
        .notice { margin: 15px 0; }
        section#kb.kb details.kb-item {
        font-size:15px !important;
        background:#eee !important;
        display:flex !important;
        gap:10px !important;
        flex-direction:column !important;
        padding:11px 20px 3px !important;
        margin-bottom:15px !important;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching for new tab
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href').substring(1);
                setActiveTab(target);
            });
            // === AOI Mapping Debug (admin only) ===
            let dtrToiToAoiMatrix = {};
            try {
                dtrToiToAoiMatrix = JSON.parse(document.getElementById('dtr-toi-aoi-matrix').textContent) || {};
            } catch(e) {
                console.warn('[DTR AOI MATRIX] JSON parse failed', e);
                dtrToiToAoiMatrix = {};
            }
            // Raw matrix before transformation
            console.log('[DTR AOI MATRIX RAW]', dtrToiToAoiMatrix);
            // Transform PHP associative structure (values can be map of {aoi:1}) to flat arrays of AOI keys
            Object.keys(dtrToiToAoiMatrix).forEach(k => {
                if (!Array.isArray(dtrToiToAoiMatrix[k])) {
                    dtrToiToAoiMatrix[k] = Object.keys(dtrToiToAoiMatrix[k] || {});
                }
            });
            // Per-key counts summary to quickly spot empties
            const perKeyCounts = {};
            Object.keys(dtrToiToAoiMatrix).forEach(k => { perKeyCounts[k] = dtrToiToAoiMatrix[k].length; });
            console.log('[DTR AOI MATRIX SUMMARY] counts per TOI key:', perKeyCounts);
            // Immediate visibility into Drugs & Therapies mapping content
            console.log('[DTR AOI MATRIX] drugs & therapies entry (post-transform):', dtrToiToAoiMatrix['cf_person_drugs_therapies']);
            const allToiSelectors = Object.keys(dtrToiToAoiMatrix).map(k => 'input[type="checkbox"][name="'+k+'"]').join(',');
            function recomputeAoiFromSelections(){
                const selected = [];
                Object.keys(dtrToiToAoiMatrix).forEach(k => { if ($('input[name="'+k+'"]').is(':checked')) selected.push(k); });
                const aoi = {};
                selected.forEach(k => { (dtrToiToAoiMatrix[k]||[]).forEach(a => { aoi[a] = 1; }); });
                const aoiList = Object.keys(aoi);
                return {selected, aoi: aoiList, aoiCount: aoiList.length};
            }
            $(document).on('change', allToiSelectors, function(){
                const field = this.name;
                const isChecked = $(this).is(':checked');
                if (dtrToiToAoiMatrix[field]) {
                    console.log('[DTR AOI DEBUG] TOI', field, isChecked ? 'selected' : 'deselected', 'affects', dtrToiToAoiMatrix[field].length, 'AOI:', dtrToiToAoiMatrix[field]);
                } else {
                    console.warn('[DTR AOI DEBUG] TOI', field, 'had no matrix entry');
                }
                const aggregate = recomputeAoiFromSelections();
                console.log('[DTR AOI DEBUG] Aggregate AOI ('+aggregate.aoiCount+') from', aggregate.selected.length, 'TOI:', aggregate.selected, '=>', aggregate.aoi);
            });
            // Focused logging for Drugs & Therapies to diagnose mapping delivery
            $(document).on('change','input[type="checkbox"][name="cf_person_drugs_therapies"]', function(){
                const on = $(this).is(':checked');
                const expected = dtrToiToAoiMatrix['cf_person_drugs_therapies'] || [];
                const agg = recomputeAoiFromSelections();
                console.log('[DTR AOI FOCUS][Drugs & Therapies] toggled', on ? 'ON':'OFF', '\nExpected AOI ('+expected.length+'):', expected, '\nAggregate AOI Now ('+agg.aoiCount+'):', agg.aoi);
            });
            // Initial log on load if any pre-checked
            setTimeout(()=>{
                const initAgg = recomputeAoiFromSelections();
                if (initAgg.selected.length) {
                    console.log('[DTR AOI DEBUG] Initial TOI -> AOI mapping. TOI selected:', initAgg.selected, 'Derived AOI ('+initAgg.aoiCount+'):', initAgg.aoi);
                } else {
                    console.log('[DTR AOI DEBUG] No TOI initially selected. Matrix keys:', Object.keys(dtrToiToAoiMatrix).length);
                }
            },100);
            // === /AOI Mapping Debug ===
            // Toggle Workbooks API Fields table
            $('#toggle-workbooks-fields').on('click', function(e) {
                e.preventDefault();
                var $table = $('#workbooks-fields-table');
                if ($table.is(':visible')) {
                    $table.slideUp(150);
                    $(this).text('Show Workbooks API Fields for this User');
                } else {
                    $table.slideDown(150);
                    $(this).text('Hide Workbooks API Fields for this User');
                }
            });
            // Initialize variables from localized script
            var ajaxurl = dtr_workbooks_admin.ajax_url;
            var nonce = dtr_workbooks_admin.nonce;
            
            // Get active tab from URL hash or localStorage
            function getActiveTab() {
                var hash = window.location.hash || localStorage.getItem('dtr_workbooks_active_tab') || '#welcome';
                return hash.substring(1);
            }

            // Set active tab
            function setActiveTab(tab) {
                $('.nav-tab').removeClass('nav-tab-active');
                $('a[href="#' + tab + '"]').addClass('nav-tab-active');
                $('.tab-content').hide();
                $('#' + tab).show();
                localStorage.setItem('dtr_workbooks_active_tab', '#' + tab);
                window.location.hash = '#' + tab;
            }
            
            // Tab switching
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href').substring(1);
                setActiveTab(target);
            });

            // Set initial active tab
            setActiveTab(getActiveTab());

            // Handle person record form submission
            $('#workbooks_update_user_form').submit(function(e) {
                e.preventDefault();
                var $form = $(this);
                var $submitButton = $('#submit-person-record');
                
                $submitButton.prop('disabled', true);
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        // Try to detect a stale lock_version error and reload the form if needed
                        var mustReload = false;
                        try {
                            // If response is JSON, check for mustReloadForm
                            if (typeof response === 'object' && response !== null && response.mustReloadForm) {
                                mustReload = true;
                            } else if (typeof response === 'string' && response.indexOf('mustReloadForm') !== -1) {
                                mustReload = true;
                            }
                        } catch (e) {}

                        if (mustReload) {
                            // Reload the page or the #testing tab to get the latest lock_version and data
                            location.reload();
                            return;
                        }

                        // The response will contain the entire page HTML
                        // Extract just the testing tab content and notices
                        var $responseHtml = $(response);
                        var $newContent = $responseHtml.find('#testing');
                        var $notices = $responseHtml.find('.notice');

                        // Update the tab content
                        $('#testing').html($newContent.html());

                        // Show notices at the top of the form
                        if ($notices.length) {
                            $notices.insertBefore('#workbooks_update_user_form');
                        }

                        // Ensure we stay on testing tab
                        setActiveTab('testing');
                    },
                    error: function() {
                        $('<div class="notice notice-error is-dismissible"><p>An error occurred while updating the record.</p></div>')
                            .insertBefore('#workbooks_update_user_form');
                    },
                    complete: function() {
                        $submitButton.prop('disabled', false);
                    }
                });
            });

            // Test Connection
            $('#test-connection').click(function() {
                var $button = $(this);
                var $result = $('#connection-result');
                
                // Disable button and show loading state
                $button.prop('disabled', true);
                $result.html('<span style="color:#666;">Testing connection...</span>');
                
                // Make AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sspg_test_workbooks_connection',
                        nonce: nonce
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color:red;">✗ ' + (response.data.message || 'Connection test failed') + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        $result.html('<span style="color:red;">✗ Connection test failed: ' + error + '</span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                })
                .done(function(response) {
                    if (response.success) {
                        $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                    }
                })
                .fail(function() {
                    $result.html('<span style="color:red;">✗ Connection test failed</span>');
                })
                .always(function() {
                    $button.prop('disabled', false);
                });
            });

            // Test Sync
            $('#test-sync').click(function() {
                var $button = $(this);
                var $result = $('#sync-result');
                
                $button.prop('disabled', true);
                $result.html('<span style="color:#666;">Testing sync...</span>');
                
                $.post(ajaxurl, {
                    action: 'dtr_sync_test',
                    nonce: dtr_workbooks_admin.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                    }
                })
                .fail(function() {
                    $result.html('<span style="color:red;">✗ Sync test failed</span>');
                })
                .always(function() {
                    $button.prop('disabled', false);
                });
            });

            // Genomics key cleanup
            $('#run-genomics-cleanup').on('click', function(e){
                e.preventDefault();
                var $btn = $(this); var $res = $('#genomics-cleanup-result');
                $btn.prop('disabled', true); $res.text(dtr_workbooks_admin.strings.cleanup_running);
                $.post(ajaxurl, {action:'dtr_cleanup_genomics_meta', nonce: nonce}, function(resp){
                    if(resp && resp.success){
                        $res.text(dtr_workbooks_admin.strings.cleanup_done + ' Migrated: '+resp.data.migrated+' Deleted old: '+resp.data.deleted);
                    } else {
                        $res.text('Cleanup failed: '+(resp && resp.data ? resp.data : 'unknown error'));
                    }
                }).fail(function(){
                    $res.text('Cleanup AJAX error');
                }).always(function(){
                    $btn.prop('disabled', false);
                });
            });
        });
        </script>
    <?php if (function_exists('dtr_get_toi_to_aoi_matrix')): ?>
    <?php 
        // Prepare AOI matrix JSON for inline consumption (avoid esc_html which broke JSON parsing)
        $dtr_aoi_matrix_export = dtr_get_toi_to_aoi_matrix();
        if (function_exists('dtr_admin_log')) {
            dtr_admin_log('[AOI MATRIX EXPORT] keys=' . implode(',', array_keys($dtr_aoi_matrix_export)) . ' count=' . count($dtr_aoi_matrix_export));
        }
    ?>
    <script type="application/json" id="dtr-toi-aoi-matrix"><?php echo wp_json_encode($dtr_aoi_matrix_export, JSON_UNESCAPED_SLASHES); ?></script>
    <?php endif; ?>
        <?php
    }

    /**
     * AJAX: Cleanup legacy genomics meta key across users
     */
    public function cleanup_genomics_meta() {
        check_ajax_referer('workbooks_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        global $wpdb;
        $old_key = 'cf_person_genomics_3744';
        $new_key = 'cf_person_genomics_3774';
        // Find users with old key set
        $rows = $wpdb->get_results($wpdb->prepare("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $old_key));
        $migrated = 0; $deleted = 0;
        foreach ($rows as $row) {
            $has_new = get_user_meta($row->user_id, $new_key, true);
            if (!$has_new && intval($row->meta_value) === 1) {
                update_user_meta($row->user_id, $new_key, 1);
                $migrated++;
            }
            delete_user_meta($row->user_id, $old_key);
            $deleted++;
        }
        wp_send_json_success(['migrated' => $migrated, 'deleted' => $deleted]);
    }

    /**
     * Person Record page (redirects/forces focus to Testing tab UI within main page construct)
     */
    public function admin_person_record_page() {
        $file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/content-person-record.php';
        echo '<div class="wrap plugin-admin-content">';
        echo '<h1>'.esc_html__('Person Record','dtr-workbooks').'</h1>';
        echo '<h2>View & Update any Registered Users WP Meta Data with Sync to Workbooks Functionality</h2>';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<p>'.esc_html__('The file admin/content-person-record.php was not found.','dtr-workbooks').'</p>';
        }
        echo '</div>';
    }

    /**
     * Registered Users page (lightweight listing with Workbooks ID/meta snapshot)
     */
    public function admin_registered_users_page() {
        $file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/content-member-registrations.php';
        echo '<div class="wrap plugin-admin-content"><h1>'.esc_html__('Registered Users','dtr-workbooks').'</h1>';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<p>'.esc_html__('The file admin/content-member-registrations.php was not found.','dtr-workbooks').'</p>';
        }
        echo '</div>';
    }

    /**
     * Form Loader page
     */
    public function admin_form_loader_page() {
        $file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/ninja-forms-loader.php';
        echo '<div class="wrap plugin-admin-content"><h1>'.esc_html__('Form Loader','dtr-workbooks').'</h1>';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<p>'.esc_html__('The file admin/ninja-forms-loader.php was not found.','dtr-workbooks').'</p>';
        }
        echo '</div>';
    }

    /**
     * Employer Sync page (admin menu)
     */
    public function admin_employer_sync_page() {
        $file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/content-employer-sync.php';
        echo '<div class="wrap plugin-admin-content"><h1>'.esc_html__('Employer Sync','dtr-workbooks').'</h1>';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<p>'.esc_html__('The file admin/content-employer-sync.php was not found.','dtr-workbooks').'</p>';
        }
        echo '</div>';
    }

    /**
     * Gated Content page
     */
    public function admin_gated_content_page() {
        $file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/gated-content.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap plugin-admin-content">';
            echo '<h1>'.esc_html__('Gated Content','dtr-workbooks').'</h1>';
            echo '<p>'.esc_html__('The file admin/gated-content.php was not found.','dtr-workbooks').'</p>';
            echo '</div>';
        }
    }

    /**
     * TOI & AOI Mapping page (displays matrix for transparency)
     */
    public function admin_toi_aoi_mapping_page() {
        echo '<div class="wrap plugin-admin-content"><h1>'.esc_html__('TOI & AOI Mapping','dtr-workbooks').'</h1>';
        if (!function_exists('dtr_get_toi_to_aoi_matrix')) { echo '<p>'.esc_html__('Mapping function not available.','dtr-workbooks').'</p></div>'; return; }
        $matrix = dtr_get_toi_to_aoi_matrix();
        if (empty($matrix)) { echo '<p>'.esc_html__('No mapping entries defined.','dtr-workbooks').'</p></div>'; return; }
        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr><th>'.esc_html__('TOI Field','dtr-workbooks').'</th><th>'.esc_html__('Mapped AOI Fields','dtr-workbooks').'</th><th>'.esc_html__('Count','dtr-workbooks').'</th></tr></thead><tbody>';
        foreach ($matrix as $toi => $aoiSet) {
            $aoiKeys = array_keys($aoiSet);
            echo '<tr>'
                .'<td><code>'.esc_html($toi).'</code></td>'
                .'<td style="font-family:monospace;white-space:normal;">'.esc_html(implode(', ', $aoiKeys)).'</td>'
                .'<td>'.count($aoiKeys).'</td>'
                .'</tr>';
        }
        echo '</tbody></table>';
        echo '<p style="margin-top:12px;">'.esc_html__('Matrix source: dtr_get_toi_to_aoi_matrix()','dtr-workbooks').'</p>';
        echo '</div>';
    }

    /**
     * Form ID 2 Testing page
     */
    /**
     * Webinar Registration Test page
     *
     * @return void
     */
    public function admin_webinar_test_page() {
        $test_page_file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/webinar-form-test.php';
        if (file_exists($test_page_file)) {
            include $test_page_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>'.esc_html__('Webinar Registration Test','dtr-workbooks').'</h1>';
            echo '<p>'.esc_html__('Test content file missing (admin/webinar-form-test.php).','dtr-workbooks').'</p>';
            echo '</div>';
        }
    }

    /**
     * Lead Generation Test page
     *
     * @return void
     */
    public function admin_lead_generation_test_page() {
        $test_page_file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/lead-generation-form-test.php';
        if (file_exists($test_page_file)) {
            include $test_page_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>'.esc_html__('Lead Generation Test','dtr-workbooks').'</h1>';
            echo '<p>'.esc_html__('Test content file missing (admin/lead-generation-form-test.php).','dtr-workbooks').'</p>';
            echo '</div>';
        }
    }

    /**
     * Member Registration Test page
     *
     * @return void
     */
    public function admin_member_registration_test_page() {
        $test_page_file = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/member-registration-test.php';
        if (file_exists($test_page_file)) {
            include $test_page_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>'.esc_html__('Member Registration Test','dtr-workbooks').'</h1>';
            echo '<p>'.esc_html__('Test content file missing (admin/member-registration-test.php).','dtr-workbooks').'</p>';
            echo '</div>';
        }
    }
    
    /**
     * Logs page
     *
     * @return void
     */
    public function logs_page() {
        $log_files = $this->get_log_files();
        ?>
        <div class="wrap plugin-admin-content">
            <h1><?php _e('DTR Workbooks Logs', 'dtr-workbooks'); ?></h1>
            
            <div class="log-viewer">
                <select id="log-selector">
                    <option value=""><?php _e('Select a log file', 'dtr-workbooks'); ?></option>
                    <?php foreach ($log_files as $file => $info): ?>
                        <option value="<?php echo esc_attr($file); ?>">
                            <?php echo esc_html($file); ?> (<?php echo esc_html($info['size_formatted']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="button" id="view-log" class="button">
                    <?php _e('View Log', 'dtr-workbooks'); ?>
                </button>
                
                <button type="button" id="clear-logs" class="button">
                    <?php _e('Clear All Logs', 'dtr-workbooks'); ?>
                </button>
            </div>
            
            <div id="log-content" style="margin-top: 20px;"></div>
        </div>
        <?php
    }
    
    /**
     * System status page
     *
     * @return void
     */
    public function status_page() {
        $status = $this->get_system_status();
        ?>
        <div class="wrap plugin-admin-content">
            <h1><?php _e('DTR Workbooks System Status', 'dtr-workbooks'); ?></h1>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Component', 'dtr-workbooks'); ?></th>
                        <th><?php _e('Status', 'dtr-workbooks'); ?></th>
                        <th><?php _e('Details', 'dtr-workbooks'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Plugin Version', 'dtr-workbooks'); ?></td>
                        <td><?php echo esc_html(DTR_WORKBOOKS_VERSION); ?></td>
                        <td><?php _e('Current plugin version', 'dtr-workbooks'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('WordPress Version', 'dtr-workbooks'); ?></td>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        <td><?php _e('Current WordPress version', 'dtr-workbooks'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('PHP Version', 'dtr-workbooks'); ?></td>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                        <td><?php echo version_compare(PHP_VERSION, '7.4', '>=') ? __('Compatible', 'dtr-workbooks') : __('Needs update', 'dtr-workbooks'); ?></td>
                    </tr>
                    <?php foreach ($status['dependencies'] as $dependency => $info): ?>
                        <tr>
                            <td><?php echo esc_html($info['name']); ?></td>
                            <td>
                                <span class="status-<?php echo $info['active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $info['active'] ? __('Active', 'dtr-workbooks') : __('Inactive', 'dtr-workbooks'); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($info['version'] ?: __('Version unknown', 'dtr-workbooks')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Setup custom logging
     *
     * @return void
     */
    public function setup_custom_logging() {
        if (!function_exists('dtr_custom_log')) {
            /**
             * Custom logging function
             *
             * @param string $message Log message
             * @param string $level Log level
             * @return void
             */
            function dtr_custom_log($message, $level = 'info') {
                $log_file = DTR_WORKBOOKS_LOG_DIR . 'dtr-workbooks-' . date('Y-m-d') . '.log';
                $timestamp = current_time('Y-m-d H:i:s');
                $log_entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
                
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
        }
    }
    
    /**
     * Setup custom capabilities
     *
     * @return void
     */
    private function setup_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_dtr_workbooks');
            $role->add_cap('view_dtr_workbooks_logs');
        }
    }
    
    /**
     * AJAX handler for providing form nonce for HTML forms
     *
     * @return void
     */
    public function get_form_nonce() {
        wp_send_json_success([
            'nonce' => wp_create_nonce('dtr_html_form_submit')
        ]);
    }

    /**
     * AJAX handler for test member registration
     *
     * @return void
     */
    public function handle_test_member_registration() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dtr_member_test_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Simulate form data for test registration
        $test_data = [
            'action' => 'dtr_html_form_submit',
            'title' => sanitize_text_field($_POST['title'] ?? 'Mr.'),
            'firstName' => sanitize_text_field($_POST['firstName'] ?? 'Test'),
            'lastName' => sanitize_text_field($_POST['lastName'] ?? 'User'),
            'email' => sanitize_email($_POST['email'] ?? 'testuser' . time() . '@example.com'),
            'password' => wp_generate_password(12, false),
            'employer' => sanitize_text_field($_POST['employer'] ?? 'Test Company Ltd'),
            'phone' => sanitize_text_field($_POST['phone'] ?? '123-456-7890'),
            'jobTitle' => sanitize_text_field($_POST['jobTitle'] ?? 'Test Manager'),
            'country' => sanitize_text_field($_POST['country'] ?? 'United Kingdom'),
            'city' => sanitize_text_field($_POST['city'] ?? 'Test City'),
            'postcode' => sanitize_text_field($_POST['postcode'] ?? 'TE5T 1NG'),
            'newsletter' => '1',
            'events' => '1',
            'thirdParty' => '0',
            'webinar' => '0',
            'business' => '1',
            'diseases' => '0',
            'drugs' => '1',
            'genomics' => '0',
            'research' => '0',
            'technology' => '1',
            'tools' => '0',
            'terms' => '1',
            'nonce' => wp_create_nonce('dtr_html_form_submit')
        ];

        // Force test mode to skip Workbooks sync
        $options = get_option('dtr_workbooks_options', []);
        $original_test_mode = $options['test_mode_forms']['html'] ?? false;
        $options['test_mode_forms']['html'] = 1;
        update_option('dtr_workbooks_options', $options);

        try {
            // Process registration using existing handler
            if (function_exists('dtr_html_membership_process')) {
                $result = dtr_html_membership_process($test_data);
                
                // Restore original test mode
                $options['test_mode_forms']['html'] = $original_test_mode;
                update_option('dtr_workbooks_options', $options);
                
                if ($result) {
                    // Find the created user
                    $user = get_user_by('email', $test_data['email']);
                    wp_send_json_success([
                        'message' => 'Test user created successfully (WP only)',
                        'user_id' => $user ? $user->ID : null,
                        'email' => $test_data['email'],
                        'password' => $test_data['password']
                    ]);
                } else {
                    wp_send_json_error(['message' => 'Test registration failed']);
                }
            } else {
                wp_send_json_error(['message' => 'Registration handler not available']);
            }
        } catch (Exception $e) {
            // Restore original test mode on error
            $options['test_mode_forms']['html'] = $original_test_mode;
            update_option('dtr_workbooks_options', $options);
            
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for syncing user to Workbooks
     *
     * @return void
     */
    public function handle_sync_user_to_workbooks() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dtr_member_test_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            wp_send_json_error(['message' => 'User not found']);
            return;
        }

        // Check if user already has Workbooks ID
        $existing_wb_id = get_user_meta($user_id, 'workbooks_person_id', true);
        if ($existing_wb_id) {
            wp_send_json_error(['message' => 'User already synced to Workbooks (ID: ' . $existing_wb_id . ')']);
            return;
        }

        try {
            // Get user data for sync
            $data = [
                'title' => get_user_meta($user_id, 'person_personal_title', true) ?: get_user_meta($user_id, 'title', true),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->user_email,
                'telephone' => get_user_meta($user_id, 'telephone', true),
                'job_title' => get_user_meta($user_id, 'job_title', true),
                'employer' => get_user_meta($user_id, 'employer_name', true),
                'claimed_employer' => get_user_meta($user_id, 'cf_person_claimed_employer', true) ?: get_user_meta($user_id, 'employer_name', true),
                'country' => get_user_meta($user_id, 'country', true),
                'town' => get_user_meta($user_id, 'town', true),
                'postcode' => get_user_meta($user_id, 'postcode', true),
                'marketing_selected' => [],
                'toi_selected' => []
            ];

            // Get marketing preferences
            $marketing_fields = ['cf_person_dtr_news', 'cf_person_dtr_events', 'cf_person_dtr_third_party', 'cf_person_dtr_webinar'];
            foreach ($marketing_fields as $field) {
                if (get_user_meta($user_id, $field, true)) {
                    $data['marketing_selected'][] = $field;
                }
            }

            // Get TOI selections
            $toi_fields = ['cf_person_business', 'cf_person_diseases', 'cf_person_drugs_therapies', 'cf_person_genomics_3774', 'cf_person_research_development', 'cf_person_technology', 'cf_person_tools_techniques'];
            foreach ($toi_fields as $field) {
                if (get_user_meta($user_id, $field, true)) {
                    $data['toi_selected'][] = $field;
                }
            }

            $debug_id = 'MANUAL-SYNC-' . uniqid();

            // Use existing Workbooks sync functions
            if (function_exists('dtr_html_build_workbooks_payload') && function_exists('dtr_html_workbooks_person_sync')) {
                $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
                
                if (!$workbooks) {
                    wp_send_json_error(['message' => 'Workbooks connection not available']);
                    return;
                }

                $payload = dtr_html_build_workbooks_payload($user_id, $data, $debug_id);
                $payload = function_exists('dtr_html_maybe_attach_employer_org') ? 
                          dtr_html_maybe_attach_employer_org($user_id, $payload, $data, $debug_id) : $payload;

                $sync_result = dtr_html_workbooks_person_sync($workbooks, $user_id, $payload, $user->user_email, $debug_id);

                if ($sync_result) {
                    $wb_id = get_user_meta($user_id, 'workbooks_person_id', true);
                    $wb_ref = get_user_meta($user_id, 'workbooks_object_ref', true);
                    
                    wp_send_json_success([
                        'message' => 'User successfully synced to Workbooks',
                        'workbooks_id' => $wb_id,
                        'workbooks_ref' => $wb_ref
                    ]);
                } else {
                    wp_send_json_error(['message' => 'Workbooks sync failed']);
                }
            } else {
                wp_send_json_error(['message' => 'Sync functions not available']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Sync error: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for searching test users
     *
     * @return void
     */
    public function handle_search_test_users() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'dtr_member_test_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $search = sanitize_text_field($_GET['search'] ?? '');
        
        $args = [
            'number' => 50  // Increased limit to show more users
        ];

        if ($search) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $users = get_users($args);
        $results = [];

        foreach ($users as $user) {
            $wb_id = get_user_meta($user->ID, 'workbooks_person_id', true);
            $wb_ref = get_user_meta($user->ID, 'workbooks_object_ref', true);
            $created_via_form = get_user_meta($user->ID, 'created_via_html_form', true);
            
            $results[] = [
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'registered' => $user->user_registered,
                'workbooks_id' => $wb_id,
                'workbooks_ref' => $wb_ref,
                'created_via_form' => $created_via_form,
                'synced' => !empty($wb_id)
            ];
        }

        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for loading detailed user meta data
     *
     * @return void
     */
    public function handle_load_user_meta() {
        // Check if required POST data exists
        if (!isset($_POST['nonce']) || !isset($_POST['user_id'])) {
            wp_send_json_error('Missing required data');
            return;
        }
        
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'dtr_test_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
            return;
        }
        
        // Verify user exists
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error('User not found');
            return;
        }

        // Mapping of WordPress meta keys to Workbooks field IDs
        $workbooks_field_ids = [
            'person_personal_title' => 'person_personal_title',
            'title' => 'person_personal_title',
            'first_name' => 'person_first_name',
            'last_name' => 'person_last_name',
            'user_email' => 'main_location[email]',
            'cf_person_email_address' => 'main_location[email]',
            'telephone' => 'main_location[telephone]',
            'cf_person_telephone_number' => 'main_location[telephone]',
            'job_title' => 'person_job_title',
            'cf_person_job_title' => 'person_job_title',
            'employer_name' => 'cf_person_claimed_employer',
            'cf_person_employer' => 'employer_name',
            'town' => 'main_location[town]',
            'cf_person_town_city' => 'main_location[town]',
            'country' => 'main_location[country]',
            'cf_person_country' => 'main_location[country]',
            'postcode' => 'main_location[postcode]',
            'cf_person_post_code' => 'main_location[postcode]',
            'person_aoi_1' => 'cf_person_automotive',
            'person_aoi_2' => 'cf_person_biomarkers',
            'person_aoi_3' => 'cf_person_cancer',
            'person_aoi_4' => 'cf_person_cardiology',
            'person_aoi_5' => 'cf_person_central_nervous_system',
            'person_aoi_6' => 'cf_person_clinical_trials',
            'person_aoi_7' => 'cf_person_companion_diagnostics',
            'person_aoi_8' => 'cf_person_drug_delivery',
            'person_aoi_9' => 'cf_person_endocrinology',
            'person_aoi_10' => 'cf_person_gastroenterology',
            'person_aoi_11' => 'cf_person_hematology',
            'person_aoi_12' => 'cf_person_immunology',
            'person_aoi_13' => 'cf_person_infectious_diseases',
            'person_aoi_14' => 'cf_person_inflammation',
            'person_aoi_15' => 'cf_person_metabolic_disorders',
            'person_aoi_16' => 'cf_person_nephrology',
            'person_aoi_17' => 'cf_person_ophthalmology',
            'person_aoi_18' => 'cf_person_orphan_drugs',
            'person_aoi_19' => 'cf_person_respiratory',
            'person_aoi_20' => 'cf_person_rheumatology',
            'person_aoi_21' => 'cf_person_womens_health'
        ];

        // Get all meta fields that are actually stored by the form
        $meta_fields = [
            'person_personal_title' => 'Personal Title',
            'title' => 'Personal Title (Alt)',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'user_email' => 'Email Address (User)',
            'cf_person_email_address' => 'Email Address',
            'telephone' => 'Telephone',
            'cf_person_telephone_number' => 'Telephone (Form)', 
            'job_title' => 'Job Title',
            'cf_person_job_title' => 'Job Title (Form)',
            'employer_name' => 'Employer',
            'cf_person_employer' => 'Employer (Form)',
            // 'cf_person_claimed_employer' => 'Claimed Employer', // Commented out - using employer_name only
            'town' => 'City',
            'cf_person_town_city' => 'City (Form)',
            'country' => 'Country',
            'cf_person_country' => 'Country (Form)',
            'postcode' => 'Postal Code',
            'cf_person_post_code' => 'Postal Code (Form)',
            'person_aoi_1' => 'AOI 1',
            'person_aoi_2' => 'AOI 2',
            'person_aoi_3' => 'AOI 3',
            'person_aoi_4' => 'AOI 4',
            'person_aoi_5' => 'AOI 5',
            'person_aoi_6' => 'AOI 6',
            'person_aoi_7' => 'AOI 7',
            'person_aoi_8' => 'AOI 8',
            'person_aoi_9' => 'AOI 9',
            'person_aoi_10' => 'AOI 10',
            'person_aoi_11' => 'AOI 11',
            'person_aoi_12' => 'AOI 12',
            'person_aoi_13' => 'AOI 13',
            'person_aoi_14' => 'AOI 14',
            'person_aoi_15' => 'AOI 15',
            'person_aoi_16' => 'AOI 16',
            'person_aoi_17' => 'AOI 17',
            'person_aoi_18' => 'AOI 18',
            'person_aoi_19' => 'AOI 19',
            'person_aoi_20' => 'AOI 20',
            'person_aoi_21' => 'AOI 21'
        ];

        // Check if user is synced to Workbooks
        $workbooks_person_id = get_user_meta($user_id, 'workbooks_person_id', true);
        $is_synced = !empty($workbooks_person_id);

        $result_fields = [];
        
        try {
            foreach ($meta_fields as $meta_key => $workbooks_field) {
                // Handle special cases for non-meta fields
                if ($meta_key === 'user_email') {
                    $wp_value = $user->user_email;
                } else {
                    $wp_value = get_user_meta($user_id, $meta_key, true);
                    
                    // Convert arrays to strings for display
                    if (is_array($wp_value)) {
                        $wp_value = implode(', ', $wp_value);
                    }
                }
                
                // Ensure we have a string value
                $wp_value = (string) $wp_value;
                
                // For now, just show sync status in Workbooks column
                // TODO: Later we can add live Workbooks data fetching
                $workbooks_status = $is_synced ? 'Synced to Workbooks' : 'Not synced';
                
                $result_fields[] = [
                    'workbooks_field' => $workbooks_field,
                    'workbooks_field_id' => $workbooks_field_ids[$meta_key] ?? $meta_key,
                    'meta_key' => $meta_key,
                    'wp_value' => $wp_value,
                    'workbooks_value' => $workbooks_status,
                    'is_synced' => $is_synced
                ];
            }
            
            error_log('DTR Load User Meta - Sending response with ' . count($result_fields) . ' fields');
            wp_send_json_success(['meta_fields' => $result_fields, 'is_synced' => $is_synced]);
            
        } catch (Exception $e) {
            error_log('DTR Load User Meta Error: ' . $e->getMessage());
            wp_send_json_error('Error loading user meta data: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX request to get all Workbooks API fields for a user
     */
    public function handle_get_workbooks_fields() {
        error_log('DTR Get Workbooks Fields - Handler called');
        error_log('DTR Get Workbooks Fields - POST data: ' . print_r($_POST, true));
        
        // Check if required POST data exists
        if (!isset($_POST['nonce']) || !isset($_POST['workbooks_id'])) {
            error_log('DTR Get Workbooks Fields - Missing required data');
            wp_send_json_error('Missing required data');
            return;
        }
        
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'dtr_test_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $workbooks_id = intval($_POST['workbooks_id']);
        error_log('DTR Get Workbooks Fields - Workbooks ID: ' . $workbooks_id);
        if (!$workbooks_id) {
            error_log('DTR Get Workbooks Fields - Invalid Workbooks ID');
            wp_send_json_error('Invalid Workbooks ID');
            return;
        }

        try {
            error_log('DTR Get Workbooks Fields - Starting API call');
            
            // Use the same method as content-person-record.php
            $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
            
            if (!$workbooks) {
                error_log('DTR Get Workbooks Fields - Workbooks instance not available');
                wp_send_json_error('Workbooks API not available');
                return;
            }

            // Make API call using the same method as the working implementation
            $result = $workbooks->assertGet('crm/people.api', [
                '_start' => 0,
                '_limit' => 1,
                '_ff[]' => 'id',
                '_ft[]' => 'eq',
                '_fc[]' => $workbooks_id
            ]);

            if (empty($result['data'][0])) {
                error_log('DTR Get Workbooks Fields - No person data found');
                wp_send_json_error('No person data found');
                return;
            }

            // Get the person record
            $person_data = $result['data'][0];
            error_log('DTR Get Workbooks Fields - Success, found ' . count($person_data) . ' fields');

            wp_send_json_success([
                'fields' => $person_data,
                'workbooks_id' => $workbooks_id
            ]);

        } catch (Exception $e) {
            error_log('DTR Get Workbooks Fields Error: ' . $e->getMessage());
            wp_send_json_error('Error fetching Workbooks fields: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX request to regenerate employer data for a Workbooks person record
     */
    public function handle_regenerate_employer_data() {
        // Check if required POST data exists
        if (!isset($_POST['nonce']) || !isset($_POST['workbooks_id']) || !isset($_POST['user_id'])) {
            wp_send_json_error('Missing required data');
            return;
        }
        
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'dtr_test_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $workbooks_id = intval($_POST['workbooks_id']);
        $user_id = intval($_POST['user_id']);
        
        if (!$workbooks_id || !$user_id) {
            wp_send_json_error('Invalid Workbooks ID or User ID');
            return;
        }

        try {
            error_log('DTR Regenerate Employer Data - Starting for Workbooks ID: ' . $workbooks_id);
            
            // Use the same method as content-person-record.php
            $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
            
            if (!$workbooks) {
                error_log('DTR Regenerate Employer Data - Workbooks instance not available');
                wp_send_json_error('Workbooks API not available');
                return;
            }

            // First, fetch the current record to get the current data
            $current_record = $workbooks->assertGet('crm/people', [
                '_start' => 0,
                '_limit' => 1,
                '_ff[]' => 'id',
                '_ft[]' => 'eq',
                '_fc[]' => $workbooks_id
            ]);
            
            if (empty($current_record['data'][0])) {
                wp_send_json_error('Person record not found in Workbooks');
                return;
            }

            $person_data = $current_record['data'][0];
            
            // Get the employer name from cf_person_claimed_employer or WordPress meta
            $claimed_employer = $person_data['cf_person_claimed_employer'] ?? '';
            if (empty($claimed_employer)) {
                $claimed_employer = get_user_meta($user_id, 'employer_name', true) ?: 'Supersonic Playground Ltd';
            }

            if (empty($claimed_employer)) {
                wp_send_json_error('No employer information found to regenerate');
                return;
            }

            // Get or create the organization ID
            $org_id = null;
            if (function_exists('workbooks_get_or_create_organisation_id')) {
                $org_id = workbooks_get_or_create_organisation_id($claimed_employer);
            }

            // Prepare the update payload
            $update_payload = [
                'id' => $workbooks_id,
                'lock_version' => $person_data['lock_version'] ?? 0,
                'cf_person_claimed_employer' => $claimed_employer
            ];

            // Add employer_link if we have an organization ID
            if ($org_id) {
                $update_payload['employer_link'] = $org_id;
                error_log('DTR Regenerate Employer Data - Adding employer_link: ' . $org_id);
            }

            // Update the record in Workbooks
            $result = $workbooks->assertUpdate('crm/people', [$update_payload]);
            
            if (!empty($result['affected_objects']) && $result['affected_objects'] > 0) {
                $message = "✅ Employer data regenerated successfully!\n\n";
                $message .= "• cf_person_claimed_employer: {$claimed_employer}\n";
                if ($org_id) {
                    $message .= "• employer_link: {$org_id}\n";
                    $message .= "• employer_name: Will be auto-populated by Workbooks";
                } else {
                    $message .= "• employer_link: Could not resolve organization ID";
                }

                error_log('DTR Regenerate Employer Data - Success for Person ID: ' . $workbooks_id);
                wp_send_json_success(['message' => $message]);
            } else {
                wp_send_json_error('No changes were made to the record');
            }

        } catch (Exception $e) {
            error_log('DTR Regenerate Employer Data Error: ' . $e->getMessage());
            wp_send_json_error('Error regenerating employer data: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for testing Workbooks connection
     *
     * @return void
     */
    public function test_workbooks_connection() {
        check_ajax_referer('workbooks_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'dtr-workbooks'));
        }
        
        // Initialize debug log
        $debug_log = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/connection-debug.log';
        $log_dir = dirname($debug_log);
        
        // Ensure the admin directory exists
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
    // Start debug logging (gated by debug_mode)
    $debug_entry = date('[Y-m-d H:i:s]') . " Starting connection test...\n";
    dtr_admin_log($debug_entry, 'connection-debug.log');
        
        try {
            // Log current settings
            $options = get_option('dtr_workbooks_options', []);
            $debug_entry = date('[Y-m-d H:i:s]') . " Current settings:\n";
            $debug_entry .= "API URL: " . ($options['api_url'] ?? 'not set') . "\n";
            $debug_entry .= "API Key: " . (empty($options['api_key']) ? 'not set' : 'set') . "\n";
            dtr_admin_log($debug_entry, 'connection-debug.log');
            
            // Check if Workbooks API class exists - FIXED CLASS NAME
            if (!class_exists('WorkbooksApi')) {
                $error = 'WorkbooksApi class not found. Please check if the API file is properly included.';
                dtr_admin_log(date('[Y-m-d H:i:s]') . " Error: {$error}\n", 'connection-debug.log');
                wp_send_json_error(['message' => __($error, 'dtr-workbooks')]);
                return;
            }

            dtr_admin_log(date('[Y-m-d H:i:s]') . " Attempting to get Workbooks instance...\n", 'connection-debug.log');
            
            // Get Workbooks instance
            $workbooks = get_workbooks_instance();
            
            if ($workbooks === false) {
                $error = 'Failed to initialize Workbooks connection. Check connection-debug.log for details.';
                dtr_admin_log(date('[Y-m-d H:i:s]') . " Error: Failed to get Workbooks instance\n", 'connection-debug.log');
                wp_send_json_error([
                    'message' => __($error, 'dtr-workbooks'),
                    'debug_log' => DTR_WORKBOOKS_PLUGIN_URL . 'admin/connection-debug.log'
                ]);
                return;
            }
            
            dtr_admin_log(date('[Y-m-d H:i:s]') . " Successfully got Workbooks instance\n", 'connection-debug.log');

            // Log successful initialization
            dtr_admin_log(date('[Y-m-d H:i:s]') . " Workbooks instance initialized successfully\n", 'connection-debug.log');
            
            // Test connection by fetching a single person record
            $response = $workbooks->assertGet('crm/people', ['_limit' => 1]);
            
            // Log API response (gated by debug_mode)
            $debug_entry = date('[Y-m-d H:i:s]') . " API Response:\n" . print_r($response, true) . "\n";
            dtr_admin_log($debug_entry, 'connection-debug.log');
            
            if (isset($response['data'])) {
                update_option('dtr_workbooks_last_connection_test', time());
                $success = 'Connection successful! API is working correctly.';
                dtr_admin_log(date('[Y-m-d H:i:s]') . " Success: {$success}\n", 'connection-debug.log');
                wp_send_json_success([
                    'message' => __($success, 'dtr-workbooks'),
                    'response' => $response
                ]);
            } else {
                $error = 'API responded but data format unexpected.';
                dtr_admin_log(date('[Y-m-d H:i:s]') . " Error: {$error}\n", 'connection-debug.log');
                wp_send_json_error(['message' => __($error, 'dtr-workbooks')]);
            }
        } catch (Exception $e) {
            // Log detailed error information
            $error_entry = sprintf(
                "[%s] Exception:\nMessage: %s\nFile: %s\nLine: %d\nStack Trace:\n%s\n",
                date('Y-m-d H:i:s'),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            dtr_admin_log($error_entry, 'connection-debug.log');
            
            // Also log to regular debug log
            dtr_custom_log('Connection test failed: ' . $e->getMessage(), 'error');
            
            wp_send_json_error([
                'message' => 'Connection failed: ' . $e->getMessage(),
                'debug_log' => DTR_WORKBOOKS_PLUGIN_URL . 'admin/connection-debug.log'
            ]);
        }
    }
    
    /**
     * AJAX handler for clearing logs
     *
     * @return void
     */
    public function clear_logs() {
        check_ajax_referer('dtr_workbooks_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'dtr-workbooks'));
        }
        
        $log_files = glob(DTR_WORKBOOKS_LOG_DIR . '*.log*');
        $deleted = 0;
        
        foreach ($log_files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        if ($deleted > 0) {
            dtr_custom_log("Cleared {$deleted} log files");
            wp_send_json_success(['message' => sprintf(__('Cleared %d log files', 'dtr-workbooks'), $deleted)]);
        } else {
            wp_send_json_error(['message' => __('No log files to clear', 'dtr-workbooks')]);
        }
    }
    
    /**
     * AJAX handler for exporting logs
     *
     * @return void
     */
    public function export_logs() {
        check_ajax_referer('dtr_workbooks_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'dtr-workbooks'));
        }
        
        $log_files = glob(DTR_WORKBOOKS_LOG_DIR . '*.log');
        
        if (empty($log_files)) {
            wp_send_json_error(['message' => __('No log files to export', 'dtr-workbooks')]);
        }
        
        // Create a zip file with all logs
        $zip_file = DTR_WORKBOOKS_LOG_DIR . 'dtr-logs-export-' . date('Y-m-d-H-i-s') . '.zip';
        
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                foreach ($log_files as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
                
                $download_url = DTR_WORKBOOKS_PLUGIN_URL . 'logs/' . basename($zip_file);
                wp_send_json_success(['download_url' => $download_url]);
            } else {
                wp_send_json_error(['message' => __('Failed to create zip file', 'dtr-workbooks')]);
            }
        } else {
            wp_send_json_error(['message' => __('ZipArchive not available', 'dtr-workbooks')]);
        }
    }
    
    /**
     * AJAX handler for sync test
     *
     * @return void
     */
    public function sync_test() {
        check_ajax_referer('dtr_workbooks_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'dtr-workbooks'));
        }
        
        // Test data sync with sample data
        $test_data = [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'company' => 'Test Company'
        ];
        
        if (function_exists('dtr_create_workbooks_person')) {
            $result = dtr_create_workbooks_person($test_data, 'SYNC-TEST');
            
            if ($result && isset($result['success']) && $result['success']) {
                wp_send_json_success(['message' => __('Sync test successful', 'dtr-workbooks')]);
            } else {
                wp_send_json_error(['message' => $result['message'] ?? __('Sync test failed', 'dtr-workbooks')]);
            }
        } else {
            wp_send_json_error(['message' => __('Workbooks integration function not available', 'dtr-workbooks')]);
        }
    }
    
    /**
     * AJAX handler for retrying submission
     *
     * @return void
     */
    public function retry_submission() {
        check_ajax_referer('dtr_workbooks_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'dtr-workbooks'));
        }
        
        $submission_id = intval($_POST['submission_id'] ?? 0);
        
        if (!$submission_id) {
            wp_send_json_error(['message' => __('Invalid submission ID', 'dtr-workbooks')]);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dtr_workbooks_submissions';
        
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $submission_id
        ));
        
        if (!$submission) {
            wp_send_json_error(['message' => __('Submission not found', 'dtr-workbooks')]);
        }
        
        $submission_data = json_decode($submission->submission_data, true);
        
        // Retry processing based on form type
        $success = false;
        if ($submission->form_id == 15) {
            $success = function_exists('dtr_process_user_registration') 
                ? dtr_process_user_registration($submission_data, $submission->form_id)
                : false;
        } elseif ($submission->form_id == 2) {
            $success = function_exists('dtr_process_webinar_registration') 
                ? dtr_process_webinar_registration($submission_data, $submission->form_id)
                : false;
        } else {
            $success = function_exists('dtr_process_lead_generation') 
                ? dtr_process_lead_generation($submission_data, $submission->form_id)
                : false;
        }
        
        if ($success) {
            $wpdb->update(
                $table_name,
                ['status' => 'completed', 'error_message' => null],
                ['id' => $submission_id]
            );
            wp_send_json_success(['message' => __('Submission retry successful', 'dtr-workbooks')]);
        } else {
            wp_send_json_error(['message' => __('Submission retry failed', 'dtr-workbooks')]);
        }
    }
    
    /**
     * AJAX handler for getting submission details
     *
     * @return void
     */
    public function get_submission_details() {
        check_ajax_referer('dtr_workbooks_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'dtr-workbooks'));
        }
        
        $submission_id = intval($_POST['submission_id'] ?? 0);
        
        if (!$submission_id) {
            wp_send_json_error(['message' => __('Invalid submission ID', 'dtr-workbooks')]);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dtr_workbooks_submissions';
        
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $submission_id
        ));
        
        if (!$submission) {
            wp_send_json_error(['message' => __('Submission not found', 'dtr-workbooks')]);
        }
        
        wp_send_json_success([
            'submission' => $submission,
            'formatted_data' => json_decode($submission->submission_data, true)
        ]);
    }
    
    /**
     * Get log files information
     *
     * @return array
     */
    private function get_log_files() {
        $log_files = glob(DTR_WORKBOOKS_LOG_DIR . '*.log*');
        $files = [];
        
        foreach ($log_files as $file) {
            $filename = basename($file);
            $files[$filename] = [
                'size' => filesize($file),
                'size_formatted' => size_format(filesize($file)),
                'modified' => filemtime($file)
            ];
        }
        
        return $files;
    }
    
    /**
     * Get system status information
     *
     * @return array
     */
    private function get_system_status() {
        $dependencies = [
            'ninja-forms/ninja-forms.php' => 'Ninja Forms'
        ];
        
        $status = ['dependencies' => []];
        
        foreach ($dependencies as $plugin => $name) {
            $status['dependencies'][$plugin] = [
                'name' => $name,
                'active' => is_plugin_active($plugin),
                'version' => $this->get_plugin_version($plugin)
            ];
        }
        
        // Add ACF status (check both free and Pro versions)
        $acf_free_active = is_plugin_active('advanced-custom-fields/acf.php');
        $acf_pro_active = is_plugin_active('advanced-custom-fields-pro/acf.php');
        $acf_active = $acf_free_active || $acf_pro_active;
        
        $acf_plugin_path = $acf_pro_active ? 'advanced-custom-fields-pro/acf.php' : 'advanced-custom-fields/acf.php';
        $acf_name = $acf_pro_active ? 'Advanced Custom Fields PRO' : 'Advanced Custom Fields';
        
        $status['dependencies'][$acf_plugin_path] = [
            'name' => $acf_name,
            'active' => $acf_active,
            'version' => $acf_active ? $this->get_plugin_version($acf_plugin_path) : ''
        ];
        
        return $status;
    }
    
    /**
     * Get plugin version
     *
     * @param string $plugin_file Plugin file path
     * @return string
     */
    private function get_plugin_version($plugin_file) {
        if (!is_plugin_active($plugin_file)) {
            return '';
        }
        
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false);
        return $plugin_data['Version'] ?? '';
    }
    
    /**
     * Cleanup old log files
     *
     * @return void
     */
    public function cleanup_old_logs() {
        $retention_days = $this->options['log_retention_days'] ?? 30;
        $cutoff_time = time() - ($retention_days * 24 * 60 * 60);
        
        $log_files = glob(DTR_WORKBOOKS_LOG_DIR . '*.log*');
        $deleted = 0;
        
        foreach ($log_files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        if ($deleted > 0) {
            dtr_custom_log("Cleanup: Deleted {$deleted} old log files");
        }
    }
    
    /**
     * Validate plugin options
     *
     * @param array $input Raw input options
     * @return array Validated options
     */
    public function validate_options($input) {
        $validated = [];
        
        // API URL validation
        if (isset($input['api_url'])) {
            $validated['api_url'] = esc_url_raw($input['api_url']);
        }
        
        // API Key validation
        if (isset($input['api_key'])) {
            $validated['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        // Debug mode validation
        $validated['debug_mode'] = !empty($input['debug_mode']);
        
        // Enabled forms validation
        if (isset($input['enabled_forms']) && is_array($input['enabled_forms'])) {
            $validated['enabled_forms'] = array_map(function($form) {
                // Handle both numeric and string form IDs
                return is_numeric($form) ? intval($form) : sanitize_text_field($form);
            }, $input['enabled_forms']);
        }
        
        // Form dev modes validation
        if (isset($input['form_dev_modes']) && is_array($input['form_dev_modes'])) {
            $validated['form_dev_modes'] = [];
            foreach ($input['form_dev_modes'] as $form_id => $dev_mode) {
                $sanitized_form_id = is_numeric($form_id) ? intval($form_id) : sanitize_text_field($form_id);
                $validated['form_dev_modes'][$sanitized_form_id] = !empty($dev_mode);
            }
        }
        
        // API timeout validation
        if (isset($input['api_timeout'])) {
            $timeout = intval($input['api_timeout']);
            $validated['api_timeout'] = ($timeout >= 5 && $timeout <= 300) ? $timeout : 30;
        }
        
        // Retry attempts validation
        if (isset($input['retry_attempts'])) {
            $attempts = intval($input['retry_attempts']);
            $validated['retry_attempts'] = ($attempts >= 0 && $attempts <= 10) ? $attempts : 3;
        }
        
        // Log retention validation
        if (isset($input['log_retention_days'])) {
            $days = intval($input['log_retention_days']);
            $validated['log_retention_days'] = ($days >= 1 && $days <= 365) ? $days : 30;
        }

        // If debug_mode is being turned off, archive existing admin log files
        try {
            $previous = get_option('dtr_workbooks_options', []);
            $prev_debug = !empty($previous['debug_mode']);
            $new_debug = !empty($validated['debug_mode']);

            if ($prev_debug && !$new_debug) {
                $admin_dir = plugin_dir_path(__FILE__) . 'admin/';
                $archive_dir = $admin_dir . 'archive/';

                if (!is_dir($admin_dir)) {
                    // nothing to archive
                } else {
                    if (!is_dir($archive_dir)) {
                        wp_mkdir_p($archive_dir);
                    }

                    $files = glob($admin_dir . '*.log');
                    $timestamp = date('Ymd_His');
                    foreach ($files as $file) {
                        if (!is_file($file)) {
                            continue;
                        }
                        $base = basename($file);
                        $dest = $archive_dir . $timestamp . '_' . $base;
                        // Move the file out of the webroot admin folder
                        @rename($file, $dest);
                    }

                    // Log the archive action to the persistent custom log
                    if (function_exists('dtr_custom_log')) {
                        dtr_custom_log("Archived admin logs to: {$archive_dir}");
                    }
                }
            }
        } catch (Exception $e) {
            // non-fatal; don't block saving options
            if (function_exists('dtr_custom_log')) {
                dtr_custom_log('Failed to archive admin logs: ' . $e->getMessage(), 'error');
            }
        }

        return $validated;
    }
    
    /**
     * Create database tables
     *
     * @return void
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Submissions table
        $submissions_table = $wpdb->prefix . 'dtr_workbooks_submissions';
        $submissions_sql = "CREATE TABLE $submissions_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id int(11) NOT NULL,
            submission_data longtext NOT NULL,
            workbooks_person_id varchar(255) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Sync log table
        $sync_log_table = $wpdb->prefix . 'dtr_workbooks_sync_log';
        $sync_log_sql = "CREATE TABLE $sync_log_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            operation varchar(100) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            request_data longtext DEFAULT NULL,
            response_data longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            execution_time float DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY operation (operation),
            KEY object_type (object_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($submissions_sql);
        dbDelta($sync_log_sql);
    }
    
    /**
     * Log error message
     *
     * @param string $message Error message
     * @return void
     */
    private function log_error($message) {
        if (function_exists('dtr_custom_log')) {
            dtr_custom_log($message, 'error');
        } else {
            error_log('[DTR Workbooks] ' . $message);
        }
    }
    
    /**
     * Display dependency notice
     *
     * @return void
     */
    public function dependency_notice() {
        if (!empty($this->missing_dependencies)) {
            $plugins = implode(', ', $this->missing_dependencies);
            echo '<div class="notice notice-error"><p>';
            printf(
                __('DTR Workbooks Integration requires the following plugins: %s', 'dtr-workbooks'),
                '<strong>' . $plugins . '</strong>'
            );
            echo '</p></div>';
        }
    }
    
    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Set default options
        $default_options = [
            'debug_mode' => true,
            'enabled_forms' => [2, 15, 31],
            'api_timeout' => 30,
            'retry_attempts' => 3,
            'log_retention_days' => 30
        ];
        
        add_option('dtr_workbooks_options', $default_options);
        
        // Create log directory
        if (!is_dir(DTR_WORKBOOKS_LOG_DIR)) {
            wp_mkdir_p(DTR_WORKBOOKS_LOG_DIR);
            file_put_contents(DTR_WORKBOOKS_LOG_DIR . '.htaccess', "Order Deny,Allow\nDeny from all\n");
            file_put_contents(DTR_WORKBOOKS_LOG_DIR . 'index.php', '<?php // Silence is golden');
        }
        
        // Setup capabilities
        $this->setup_capabilities();
        
        // Log activation
        dtr_custom_log('DTR Workbooks Integration plugin activated');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('dtr_workbooks_cleanup');
        
        // Log deactivation
        dtr_custom_log('DTR Workbooks Integration plugin deactivated');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     *
     * @return void
        
        // Log deactivation
        dtr_custom_log('DTR Workbooks Integration plugin deactivated');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     *
     * @return void
     */
    public static function uninstall() {
        // Remove options
        delete_option('dtr_workbooks_options');
        delete_option('dtr_workbooks_last_connection_test');
        
        // Remove custom capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->remove_cap('manage_dtr_workbooks');
            $role->remove_cap('view_dtr_workbooks_logs');
        }
        
        // Remove database tables
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dtr_workbooks_submissions");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dtr_workbooks_sync_log");
        
        // Remove log directory
        $log_dir = plugin_dir_path(__FILE__) . 'logs/';
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($log_dir);
        }
    }
    
    /**
     * Handle admin test webinar form submission
     */
    public function handle_admin_test_webinar() {
        // Verify nonce for security (optional for admin-only testing)
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access.');
        }
        
        // Extract form data
        $registration_data = [
            'post_id' => sanitize_text_field($_POST['post_id'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'person_id' => sanitize_text_field($_POST['person_id'] ?? ''),
            'event_id' => sanitize_text_field($_POST['event_id'] ?? ''),
            'speaker_question' => sanitize_textarea_field($_POST['speaker_question'] ?? ''),
            'cf_mailing_list_member_sponsor_1_optin' => !empty($_POST['cf_mailing_list_member_sponsor_1_optin']) ? 1 : 0
        ];
        
        // Log to admin debug file first
        $admin_log_file = plugin_dir_path(__FILE__) . 'admin/admin-webinar-debug.log';
        $debug_lines = [];
        foreach ($registration_data as $key => $value) {
            $debug_lines[] = "[$key] => $value";
        }
        $debug_entry = implode("\n", $debug_lines) . "\n";
        error_log($debug_entry, 3, $admin_log_file);
        
        // Admin test uses the live handler directly (no separate admin handler needed)
        
        // Process using existing handler
        if (function_exists('dtr_handle_live_webinar_registration')) {
            try {
                $result = dtr_handle_live_webinar_registration($registration_data);
                
                if (!empty($result['success'])) {
                    echo '<h3>Registration Successful</h3>';
                    echo '<p>Ticket ID: ' . esc_html($result['ticket_id'] ?? 'N/A') . '</p>';
                    echo '<p>Person ID: ' . esc_html($result['person_id'] ?? 'N/A') . '</p>';
                    echo '<p>Event ID: ' . esc_html($result['event_id'] ?? 'N/A') . '</p>';
                    echo '<p>Check admin-webinar-debug.log for detailed processing steps.</p>';
                } else {
                    echo '<h3>Registration Failed</h3>';
                    echo '<p>Please check admin-webinar-debug.log for error details.</p>';
                }
            } catch (Exception $e) {
                echo '<h3>Registration Error</h3>';
                echo '<p>Error: ' . esc_html($e->getMessage()) . '</p>';
                error_log('DTR Admin Test Error: ' . $e->getMessage(), 3, $admin_log_file);
            }
        } else {
            echo '<h3>Handler Function Not Available</h3>';
            echo '<p>The dtr_handle_live_webinar_registration function could not be loaded.</p>';
        }
        
        wp_die(); // Important: terminate admin-post processing
    }
    
    /**
     * Handle admin test lead generation (Form 31) form submission
     */
    public function handle_admin_test_lead_generation() {
        // Verify nonce for security (optional for admin-only testing)
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access.');
        }
        
        // Extract form data
        $lead_data = [
            'post_id' => sanitize_text_field($_POST['post_id'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'person_id' => sanitize_text_field($_POST['person_id'] ?? ''),
            'lead_question' => sanitize_textarea_field($_POST['lead_question'] ?? ''),
            'cf_mailing_list_member_sponsor_1_optin' => !empty($_POST['cf_mailing_list_member_sponsor_1_optin']) ? 1 : 0
        ];
        
        // Log to admin debug file first
        $admin_log_file = plugin_dir_path(__FILE__) . 'logs/lead-generation-admin-debug.log';
        $debug_lines = [];
        foreach ($lead_data as $key => $value) {
            $debug_lines[] = "[$key] => $value";
        }
        $debug_entry = date('Y-m-d H:i:s') . " - Admin Test Lead Generation:\n" . implode("\n", $debug_lines) . "\n\n";
        error_log($debug_entry, 3, $admin_log_file);
        
        // Include the lead generation handler
        $handler_file = plugin_dir_path(__FILE__) . 'includes/form-handler-lead-generation-registration.php';
        if (!file_exists($handler_file)) {
            echo '<h3>Handler File Not Found</h3>';
            echo '<p>Expected: ' . esc_html($handler_file) . '</p>';
            wp_die();
        }
        
        require_once $handler_file;
        
        // Process using existing handler
        if (function_exists('dtr_handle_lead_generation_registration')) {
            try {
                $result = dtr_handle_lead_generation_registration($lead_data);
                
                if (!empty($result['success'])) {
                    echo '<h3>Lead Generation Successful</h3>';
                    echo '<p>Lead ID: ' . esc_html($result['lead_id'] ?? 'N/A') . '</p>';
                    echo '<p>Person ID: ' . esc_html($result['person_id'] ?? 'N/A') . '</p>';
                    echo '<p>Check lead-generation-registration-debug.log for detailed processing steps.</p>';
                } else {
                    echo '<h3>Lead Generation Failed</h3>';
                    echo '<p>Please check lead-generation-registration-debug.log for error details.</p>';
                    if (!empty($result['error'])) {
                        echo '<p>Error: ' . esc_html($result['error']) . '</p>';
                    }
                }
            } catch (Exception $e) {
                echo '<h3>Lead Generation Error</h3>';
                echo '<p>Error: ' . esc_html($e->getMessage()) . '</p>';
                error_log('DTR Admin Lead Gen Test Error: ' . $e->getMessage(), 3, $admin_log_file);
            }
        } else {
            echo '<h3>Handler Function Not Available</h3>';
            echo '<p>The dtr_handle_lead_generation_registration function could not be loaded.</p>';
        }
        
        wp_die(); // Important: terminate admin-post processing
    }
    
    // Settings field callbacks
    public function api_section_callback() {
        echo '<p>' . __('Configure your Workbooks API connection settings.', 'dtr-workbooks') . '</p>';
    }
    
    public function api_url_field_callback($args) {
        $value = $this->options['api_url'] ?? '';
        echo '<input type="url" id="api_url" name="dtr_workbooks_options[api_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($args['placeholder']) . '" required />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function api_key_field_callback($args) {
        $value = $this->options['api_key'] ?? '';
        echo '<input type="password" id="api_key" name="dtr_workbooks_options[api_key]" value="' . esc_attr($value) . '" class="regular-text" required autocomplete="new-password" />';
        echo '<p class="description">' . __('Your Workbooks API key', 'dtr-workbooks') . '</p>';
    }
    
    public function forms_section_callback() {
        echo '<p>' . __('Configure which forms should be processed by the integration.', 'dtr-workbooks') . '</p>';
    }
    
    public function enabled_forms_field_callback() {
        // Debug: Log that this function is being called
        error_log('DTR: enabled_forms_field_callback called');
        
        $enabled_forms = $this->options['enabled_forms'] ?? [2, 15, 31, 'media_planner', 'membership_registration'];
        $form_dev_modes = $this->options['form_dev_modes'] ?? [];
        
        // Debug: Log current options
        error_log('DTR: enabled_forms = ' . print_r($enabled_forms, true));
        error_log('DTR: form_dev_modes = ' . print_r($form_dev_modes, true));
        
        $available_forms = [
            2 => 'Webinar Form', 
            15 => 'Registration Form', 
            31 => 'Lead Gen Form',
            'media_planner' => 'Media Planner Form',
            'membership_registration' => 'Membership Registration Form'
        ];
        
        // Debug: Log available forms
        error_log('DTR: available_forms = ' . print_r($available_forms, true));
        
        echo '<style>
            .dtr-form-config { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa; }
            .dtr-form-config h4 { margin: 0 0 10px 0; color: #23282d; }
            .dtr-form-toggle { display: flex; align-items: center; gap: 15px; margin-bottom: 10px; }
            .dtr-toggle-switch { position: relative; width: 60px; height: 30px; }
            .dtr-toggle-switch input { opacity: 0; width: 0; height: 0; }
            .dtr-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 30px; }
            .dtr-toggle-slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
            input:checked + .dtr-toggle-slider { background-color: #4CAF50; }
            input:checked + .dtr-toggle-slider:before { transform: translateX(30px); }
            .dtr-toggle-label { font-weight: bold; }
            .dtr-toggle-status { font-size: 14px; padding: 4px 8px; border-radius: 4px; }
            .dtr-toggle-status.live { background: #4CAF50; color: white; }
            .dtr-toggle-status.dev { background: #ff9800; color: white; }
            .dtr-dev-toggle { margin-left: 20px; opacity: 0.7; }
        </style>';
        
        echo '<h3>Form Configuration</h3>';
        echo '<p>Configure which forms should be processed by the integration.</p>';
        
        foreach ($available_forms as $form_id => $form_name) {
            $is_enabled = in_array($form_id, $enabled_forms);
            $is_dev_mode = isset($form_dev_modes[$form_id]) && $form_dev_modes[$form_id];
            
            echo '<div class="dtr-form-config">';
            echo '<h4>' . esc_html($form_name) . ' (ID: ' . esc_html($form_id) . ')</h4>';
            
            echo '<div class="dtr-form-toggle">';
            echo '<label class="dtr-toggle-switch">';
            echo '<input type="checkbox" name="dtr_workbooks_options[enabled_forms][]" value="' . esc_attr($form_id) . '" ' . ($is_enabled ? 'checked' : '') . ' />';
            echo '<span class="dtr-toggle-slider"></span>';
            echo '</label>';
            echo '<span class="dtr-toggle-status ' . ($is_enabled ? 'live' : 'dev') . '">' . ($is_enabled ? 'Live' : 'Disabled') . '</span>';
            echo '</div>';
            
            if ($is_enabled) {
                echo '<div class="dtr-dev-toggle">';
                echo '<label>';
                echo '<input type="checkbox" name="dtr_workbooks_options[form_dev_modes][' . esc_attr($form_id) . ']" value="1" ' . ($is_dev_mode ? 'checked' : '') . ' /> ';
                echo 'Enable Development Mode (shows debug tools and test functions)';
                echo '</label>';
                echo '</div>';
            }
            
            echo '</div>';
        }
    }
    
    public function debug_section_callback() {
        echo '<p>' . __('Configure debug and logging settings.', 'dtr-workbooks') . '</p>';
    }
    
    public function debug_mode_field_callback() {
        $checked = !empty($this->options['debug_mode']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="dtr_workbooks_options[debug_mode]" value="1" ' . $checked . ' /> ' . __('Enable debug mode', 'dtr-workbooks') . '</label>';
    }

    /**
     * Check if a form is enabled and get its dev mode status
     * 
     * @param string|int $form_id The form identifier
     * @return array Array with 'enabled' and 'dev_mode' keys
     */
    public function get_form_config($form_id) {
        $enabled_forms = $this->options['enabled_forms'] ?? [2, 15, 31, 'media_planner', 'membership_registration', 'forgot_password'];
        $form_dev_modes = $this->options['form_dev_modes'] ?? [];
        
        return [
            'enabled' => in_array($form_id, $enabled_forms),
            'dev_mode' => isset($form_dev_modes[$form_id]) && $form_dev_modes[$form_id]
        ];
    }
    
    /**
     * Get form configuration for shortcode use
     * Static method that can be called from shortcodes
     * 
     * @param string|int $form_id The form identifier  
     * @return array Array with 'enabled' and 'dev_mode' keys
     */
    public static function get_shortcode_form_config($form_id) {
        $instance = self::get_instance();
        return $instance->get_form_config($form_id);
    }

    /**
     * Prepare Ninja Forms submission data
     * Ensures all required arrays are initialized
     *
     * @param array $data Form submission data
     * @return array Modified form data
     */
    public function prepare_ninja_forms_submission($data) {
        // Initialize required arrays if they don't exist
        if (!isset($data['fields'])) {
            $data['fields'] = [];
        }
        
        if (!isset($data['extra'])) {
            $data['extra'] = [];
        }
        
        // Ensure the fields array is not null
        if ($data['fields'] === null) {
            $data['fields'] = [];
        }
        
        // Log submission data in debug mode
        if ($this->debug_mode) {
            dtr_custom_log("Ninja Forms submission data: " . print_r($data, true));
        }
        
        // Handle webinar registration for form 2
        if (isset($data['id']) && intval($data['id']) === 2) {
            $this->handle_webinar_registration_form_2($data);
        }
        
        // Handle membership registration for form 15
        if (isset($data['id']) && intval($data['id']) === 15) {
            $this->handle_membership_registration($data);
        }
        
        return $data;
    }
    
    /**
     * Fix array merge issues in Ninja Forms
     *
     * @param array|null $array1 First array
     * @param array|null $array2 Second array
     * @return array Merged array
     */
    public function fix_array_merge($array1, $array2) {
        // Ensure both parameters are arrays
        $array1 = is_array($array1) ? $array1 : [];
        $array2 = is_array($array2) ? $array2 : [];
        
        // Merge arrays safely
        return array_merge($array1, $array2);
    }
    
    /**
     * Handle webinar registration for Form ID 2
     */
    private function handle_webinar_registration_form_2($form_data) {
        $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
            ? DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'
            : dirname(__FILE__) . '/logs/live-webinar-registration-debug.log';
        
        $debug_id = 'NF2-' . uniqid();
        file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] === FORM ID 2 SUBMISSION CAPTURED (SUBMIT_DATA FILTER) ===\n", FILE_APPEND | LOCK_EX);
        file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Debug ID: $debug_id\n", FILE_APPEND | LOCK_EX);
        file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Form Data: " . print_r($form_data, true) . "\n", FILE_APPEND | LOCK_EX);
        
        // Extract fields from Ninja Forms data
        $fields = $form_data['fields'] ?? [];
        $flat = [];
        foreach ($fields as $field) {
            if (isset($field['key'])) {
                $flat[$field['key']] = $field['value'] ?? '';
            }
        }
        
        file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Extracted fields: " . print_r($flat, true) . "\n", FILE_APPEND | LOCK_EX);
        
        // Get post ID from form data or extract from current context
        $post_id = $flat['post_id'] ?? null;
        if (!$post_id && isset($_SERVER['HTTP_REFERER'])) {
            if (preg_match('/\/webinars\/([^\/]+)\//i', $_SERVER['HTTP_REFERER'], $matches)) {
                $post = get_page_by_path($matches[1], OBJECT, 'webinars');
                if ($post) {
                    $post_id = $post->ID;
                    file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Extracted post_id from referrer: $post_id\n", FILE_APPEND | LOCK_EX);
                }
            }
        }
        
        if (!$post_id) {
            file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] ERROR: Could not determine post_id for webinar registration\n", FILE_APPEND | LOCK_EX);
            return;
        }
        
        // Prepare registration data
        $registration_data = [
            'post_id' => $post_id,
            'speaker_question' => $flat['question_for_speaker'] ?? '',
            'cf_mailing_list_member_sponsor_1_optin' => !empty($flat['cf_mailing_list_member_sponsor_1_optin']) ? 1 : 0
        ];
        
        file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Registration data prepared: " . print_r($registration_data, true) . "\n", FILE_APPEND | LOCK_EX);
        
        // Call the webinar registration handler
        if (file_exists(dirname(__FILE__) . '/includes/form-handler-live-webinar-registration.php')) {
            require_once(dirname(__FILE__) . '/includes/form-handler-live-webinar-registration.php');
            
            if (function_exists('dtr_handle_live_webinar_registration')) {
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Calling dtr_handle_live_webinar_registration\n", FILE_APPEND | LOCK_EX);
                $result = dtr_handle_live_webinar_registration($registration_data);
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] Registration result: " . print_r($result, true) . "\n", FILE_APPEND | LOCK_EX);
                
                // Register user locally if successful
                if (!empty($result['success']) && is_user_logged_in()) {
                    if (function_exists('register_user_for_event')) {
                        register_user_for_event(get_current_user_id(), $post_id);
                        file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] User registered locally for event $post_id\n", FILE_APPEND | LOCK_EX);
                    }
                }
            } else {
                file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] ERROR: dtr_handle_live_webinar_registration function not found\n", FILE_APPEND | LOCK_EX);
            }
        } else {
            file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] ERROR: Live webinar registration handler file not found\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Handle membership registration processing for form 15
     *
     * @param array $form_data Form submission data
     * @return void
     */
    private function handle_membership_registration($form_data) {
        try {
            $debug_id = 'REG-' . uniqid();
            $this->log_membership_registration("[{$debug_id}] ====== MEMBER REGISTRATION ======");
            $this->log_membership_registration("[{$debug_id}] [ENTRY] Processing membership registration for form 15");

            // Extract field data
            $fields = $form_data['fields'] ?? [];
            $flat = [];
            foreach ($fields as $field) {
                if (isset($field['key'])) $flat[$field['key']] = $field['value'] ?? '';
                if (isset($field['id'])) $flat[$field['id']] = $field['value'] ?? '';
            }

            // Collect and validate data
            $data = [
                'email' => sanitize_email($this->get_field_value($flat, ['email_address', '144'])),
                'password' => $this->get_field_value($flat, ['password', '221']),
                'first_name' => sanitize_text_field($this->get_field_value($flat, ['first_name', '142'])),
                'last_name' => sanitize_text_field($this->get_field_value($flat, ['last_name', '143'])),
                'employer' => sanitize_text_field($this->get_field_value($flat, ['employer', '218'])),
                'title' => sanitize_text_field($this->get_field_value($flat, ['title', '141'])),
                'telephone' => sanitize_text_field($this->get_field_value($flat, ['telephone', '146'])),
                'country' => sanitize_text_field($this->get_field_value($flat, ['country', '148'], 'South Africa')),
                'town' => sanitize_text_field($this->get_field_value($flat, ['town', '149'])),
                'postcode' => sanitize_text_field($this->get_field_value($flat, ['postcode', '150'])),
                'job_title' => sanitize_text_field($this->get_field_value($flat, ['job_title', '147'])),
                'marketing_selected' => $this->to_array($this->get_field_value($flat, ['marketing_preferences', '153'])),
                'toi_selected' => $this->to_array($this->get_field_value($flat, ['topics_of_interest', '154']))
            ];

            // 1. Block if WP user exists
            if (!$data['email'] || !is_email($data['email']) || !$data['password'] || !$data['first_name'] || !$data['last_name']) {
                $this->log_membership_registration("[{$debug_id}] [ERROR] Missing required fields");
                return;
            }
            if (username_exists($data['email']) || email_exists($data['email'])) {
                $this->log_membership_registration("[{$debug_id}] [WARNING] User already exists for email: {$data['email']}");
                // TODO: Optionally trigger front-end error here (Ninja Forms validation)
                return;
            }

            // 2. Check Workbooks for existing person
            $workbooks = get_workbooks_instance();
            $workbooks_person_id = null;
            $workbooks_ref = null;
            $duplicate_workbooks = false;
            if ($workbooks) {
                try {
                    $this->log_membership_registration("[{$debug_id}] [DEBUG] Checking Workbooks for existing person with email: {$data['email']}");
                    $search = $workbooks->assertGet('crm/people', ['main_location[email]' => $data['email'], '_limit' => 1]);
                    if (!empty($search['data'][0]['id'])) {
                        $found_email = strtolower(trim($search['data'][0]['main_location[email]'] ?? ''));
                        $input_email = strtolower(trim($data['email']));
                        if ($found_email === $input_email) {
                            $workbooks_person_id = $search['data'][0]['id'];
                            $workbooks_ref = $search['data'][0]['object_ref'] ?? '';
                            $duplicate_workbooks = true;
                            $this->log_membership_registration("[{$debug_id}] [DUPLICATE] Person already exists in Workbooks with ID: {$workbooks_person_id}, ref: {$workbooks_ref}");
                        } else {
                            $this->log_membership_registration("[{$debug_id}] [DEBUG] Workbooks search returned a person, but email did not match exactly. Found: '{$found_email}', Expected: '{$input_email}'");
                        }
                    }
                } catch (Exception $e) {
                    $this->log_membership_registration("[{$debug_id}] [ERROR] Workbooks search failed: " . $e->getMessage());
                }
            } else {
                $this->log_membership_registration("[{$debug_id}] [WARNING] Workbooks API instance not available");
            }

            // 3. Create WP user
            $user_id = wp_create_user($data['email'], $data['password'], $data['email']);
            if (is_wp_error($user_id)) {
                $this->log_membership_registration("[{$debug_id}] [ERROR] WP user creation failed: " . $user_id->get_error_message());
                return;
            }
            $this->log_membership_registration("[{$debug_id}] [SUCCESS] WP user created with ID: {$user_id}");

            // Set core user meta
            $core_meta = [
                'created_via_ninja_form' => 1,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'employer' => $data['employer'],
                'employer_name' => $data['employer'],
                'cf_person_claimed_employer' => $data['employer'],
                'person_personal_title' => $data['title'],
                'telephone' => $data['telephone'],
                'country' => $data['country'],
                'town' => $data['town'],
                'postcode' => $data['postcode'],
                'job_title' => $data['job_title'],
                'cf_person_dtr_subscriber_type' => 'Prospect',
                'cf_person_dtr_web_member' => 1,
                'lead_source_type' => 'Online Registration',
                'cf_person_is_person_active_or_inactive' => 'Active',
                'cf_person_data_source_detail' => 'DTR Web Member Signup'
            ];
            foreach ($core_meta as $key => $value) {
                update_user_meta($user_id, $key, $value);
            }

            // Apply marketing preferences
            $marketing_fields = ['cf_person_dtr_news', 'cf_person_dtr_events', 'cf_person_dtr_third_party', 'cf_person_dtr_webinar'];
            foreach ($marketing_fields as $mf) {
                update_user_meta($user_id, $mf, in_array($mf, $data['marketing_selected'], true) ? 1 : 0);
            }

            // Apply topics of interest
            $toi_fields = ['cf_person_business', 'cf_person_diseases', 'cf_person_drugs_therapies', 'cf_person_genomics_3774', 'cf_person_research_development', 'cf_person_technology', 'cf_person_tools_techniques'];
            foreach ($toi_fields as $tf) {
                update_user_meta($user_id, $tf, in_array($tf, $data['toi_selected'], true) ? 1 : 0);
            }

            // 4. Link to Workbooks (existing or new)
            if ($workbooks && $duplicate_workbooks) {
                update_user_meta($user_id, 'workbooks_person_id', $workbooks_person_id);
                if ($workbooks_ref) update_user_meta($user_id, 'workbooks_object_ref', $workbooks_ref);
                update_user_meta($user_id, 'workbooks_existing_person', 1);
                $this->log_membership_registration("[{$debug_id}] [LINKED] Linked WP user to existing Workbooks person ID: {$workbooks_person_id}");
            } elseif ($workbooks) {
                // Create new Workbooks person
                $this->log_membership_registration("[{$debug_id}] [DEBUG] No existing Workbooks person found - creating new");
                $this->sync_to_workbooks($workbooks, $user_id, $data, $debug_id);
            }

            // Log detailed summary
            $this->log_detailed_summary($user_id, $data, $debug_id);

        } catch (Exception $e) {
            $this->log_membership_registration('[FATAL] Exception in membership registration: ' . $e->getMessage());
        }
    }
    
    /**
     * Get field value from flattened fields array
     *
     * @param array $flat Flattened fields
     * @param array $candidates Field keys to check
     * @param string $default Default value
     * @return string
     */
    private function get_field_value($flat, $candidates, $default = '') {
        foreach ($candidates as $key) {
            if (isset($flat[$key]) && $flat[$key] !== '') {
                return $flat[$key];
            }
        }
        return $default;
    }
    
    /**
     * Convert value to array
     *
     * @param mixed $val Value to convert
     * @return array
     */
    private function to_array($val) {
        if (is_array($val)) return $val;
        if ($val === '' || $val === null) return [];
        return [$val];
    }
    
    /**
     * Sync user to Workbooks
     *
     * @param object $workbooks Workbooks API instance
     * @param int $user_id WordPress user ID
     * @param array $data User data
     * @param string $debug_id Debug ID for logging
     * @return void
     */
    private function sync_to_workbooks($workbooks, $user_id, $data, $debug_id) {
        try {
            $this->log_membership_registration("[{$debug_id}] [DEBUG] Starting Workbooks sync");
            $this->log_membership_registration("[{$debug_id}] [DEBUG] Searching for existing person with email: {$data['email']}");
            
            // Check for existing person
            $search = $workbooks->assertGet('crm/people', ['main_location[email]' => $data['email'], '_limit' => 1]);
            if (!empty($search['data'][0]['id'])) {
                $found_email = strtolower(trim($search['data'][0]['main_location[email]'] ?? ''));
                $input_email = strtolower(trim($data['email']));
                if ($found_email === $input_email) {
                    $existing_id = $search['data'][0]['id'];
                    $existing_ref = $search['data'][0]['object_ref'] ?? '';
                    $this->log_membership_registration("[{$debug_id}] [DUPLICATE] Person already exists in Workbooks with ID: {$existing_id}, ref: {$existing_ref}");
                    $this->log_membership_registration("[{$debug_id}] [DUPLICATE] Linking WordPress user {$user_id} to existing Workbooks person {$existing_id}");
                    update_user_meta($user_id, 'workbooks_person_id', $existing_id);
                    if ($existing_ref) update_user_meta($user_id, 'workbooks_object_ref', $existing_ref);
                    update_user_meta($user_id, 'workbooks_existing_person', 1);
                    $this->log_membership_registration("[{$debug_id}] [DUPLICATE] No new Workbooks person created - linked to existing");
                    return;
                } else {
                    $this->log_membership_registration("[{$debug_id}] [DEBUG] Workbooks search returned a person, but email did not match exactly. Found: '{$found_email}', Expected: '{$input_email}'");
                }
            }
            $this->log_membership_registration("[{$debug_id}] [DEBUG] No existing person found - creating new Workbooks person");
            
            // Build Workbooks payload
            $employer = isset($data['employer']) ? sanitize_text_field($data['employer']) : '';
            $payload = [
                'person_first_name' => $data['first_name'],
                'person_last_name' => $data['last_name'],
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'main_location[email]' => $data['email'],
                'created_through_reference' => 'wp_user_' . $user_id,
                'person_personal_title' => $data['title'],
                'person_job_title' => $data['job_title'],
                'main_location[telephone]' => $data['telephone'],
                'main_location[country]' => $data['country'],
                'main_location[town]' => $data['town'],
                'main_location[postcode]' => $data['postcode'],
                'employer_name' => $employer,
                'cf_person_claimed_employer' => $employer,
                'cf_person_dtr_subscriber_type' => 'Prospect',
                // 'cf_person_dtr_subscriber' => 1, // Commented out as requested
                'cf_person_dtr_web_member' => 1,
                'lead_source_type' => 'Online Registration',
                'cf_person_is_person_active_or_inactive' => 'Active',
                'cf_person_data_source_detail' => 'DTR Web Member Signup'
            ];
            
            // Add marketing preferences
            $marketing_fields = ['cf_person_dtr_news', 'cf_person_dtr_events', 'cf_person_dtr_third_party', 'cf_person_dtr_webinar'];
            foreach ($marketing_fields as $mf) {
                $payload[$mf] = in_array($mf, $data['marketing_selected'], true) ? 1 : 0;
            }

            // Add topics of interest (TOI)
            $toi_fields = ['cf_person_business', 'cf_person_diseases', 'cf_person_drugs_therapies', 'cf_person_genomics_3774', 'cf_person_research_development', 'cf_person_technology', 'cf_person_tools_techniques'];
            foreach ($toi_fields as $tf) {
                $payload[$tf] = in_array($tf, $data['toi_selected'], true) ? 1 : 0;
            }

            // Add AOI mapping (Areas of Interest) based on TOI selection using centralized mapping
            $aoi_debug = [];
            if (function_exists('dtr_get_toi_to_aoi_matrix') && function_exists('dtr_get_aoi_field_names') && function_exists('dtr_normalize_toi_key')) {
                $normalized_selected = array_map('dtr_normalize_toi_key', $data['toi_selected']);
                $matrix = dtr_get_toi_to_aoi_matrix();
                $aoi_fields = array_keys(dtr_get_aoi_field_names());
                $aoi_map = array_fill_keys($aoi_fields, 0);
                foreach ($normalized_selected as $toi_field) {
                    if (isset($matrix[$toi_field])) {
                        foreach ($matrix[$toi_field] as $aoi_field => $value) {
                            $aoi_map[$aoi_field] = $value;
                        }
                    }
                }
                foreach ($aoi_map as $aoi_key => $val) {
                    if ($val) {
                        $payload[$aoi_key] = 1;
                        $aoi_debug[] = $aoi_key;
                    }
                }
            }
            
            // Create person in Workbooks
            $this->log_membership_registration("[{$debug_id}] [DEBUG] Sending create request to Workbooks API");
            $payloads = [$payload];
            $resp = $workbooks->assertCreate('crm/people', $payloads);
            
            if (!empty($resp['affected_objects'][0]['id'])) {
                $person_id = $resp['affected_objects'][0]['id'];
                $person_ref = $resp['affected_objects'][0]['object_ref'] ?? '';
                
                update_user_meta($user_id, 'workbooks_person_id', $person_id);
                if ($person_ref) update_user_meta($user_id, 'workbooks_object_ref', $person_ref);
                
                $this->log_membership_registration("[{$debug_id}] [SUCCESS] NEW person created in Workbooks with ID: {$person_id}, ref: {$person_ref}");
            } else {
                $this->log_membership_registration("[{$debug_id}] [ERROR] Workbooks create response missing ID - response: " . wp_json_encode($resp));
            }
            
        } catch (Exception $e) {
            $this->log_membership_registration("[{$debug_id}] [ERROR] Workbooks sync failed: " . $e->getMessage());
            // Don't rollback WP user on Workbooks failure - just log it
        }
    }
    
    /**
     * Log detailed registration summary
     *
     * @param int $user_id WordPress user ID
     * @param array $data User data
     * @param string $debug_id Debug ID for logging
     * @return void
     */
    private function log_detailed_summary($user_id, $data, $debug_id) {
        $marketing_map = [
            'cf_person_dtr_news' => 'Newsletter',
            'cf_person_dtr_events' => 'Event',
            'cf_person_dtr_third_party' => 'Third party',
            'cf_person_dtr_webinar' => 'Webinar'
        ];
        
        $toi_map = [
            'cf_person_business' => 'Business',
            'cf_person_diseases' => 'Diseases',
            'cf_person_drugs_therapies' => 'Drugs & Therapies',
            'cf_person_genomics_3774' => 'Genomics',
            'cf_person_research_development' => 'Research & Development',
            'cf_person_technology' => 'Technology',
            'cf_person_tools_techniques' => 'Tools & Techniques'
        ];
        
        $workbooks_person_id = get_user_meta($user_id, 'workbooks_person_id', true);
        $workbooks_ref = get_user_meta($user_id, 'workbooks_object_ref', true);
        $duplicate_flag = (int) get_user_meta($user_id, 'workbooks_existing_person', true) === 1;

        $this->log_membership_registration('==== MEMBER REGISTRATION =====');
        $this->log_membership_registration('User Details:');
        $this->log_membership_registration('First Name: ' . ($data['first_name'] ?? ''));
        $this->log_membership_registration('Last Name: ' . ($data['last_name'] ?? ''));
        $this->log_membership_registration('Email Address: ' . ($data['email'] ?? ''));
        $this->log_membership_registration('Telephone Number: ' . ($data['telephone'] ?? ''));
        $this->log_membership_registration('Job Title: ' . ($data['job_title'] ?? ''));
        $this->log_membership_registration('Employer: ' . ($data['employer'] ?? ''));
        $this->log_membership_registration('Country: ' . ($data['country'] ?? ''));
        $this->log_membership_registration('Town: ' . ($data['town'] ?? ''));
        $this->log_membership_registration('Post Code: ' . ($data['postcode'] ?? ''));
        $this->log_membership_registration('');
        $this->log_membership_registration('---- MARKETING COMMUNICATION ----');

        $marketing_summary = [];
        foreach ($marketing_map as $meta_key => $label) {
            $val = (int) get_user_meta($user_id, $meta_key, true) === 1 ? 'Yes' : 'No';
            $this->log_membership_registration($label . ': - ' . $val);
            $marketing_summary[] = $label . '=' . $val;
        }

        $this->log_membership_registration('');
        $this->log_membership_registration('---- TOPICS OF INTEREST ----');

        $toi_summary = [];
        foreach ($toi_map as $meta_key => $label) {
            $val = (int) get_user_meta($user_id, $meta_key, true) === 1 ? 'Yes' : 'No';
            $this->log_membership_registration($label . ': - ' . $val);
            $toi_summary[] = $label . '=' . $val;
        }

        $this->log_membership_registration('');
        $this->log_membership_registration('---- AREAS OF INTEREST ----');
        // AOI debug output
        if (function_exists('dtr_map_toi_to_aoi')) {
            $aoi_map = dtr_map_toi_to_aoi($data['toi_selected']);
            $aoi_summary = [];
            foreach ($aoi_map as $aoi_key => $val) {
                if ($val) {
                    $this->log_membership_registration($aoi_key . ': - Yes');
                    $aoi_summary[] = $aoi_key . '=Yes';
                } else {
                    $aoi_summary[] = $aoi_key . '=No';
                }
            }
        }

        $this->log_membership_registration('');
        $this->log_membership_registration('---- WORDPRESS/WORKBOOKS ----');
        $this->log_membership_registration("SUCCESS: WordPress user created with ID: {$user_id}");

        if ($workbooks_person_id) {
            if ($duplicate_flag) {
                $this->log_membership_registration("SUCCESS: Linked to existing Workbooks person with ID: {$workbooks_person_id}");
            } else {
                $this->log_membership_registration("SUCCESS: New Workbooks person created with ID: {$workbooks_person_id}");
            }
        } else {
            $this->log_membership_registration("WARNING: No Workbooks person ID available");
        }

        if ($workbooks_ref) $this->log_membership_registration('Workbooks Person Ref: ' . $workbooks_ref);
        if ($duplicate_flag) $this->log_membership_registration('Duplicate Detected: YES (existing person linked)');

        $this->log_membership_registration('');
        $this->log_membership_registration('Communication Preferences: ' . implode(', ', $marketing_summary));
        $this->log_membership_registration('Topics of Interest: ' . implode(', ', $toi_summary));
        if (isset($aoi_summary)) {
            $this->log_membership_registration('Areas of Interest: ' . implode(', ', $aoi_summary));
        }
        $this->log_membership_registration('');
        $this->log_membership_registration('==== MEMBER REGISTRATION SUCCESSFUL =====');
        $this->log_membership_registration('Member registration successful - Fucking Celebrate Good Times Come On!!!');
    }
    
    /**
     * Log membership registration messages
     *
     * @param string $message Message to log
     * @return void
     */
    private function log_membership_registration($message) {
        $timestamp = current_time('Y-m-d H:i:s');
        $log_line = "{$timestamp} [Membership-Reg] {$message}\n";
        
        // Log to membership registration debug file
        if (defined('DTR_WORKBOOKS_LOG_DIR')) {
            $log_file = DTR_WORKBOOKS_LOG_DIR . 'member-registration-debug.log';
            file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        }
        
        // Also log to main plugin log
        dtr_custom_log($message);
    }
    
    /**
     * Admin page for viewing and managing registered events and lead generation submissions
     */
    public function admin_registered_events_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access.');
        }
        
        global $wpdb;
        
        // Debug: Log that the admin page is being loaded
        error_log("Admin Page Debug: admin_registered_events_page() called");
        
        // Handle manual table creation request
        if (isset($_POST['create_tables'])) {
            check_admin_referer('create_tables', 'create_tables_nonce');
            $this->create_database_tables();
            echo '<div class="notice notice-success"><p>Database tables have been created/updated successfully.</p></div>';
        }
        
        // Handle request to recreate submissions table
        if (isset($_POST['recreate_submissions_table'])) {
            check_admin_referer('recreate_submissions_table', 'recreate_submissions_nonce');
            
            // Drop the table if it exists
            $table_name = $wpdb->prefix . 'dtr_workbooks_submissions';
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
            
            // Create the table
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                form_id int(11) NOT NULL,
                post_id bigint(20) NULL,
                status varchar(50) NOT NULL DEFAULT 'completed',
                submission_data longtext NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NULL,
                PRIMARY KEY (id),
                KEY form_id (form_id),
                KEY post_id (post_id),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            echo '<div class="notice notice-success"><p>Submissions table has been recreated successfully.</p></div>';
        }
        
        // Handle delete request for webinar registrations (function-based)
        if (isset($_POST['delete_registration']) && isset($_POST['user_id']) && isset($_POST['event_id'])) {
            check_admin_referer('delete_registration', 'delete_nonce');
            $user_id = intval($_POST['user_id']);
            $event_id = intval($_POST['event_id']);
            
            $registered_events = get_user_meta($user_id, 'registered_events', true);
            if (is_array($registered_events) && in_array($event_id, $registered_events)) {
                $registered_events = array_diff($registered_events, [$event_id]);
                update_user_meta($user_id, 'registered_events', $registered_events);
                echo '<div class="notice notice-success"><p>Function-based webinar registration deleted successfully.</p></div>';
            }
        }
        
        // Handle delete request for class-based webinar registrations
        if (isset($_POST['delete_class_registration']) && isset($_POST['user_id']) && isset($_POST['event_id']) && isset($_POST['registration_email'])) {
            check_admin_referer('delete_class_registration', 'delete_class_nonce');
            $user_id = intval($_POST['user_id']);
            $event_id = intval($_POST['event_id']);
            $registration_email = sanitize_email($_POST['registration_email']);
            $registration_date = sanitize_text_field($_POST['registration_date'] ?? '');
            
            // Remove from user meta (if user exists)
            if ($user_id > 0) {
                $user_registration_key = 'webinar_registration_' . $event_id;
                delete_user_meta($user_id, $user_registration_key);
            }
            
            // Remove from post meta - match by email and optionally by registration_date for uniqueness
            $all_registrations = get_post_meta($event_id, 'webinar_registrations', false);
            $removed = false;
            foreach ($all_registrations as $registration) {
                $match = false;
                
                // Try to match by email and registration date if both are available
                if (isset($registration['email']) && $registration['email'] === $registration_email) {
                    if ($registration_date && isset($registration['registration_date'])) {
                        $match = ($registration['registration_date'] === $registration_date);
                    } else {
                        $match = true; // Match by email only if no date provided
                    }
                }
                
                if ($match) {
                    delete_post_meta($event_id, 'webinar_registrations', $registration);
                    $removed = true;
                    error_log("Admin Page Debug: Deleted class-based registration for email {$registration_email} from post {$event_id}");
                    break; // Only delete the first match to avoid removing multiple registrations
                }
            }
            
            if ($removed) {
                echo '<div class="notice notice-success"><p>Class-based webinar registration deleted successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to delete class-based webinar registration.</p></div>';
            }
        }
        
        // Handle delete request for lead generation submissions
        if (isset($_POST['delete_lead_submission']) && isset($_POST['submission_id']) && isset($_POST['user_id'])) {
            check_admin_referer('delete_lead_submission', 'delete_lead_nonce');
            $submission_id = intval($_POST['submission_id']);
            $user_id = intval($_POST['user_id']);
            $form_id = intval($_POST['form_id'] ?? 0);
            $post_id = intval($_POST['post_id'] ?? 0);
            
            // Delete from submissions table
            $submissions_table = $wpdb->prefix . 'dtr_workbooks_submissions';
            $deleted = $wpdb->delete($submissions_table, ['id' => $submission_id]);
            
            if ($deleted !== false) {
                // Clean up user meta
                if ($form_id && $post_id) {
                    delete_user_meta($user_id, "completed_form_{$form_id}_{$post_id}");
                }
                
                // Update completed ninja forms array
                $completed_forms = get_user_meta($user_id, 'completed_ninja_forms', true);
                if (is_array($completed_forms)) {
                    if ($post_id) {
                        $completed_forms = array_filter($completed_forms, function($item) use ($post_id) {
                            return !isset($item['post_id']) || $item['post_id'] != $post_id;
                        });
                    }
                    update_user_meta($user_id, 'completed_ninja_forms', $completed_forms);
                }
                
                echo '<div class="notice notice-success"><p>Lead generation submission deleted successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to delete lead generation submission.</p></div>';
            }
        }
        
        // Get all users with registered events (webinars) - Function-based system
        $users_with_events = get_users([
            'meta_key' => 'registered_events',
            'meta_compare' => 'EXISTS'
        ]);
        
        // Get all class-based webinar registrations from post meta
        $class_based_registrations = [];
        
        // First, find all posts that have webinar_registrations meta using direct DB query
        $posts_with_registrations = $wpdb->get_results("
            SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'webinar_registrations'
        ");
        
        error_log("Admin Page Debug: Direct DB query found " . count($posts_with_registrations) . " posts with webinar_registrations meta");
        
        $webinar_posts = [];
        foreach ($posts_with_registrations as $result) {
            $post = get_post($result->post_id);
            if ($post && $post->post_status === 'publish') {
                $webinar_posts[] = $post;
                error_log("Admin Page Debug: Added post {$post->ID} ({$post->post_title}) to webinar_posts array");
            }
        }
        
        foreach ($webinar_posts as $post) {
            $registrations = get_post_meta($post->ID, 'webinar_registrations', false);
            // Debug: log what we're finding
            error_log("Admin Page Debug: Found post {$post->ID} ({$post->post_title}) with " . count($registrations) . " registration entries");
            foreach ($registrations as $registration) {
                $user = null;
                
                // Try to find user by user_id first (for compatibility with old format)
                if (isset($registration['user_id'])) {
                    $user = get_user_by('ID', $registration['user_id']);
                    if ($user) {
                        error_log("Admin Page Debug: Found user by user_id {$registration['user_id']} - {$user->user_email}");
                    }
                }
                
                // If no user found by user_id, try to find by email (new class-based format)
                if (!$user && isset($registration['email'])) {
                    $user = get_user_by('email', $registration['email']);
                    if ($user) {
                        error_log("Admin Page Debug: Found user by email {$registration['email']} - User ID {$user->ID}");
                    } else {
                        error_log("Admin Page Debug: No WordPress user found for email {$registration['email']}");
                    }
                }
                
                if ($user) {
                    error_log("Admin Page Debug: Adding registration for user {$user->user_email} (ID: {$user->ID}) on post {$post->ID}");
                    $class_based_registrations[] = [
                        'user' => $user,
                        'post' => $post,
                        'registration_data' => $registration
                    ];
                } else {
                    error_log("Admin Page Debug: Could not identify user for registration: " . print_r($registration, true));
                }
            }
        }
        
        // Get all lead generation submissions from database
        $submissions_table = $wpdb->prefix . 'dtr_workbooks_submissions';
        $lead_submissions = $wpdb->get_results("
            SELECT s.*, u.display_name, u.user_email, s.submission_data
            FROM $submissions_table s
            LEFT JOIN {$wpdb->users} u ON JSON_EXTRACT(s.submission_data, '$.user_id') = u.ID
            ORDER BY s.created_at DESC
        ");
        
        // Handle potential SQL errors
        if ($wpdb->last_error) {
            echo '<div class="notice notice-error"><p>Database error: ' . esc_html($wpdb->last_error) . '</p></div>';
            $lead_submissions = [];
        }
        
        // Parse submission data to extract form and post IDs
        foreach ($lead_submissions as $submission) {
            $data = json_decode($submission->submission_data, true);
            $submission->user_id = $data['user_id'] ?? null;
            $submission->post_id = $data['post_id'] ?? null;
            $submission->form_fields = $data;
            
            // Get user details if user_id is available but display_name is null
            if ($submission->user_id && !$submission->display_name) {
                $user = get_user_by('ID', $submission->user_id);
                if ($user) {
                    $submission->display_name = $user->display_name;
                    $submission->user_email = $user->user_email;
                }
            }
        }
        
        ?>
        <div class="wrap plugin-admin-content">
            <h1>Registered Events & Lead Generation</h1>
            <h2>View and manage user registrations for webinars and lead generation submissions.</h2>
            
            <!-- Debug Info -->
            <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">
                <strong>Debug Info:</strong><br>
                Function-based registrations: <?php echo count($users_with_events); ?><br>
                Class-based registrations: <?php echo count($class_based_registrations); ?><br>
                Lead submissions: <?php echo count($lead_submissions); ?><br>
                Posts with webinar_registrations meta: <?php echo count($posts_with_registrations ?? []); ?><br>
                Webinar posts (filtered): <?php echo count($webinar_posts ?? []); ?><br>
                
                <?php if (!empty($webinar_posts)): ?>
                <br><strong>Webinar Posts Found:</strong><br>
                <?php foreach ($webinar_posts as $post): ?>
                    <?php 
                    $registrations = get_post_meta($post->ID, 'webinar_registrations', false);
                    ?>
                    Post ID <?php echo $post->ID; ?>: "<?php echo esc_html($post->post_title); ?>" 
                    (<?php echo count($registrations); ?> registration entries)<br>
                    <?php if (!empty($registrations)): ?>
                        <?php foreach ($registrations as $i => $reg): ?>
                            &nbsp;&nbsp;Registration <?php echo $i; ?>: 
                            <?php 
                            $debug_user = null;
                            if (isset($reg['user_id'])) {
                                $debug_user = get_user_by('ID', $reg['user_id']);
                            } elseif (isset($reg['email'])) {
                                $debug_user = get_user_by('email', $reg['email']);
                            }
                            ?>
                            
                            <?php if ($debug_user): ?>
                                User: <?php echo esc_html($debug_user->user_email); ?> (ID: <?php echo $debug_user->ID; ?>)
                                <?php if (isset($reg['email'])): ?>
                                    - Email: <?php echo esc_html($reg['email']); ?>
                                <?php endif; ?>
                            <?php elseif (isset($reg['email'])): ?>
                                Email: <?php echo esc_html($reg['email']); ?> (No WP user found)
                            <?php else: ?>
                                No user identification - Contents: <?php echo esc_html(print_r($reg, true)); ?>
                            <?php endif; ?>
                            <br>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="margin-bottom: 20px;">
                <h3>Maintenance Tools</h3>
                <form method="post" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('recreate_submissions_table', 'recreate_submissions_nonce'); ?>
                    <input type="submit" name="recreate_submissions_table" value="Recreate Submissions Table" class="button button-secondary" onclick="return confirm('This will delete all existing lead generation submission records and recreate the table. Are you sure?');">
                </form>
                
                <p><small>Use these tools to fix database issues. The "Recreate Submissions Table" button will drop and recreate the lead generation submissions table.</small></p>
            </div>

            <!-- Combined Events & Lead Generation Section -->
            <div>
                <h3>All Registrations & Submissions</h3>
                
                <?php 
                // Check if all arrays are empty
                if (empty($users_with_events) && empty($class_based_registrations) && empty($lead_submissions)): 
                ?>
                    <div class="notice notice-info">
                        <p>No registrations or submissions found.</p>
                    </div>
                <?php else: ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Event/Form Title</th>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // First add function-based webinar registrations
                        foreach ($users_with_events as $user): 
                            $registered_events = get_user_meta($user->ID, 'registered_events', true);
                            $registered_events = is_array($registered_events) ? $registered_events : [];
                            
                            foreach ($registered_events as $event_id): 
                                $post = get_post($event_id);
                                $event_title = $post ? $post->post_title : 'Event not found';
                                $reg_date = get_user_meta($user->ID, 'event_' . $event_id . '_registration_date', true);
                                $date_display = $reg_date ? date('Y-m-d H:i:s', $reg_date) : 'N/A';
                        ?>
                            <tr>
                                <td><span class="badge" style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px;">Webinar (Function)</span></td>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <?php if ($post): ?>
                                        <a href="<?php echo get_edit_post_link($event_id); ?>" target="_blank">
                                            <?php echo esc_html($event_title); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html($event_title); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($event_id); ?></td>
                                <td><span class="status-completed">Registered</span></td>
                                <td><?php echo esc_html($date_display); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('delete_registration', 'delete_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                        <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                                        <input type="submit" name="delete_registration" value="Delete" 
                                               class="button button-secondary" 
                                               onclick="return confirm('Are you sure you want to delete this function-based registration?');">
                                    </form>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        endforeach; 
                        
                        // Add class-based webinar registrations
                        foreach ($class_based_registrations as $reg): 
                            $user = $reg['user'];
                            $post = $reg['post'];
                            $registration_data = $reg['registration_data'];
                            $date_display = isset($registration_data['registration_date']) ? $registration_data['registration_date'] : 'N/A';
                        ?>
                            <tr>
                                <td><span class="badge" style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px;">Webinar (Class)</span></td>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($post->ID); ?></td>
                                <td><span class="status-completed">Registered</span></td>
                                <td><?php echo esc_html($date_display); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('delete_class_registration', 'delete_class_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                        <input type="hidden" name="event_id" value="<?php echo esc_attr($post->ID); ?>">
                                        <input type="hidden" name="registration_email" value="<?php echo esc_attr($registration_data['email'] ?? $user->user_email); ?>">
                                        <input type="hidden" name="registration_date" value="<?php echo esc_attr($registration_data['registration_date'] ?? ''); ?>">
                                        <input type="submit" name="delete_class_registration" value="Delete" 
                                               class="button button-secondary" 
                                               onclick="return confirm('Are you sure you want to delete this class-based registration?');">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; 
                        
                        // Then add lead generation submissions
                        foreach ($lead_submissions as $submission): 
                            $post_title = 'Unknown';
                            if ($submission->post_id) {
                                $post = get_post($submission->post_id);
                                $post_title = $post ? $post->post_title : "Post ID {$submission->post_id} (not found)";
                            }
                        ?>
                            <tr>
                                <td><span class="badge" style="background: #d58c2d; color: white; padding: 2px 6px; border-radius: 3px;">Lead Generation</span></td>
                                <td><?php echo esc_html($submission->display_name ?: 'Unknown User'); ?></td>
                                <td><?php echo esc_html($submission->user_email ?: 'Unknown Email'); ?></td>
                                <td>
                                    <?php if ($submission->post_id && $post): ?>
                                        <a href="<?php echo get_edit_post_link($submission->post_id); ?>" target="_blank">
                                            <?php echo esc_html($post_title); ?>
                                        </a>
                                        <br><small>Form ID: <?php echo esc_html($submission->form_id); ?></small>
                                    <?php else: ?>
                                        <?php echo esc_html($post_title); ?>
                                        <br><small>Form ID: <?php echo esc_html($submission->form_id); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($submission->id); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($submission->status); ?>"><?php echo esc_html(ucfirst($submission->status)); ?></span>
                                </td>
                                <td><?php echo esc_html($submission->created_at); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('delete_lead_submission', 'delete_lead_nonce'); ?>
                                        <input type="hidden" name="submission_id" value="<?php echo esc_attr($submission->id); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($submission->user_id); ?>">
                                        <input type="hidden" name="form_id" value="<?php echo esc_attr($submission->form_id); ?>">
                                        <input type="hidden" name="post_id" value="<?php echo esc_attr($submission->post_id); ?>">
                                        <input type="submit" name="delete_lead_submission" value="Delete" 
                                               class="button button-secondary" 
                                               onclick="return confirm('Are you sure you want to delete this submission?');">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <br>
            <div class="card">
                <h2>Usage Notes</h2>
                <h4>Types of Records</h4>
                <ul>
                    <li><span class="badge" style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px;">Webinar (Function)</span> - Function-based webinar registrations using [dtr_webinar_registration]</li>
                    <li><span class="badge" style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px;">Webinar (Class)</span> - Class-based webinar registrations using [dtr_webinar_registration_form]</li>
                    <li><span class="badge" style="background: #d58c2d; color: white; padding: 2px 6px; border-radius: 3px;">Lead Generation</span> - Form submissions for lead generation campaigns</li>
                </ul>
                <h4>Data Storage</h4>
                <ul>
                    <li>Function-based webinar registrations are tracked in WordPress user meta under 'registered_events'</li>
                    <li>Class-based webinar registrations are stored in both user meta ('webinar_registration_{post_id}') and post meta ('webinar_registrations')</li>
                    <li>Lead generation submissions are stored in the custom database table 'dtr_workbooks_submissions'</li>
                    <li>All records are synced with Workbooks CRM when created</li>
                </ul>
                <h4>Important Notes</h4>
                <ul>
                    <li>Use the "Delete" button to remove records for testing purposes</li>
                    <li>Deleting records here only removes the local WordPress data - it does not affect Workbooks CRM records</li>
                    <li>The status column indicates the sync status with Workbooks CRM</li>
                </ul>
                
                <style>
                .status-pending { color: #d54e21; }
                .status-completed { color: #46b450; }
                .status-failed { color: #dc3232; }
                .status-processing { color: #ffb900; }
                .badge { font-size: 11px; font-weight: bold; text-transform: uppercase; }
                </style>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
DTR_Workbooks_Integration::get_instance();

/**
 * Get Workbooks API instance
 *
 * @return WorkbooksApi|false
 */
function get_workbooks_instance() {
    $debug_log = DTR_WORKBOOKS_PLUGIN_DIR . 'admin/connection-debug.log';
    
    try {
        $options = get_option('dtr_workbooks_options', []);
        $api_url = $options['api_url'] ?? '';
        $api_key = $options['api_key'] ?? '';
        
        // Ensure the admin directory exists
        $log_dir = dirname($debug_log);
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        dtr_admin_log(date('[Y-m-d H:i:s]') . " Getting options: API URL = {$api_url}, API Key = " . (empty($api_key) ? 'empty' : 'set') . "\n", 'connection-debug.log');

        if (empty($api_url) || empty($api_key)) {
            dtr_admin_log(date('[Y-m-d H:i:s]') . " Error: API credentials not configured\n", 'connection-debug.log');
            return false;
        }

        // Check if WorkbooksApi class exists
        if (!class_exists('WorkbooksApi')) {
            dtr_admin_log(date('[Y-m-d H:i:s]') . " Error: WorkbooksApi class not found\n", 'connection-debug.log');
            return false;
        }

        dtr_admin_log(date('[Y-m-d H:i:s]') . " Initializing WorkbooksApi...\n", 'connection-debug.log');
        
        // Initialize Workbooks API according to the official documentation
        $workbooks = new WorkbooksApi([
            'application_name' => 'DTR Workbooks Integration',
            'user_agent' => 'DTR-WordPress-Plugin/2.0.0',
            'service' => rtrim($api_url, '/'),
            'api_key' => $api_key,
            'verify_peer' => true,
            'connect_timeout' => $options['api_timeout'] ?? 30,
            'request_timeout' => $options['api_timeout'] ?? 30,
            'logger_callback' => ['WorkbooksApi', 'logAllToStdout'] // Enable logging for debugging
        ]);
        
        file_put_contents($debug_log, date('[Y-m-d H:i:s]') . " WorkbooksApi initialized successfully\n", FILE_APPEND);
        
        return $workbooks;
        
    } catch (Exception $e) {
        $error_msg = "Failed to initialize Workbooks API: " . $e->getMessage();
        file_put_contents($debug_log, date('[Y-m-d H:i:s]') . " Exception: {$error_msg}\n", FILE_APPEND);
        
        if (function_exists('dtr_custom_log')) {
            dtr_custom_log($error_msg, 'error');
        }
        
        return false;
    }
}

/**
 * Core Workbooks API functions
 */

/**
 * Create a person in Workbooks
 *
 * @param array $data Person data
 * @param string $debug_id Debug identifier
 * @return array Result array
 */
function dtr_create_workbooks_person($data, $debug_id = '') {
    $options = get_option('dtr_workbooks_options', []);
    $workbooks = get_workbooks_instance();
    
    if ($workbooks === false) {
        return ['success' => false, 'message' => 'Failed to initialize Workbooks API'];
    }
    
    try {
        // Prepare person data for Workbooks API
        $person_data = [
            'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
            'person_first_name' => $data['first_name'] ?? '',
            'person_last_name' => $data['last_name'] ?? '',
            'main_location[email]' => $data['email'] ?? '',
            'main_location[telephone]' => $data['phone'] ?? '',
            'organisation_name' => $data['company'] ?? '',
            'lead_source' => $data['lead_source'] ?? 'Website Form'
        ];
        
        // Remove empty fields
        $person_data = array_filter($person_data, function($value) {
            return !empty($value);
        });
        
        dtr_custom_log("Creating person in Workbooks: " . print_r($person_data, true));
        
        $person_payload = [$person_data];
        $response = $workbooks->assertCreate('crm/people', $person_payload);
        
        if (isset($response['affected_objects'][0]['id'])) {
            $person_id = $response['affected_objects'][0]['id'];
            dtr_custom_log("Person created successfully. ID: {$person_id}");
            return ['success' => true, 'person_id' => $person_id, 'data' => $response];
        } else {
            dtr_custom_log("Person creation failed: " . print_r($response, true), 'error');
            return ['success' => false, 'message' => 'Person creation failed'];
        }
        
    } catch (Exception $e) {
        dtr_custom_log("Person creation error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Create a ticket in Workbooks
 *
 * @param int $person_id Person ID
 * @param string $event_reference Event reference
 * @param string $debug_id Debug identifier
 * @return array Result array
 */
function dtr_create_workbooks_ticket($person_id, $event_reference, $debug_id = '') {
    $workbooks = get_workbooks_instance();
    
    if ($workbooks === false) {
        return ['success' => false, 'message' => 'Failed to initialize Workbooks API'];
    }
    
    try {
        $ticket_data = [
            'name' => 'Webinar Registration: ' . $event_reference,
            'party_id' => $person_id,
            'category' => 'Event Registration',
            'status' => 'Open',
            'description' => 'Webinar registration for: ' . $event_reference
        ];
        
        dtr_custom_log("Creating ticket in Workbooks: " . print_r($ticket_data, true));
        
        $ticket_payload = [$ticket_data];
        $response = $workbooks->assertCreate('crm/cases', $ticket_payload);
        
        if (isset($response['affected_objects'][0]['id'])) {
            $ticket_id = $response['affected_objects'][0]['id'];
            dtr_custom_log("Ticket created successfully. ID: {$ticket_id}");
            return ['success' => true, 'ticket_id' => $ticket_id, 'data' => $response];
        } else {
            dtr_custom_log("Ticket creation failed: " . print_r($response, true), 'error');
            return ['success' => false, 'message' => 'Ticket creation failed'];
        }
        
    } catch (Exception $e) {
        dtr_custom_log("Ticket creation error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Add person to mailing list
 *
 * @param array $data Person data
 * @param string $debug_id Debug identifier
 * @return array Result array
 */
function dtr_add_to_mailing_list($data, $debug_id = '') {
    $workbooks = get_workbooks_instance();
    
    if ($workbooks === false) {
        return ['success' => false, 'message' => 'Failed to initialize Workbooks API'];
    }
    
    try {
        // This would typically involve finding existing marketing lists and adding the person
        // For now, we'll just log the attempt
        dtr_custom_log("Adding to mailing list: " . ($data['email'] ?? 'No email provided'));
        
        // Implementation would depend on your specific Workbooks setup and marketing lists
        // Example: Find marketing list and add person as subscriber
        
        return ['success' => true, 'message' => 'Added to mailing list (placeholder implementation)'];
        
    } catch (Exception $e) {
        dtr_custom_log("Mailing list error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Process lead generation form
 *
 * @param array $form_data Form data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Success status
 */
function dtr_process_lead_generation($form_data, $form_id, $debug_id = '') {
    dtr_custom_log("Processing lead generation form {$form_id}");
    
    // Extract contact information
    $contact_data = [
        'email' => $form_data['email'] ?? '',
        'first_name' => $form_data['first_name'] ?? '',
        'last_name' => $form_data['last_name'] ?? '',
        'company' => $form_data['company'] ?? '',
        'phone' => $form_data['phone'] ?? '',
        'lead_source' => 'Website Form'
    ];
    
    // Create person in Workbooks
    $result = dtr_create_workbooks_person($contact_data, $debug_id);
    
    if ($result['success']) {
        // Add to mailing list
        dtr_add_to_mailing_list($contact_data, $debug_id);
        dtr_custom_log("Lead generation processing completed successfully");
        return true;
    } else {
        dtr_custom_log("Lead generation processing failed: " . $result['message'], 'error');
        return false;
    }
}

/**
 * Process user registration form
 *
 * @param array $form_data Form data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Success status
 */
// NOTE: The basic dtr_process_user_registration() implementation previously here
// has been removed to allow the enhanced version in includes/nf-user-register.php
// to load without a fatal redeclare error.
// All references (e.g. retry handler) now resolve to the enhanced implementation.

/**
 * Process webinar registration form
 *
 * @param array $form_data Form data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Success status
 */
function dtr_process_webinar_registration($form_data, $form_id, $debug_id = '') {
    dtr_custom_log("Processing webinar registration form {$form_id}");
    
    // Extract contact information from form data
    $contact_data = [
        'email' => $form_data['email'] ?? '',
        'first_name' => $form_data['first_name'] ?? '',
        'last_name' => $form_data['last_name'] ?? '',
        'company' => $form_data['company'] ?? '',
        'phone' => $form_data['phone'] ?? '',
        'lead_source' => 'Webinar Registration'
    ];
    
    // Fill missing user info from logged-in WordPress user
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        
        if (empty($contact_data['email'])) {
            $contact_data['email'] = $current_user->user_email;
            dtr_custom_log("Filled email from logged-in user: " . $contact_data['email']);
        }
        
        if (empty($contact_data['first_name'])) {
            $contact_data['first_name'] = get_user_meta($current_user->ID, 'first_name', true) ?: $current_user->display_name;
            dtr_custom_log("Filled first_name from logged-in user: " . $contact_data['first_name']);
        }
        
        if (empty($contact_data['last_name'])) {
            $contact_data['last_name'] = get_user_meta($current_user->ID, 'last_name', true);
            dtr_custom_log("Filled last_name from logged-in user: " . $contact_data['last_name']);
        }
    } else {
        dtr_custom_log("User not logged in - cannot fill missing contact data");
    }
    
    // Create person in Workbooks
    $result = dtr_create_workbooks_person($contact_data, $debug_id);
    
    if ($result['success']) {
        $person_id = $result['person_id'];
        
        // Create ticket for webinar registration
        $event_reference = $form_data['webinar_name'] ?? $form_data['event_name'] ?? 'Webinar';
        $ticket_result = dtr_create_workbooks_ticket($person_id, $event_reference, $debug_id);
        
        // Add to mailing list
        dtr_add_to_mailing_list($contact_data, $debug_id);
        
        dtr_custom_log("Webinar registration processing completed successfully");
        return true;
    } else {
        dtr_custom_log("Webinar registration processing failed: " . $result['message'], 'error');
        return false;
    }
}

/**
 * Store failed submission for retry
 *
 * @param int $form_id Form ID
 * @param array $submission_data Submission data
 * @param string $error_message Error message
 * @return void
 */
function dtr_store_failed_submission($form_id, $submission_data, $error_message) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'dtr_workbooks_submissions';
    
    $wpdb->insert(
        $table_name,
        [
            'form_id' => $form_id,
            'submission_data' => wp_json_encode($submission_data),
            'status' => 'failed',
            'error_message' => $error_message,
            'created_at' => current_time('mysql')
        ],
        ['%d', '%s', '%s', '%s', '%s']
    );
    
    if ($wpdb->last_error) {
        dtr_custom_log('Database error storing failed submission: ' . $wpdb->last_error, 'error');
    }
}

/**
 * Log sync operation
 *
 * @param string $operation Operation type
 * @param string $object_type Object type
 * @param string $object_id Object ID
 * @param string $status Status
 * @param array $request_data Request data
 * @param array $response_data Response data
 * @param string $error_message Error message
 * @param float $execution_time Execution time
 * @return void
 */
function dtr_log_sync_operation($operation, $object_type, $object_id, $status, $request_data = null, $response_data = null, $error_message = null, $execution_time = null) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'dtr_workbooks_sync_log';
    
    $wpdb->insert(
        $table_name,
        [
            'operation' => $operation,
            'object_type' => $object_type,
            'object_id' => $object_id,
            'status' => $status,
            'request_data' => $request_data ? wp_json_encode($request_data) : null,
            'response_data' => $response_data ? wp_json_encode($response_data) : null,
            'error_message' => $error_message,
            'execution_time' => $execution_time,
            'created_at' => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s']
    );
    
    if ($wpdb->last_error) {
        dtr_custom_log('Database error logging sync operation: ' . $wpdb->last_error, 'error');
    }
}

/**
 * Get Workbooks person by email
 *
 * @param string $email Email address
 * @return array|false Person data or false if not found
 */
function dtr_get_workbooks_person_by_email($email) {
    $workbooks = get_workbooks_instance();
    
    if ($workbooks === false) {
        return false;
    }
    
    try {
        $response = $workbooks->get('crm/people', [
            '_filters[]' => ['main_location[email]', 'eq', $email],
            '_limit' => 1,
            '_select_columns[]' => ['id', 'name', 'main_location[email]', 'lock_version']
        ]);
        
        if (isset($response['data']) && !empty($response['data'])) {
            return $response['data'][0];
        }
        
        return false;
        
    } catch (Exception $e) {
        dtr_custom_log("Error fetching person by email: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Update existing Workbooks person
 *
 * @param int $person_id Person ID
 * @param int $lock_version Lock version
 * @param array $data Update data
 * @return array Result array
 */
function dtr_update_workbooks_person($person_id, $lock_version, $data) {
    $workbooks = get_workbooks_instance();
    
    if ($workbooks === false) {
        return ['success' => false, 'message' => 'Failed to initialize Workbooks API'];
    }
    
    try {
        $update_data = array_merge($data, [
            'id' => $person_id,
            'lock_version' => $lock_version
        ]);
        
        dtr_custom_log("Updating person in Workbooks: " . print_r($update_data, true));
        
        $response = $workbooks->assertUpdate('crm/people', [$update_data]);
        
        if (isset($response['affected_objects'][0]['id'])) {
            dtr_custom_log("Person updated successfully. ID: {$person_id}");
            return ['success' => true, 'person_id' => $person_id, 'data' => $response];
        } else {
            dtr_custom_log("Person update failed: " . print_r($response, true), 'error');
            return ['success' => false, 'message' => 'Person update failed'];
        }
        
    } catch (Exception $e) {
        dtr_custom_log("Person update error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// --- Event Field Explorer (Improved) ---
add_action('admin_menu', function() {
    add_submenu_page(
        'dtr-workbooks',
        __('Event Field Explorer', 'dtr-workbooks'),
        __('Event Field Explorer', 'dtr-workbooks'),
        'manage_options',
        'dtr-workbooks-event-explorer',
        'dtr_workbooks_event_explorer_page'
    );
});

function dtr_workbooks_event_explorer_page() {
    ?>
    <div class="wrap plugin-admin-content">
        <h1><?php _e('Workbooks Event Field Explorer', 'dtr-workbooks'); ?></h1>
        <p><?php _e('This tool discovers and explores event data in your Russell Publishing Workbooks CRM instance. It automatically detects the correct API endpoint structure and shows available object types.', 'dtr-workbooks'); ?></p>
        <div id="dtr-event-explorer-error" style="color:red;"></div>
        
        <!-- Event Lookup Section -->
        <div style="background: #f0f6fc; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h3><?php _e('Direct Event Lookup', 'dtr-workbooks'); ?></h3>
            <p><?php _e('Enter an event ID or reference (e.g., "2921" or "EVENT-2921") to fetch detailed information:', 'dtr-workbooks'); ?></p>
            <input type="text" id="dtr-direct-event-lookup" placeholder="Enter Event ID or Reference (e.g., 2921, EVENT-2921)" style="min-width: 300px; padding: 8px;" />
            <button id="dtr-fetch-event-details" class="button button-primary" style="margin-left: 10px;"><?php _e('Fetch Event Details', 'dtr-workbooks'); ?></button>
        </div>
        
        <!-- Event Selection Dropdown -->
        <div style="margin-bottom: 20px;">
            <h3><?php _e('Browse Available Events', 'dtr-workbooks'); ?></h3>
            <select id="dtr-workbooks-event-select" style="min-width:300px;">
                <option value=""><?php _e('Loading events and discovering endpoints...', 'dtr-workbooks'); ?></option>
            </select>
        </div>
        
        <div id="dtr-workbooks-event-fields-table" style="margin-top: 30px;"></div>
        <script>
        jQuery(function($){
            var $select = $('#dtr-workbooks-event-select');
            var $table = $('#dtr-workbooks-event-fields-table');
            var $error = $('#dtr-event-explorer-error');
            var $directLookup = $('#dtr-direct-event-lookup');
            var $fetchButton = $('#dtr-fetch-event-details');
            
            $select.prop('disabled', true);
            $error.text('');
            
            // Function to display event details in comprehensive format
            function displayEventDetails(eventData, endpointUsed, isDirectLookup = false) {
                var html = '';
                
                if (endpointUsed) {
                    html += '<div style="margin-bottom: 15px; color: green; font-weight: bold;">✅ Event details loaded using endpoint: <code>' + endpointUsed + '</code></div>';
                }
                
                if (isDirectLookup && eventData) {
                    html += '<div style="margin-bottom: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #0073aa;"><strong>📋 Event Found:</strong> ' + (eventData.name || 'Unnamed Event') + ' (ID: ' + (eventData.id || 'Unknown') + ')</div>';
                }
                
                // Main event information table
                html += '<h3>📊 Event Information</h3>';
                html += '<table class="widefat striped"><thead><tr><th style="width: 200px;">Field</th><th>Value</th></tr></thead><tbody>';
                
                // Priority fields first
                var priorityFields = ['id', 'object_ref', 'name', 'description', 'event_type', 'start_date', 'end_date', 'status', 'location', 'website_page', 'lead_source_reference'];
                var remainingFields = {};
                
                // Show priority fields first
                priorityFields.forEach(function(field) {
                    if (eventData.hasOwnProperty(field)) {
                        var value = eventData[field];
                        if (typeof value === 'object') value = JSON.stringify(value, null, 2);
                        html += '<tr><td><strong><code>' + field + '</code></strong></td><td>' + (value || '-') + '</td></tr>';
                        delete eventData[field]; // Remove from remaining fields
                    }
                });
                
                // Show all remaining fields
                Object.keys(eventData).sort().forEach(function(field) {
                    var value = eventData[field];
                    if (typeof value === 'object') value = '<pre style="white-space: pre-wrap; font-size: 11px; max-height: 200px; overflow-y: auto;">' + JSON.stringify(value, null, 2) + '</pre>';
                    html += '<tr><td><code>' + field + '</code></td><td>' + (value || '-') + '</td></tr>';
                });
                
                html += '</tbody></table>';
                
                // Add copy functionality
                html += '<div style="margin-top: 15px;"><button id="copy-event-data" class="button button-secondary">📋 Copy All Event Data as JSON</button></div>';
                
                $table.html(html);
                
                // Add copy functionality
                $('#copy-event-data').on('click', function() {
                    var jsonData = JSON.stringify(eventData, null, 2);
                    navigator.clipboard.writeText(jsonData).then(function() {
                        $(this).text('✅ Copied!').prop('disabled', true);
                        setTimeout(() => {
                            $(this).text('📋 Copy All Event Data as JSON').prop('disabled', false);
                        }, 2000);
                    }).catch(function(err) {
                        alert('Failed to copy to clipboard. Check console for JSON data.');
                        console.log('Event Data JSON:', jsonData);
                    });
                });
            }
            
            // Direct event lookup functionality
            $fetchButton.on('click', function() {
                var eventRef = $directLookup.val().trim();
                if (!eventRef) {
                    $error.html('<span style="color: orange;">⚠️ Please enter an event ID or reference</span>');
                    return;
                }
                
                // Extract numeric ID from reference like "EVENT-2921" or use as-is if already numeric
                var eventId = eventRef.replace(/[^0-9]/g, '');
                if (!eventId) {
                    $error.html('<span style="color: orange;">⚠️ Could not extract numeric ID from: ' + eventRef + '</span>');
                    return;
                }
                
                $table.html('<div style="text-align: center; padding: 20px; color: #666;">🔍 Searching for event: <strong>' + eventRef + '</strong> (ID: ' + eventId + ')...</div>');
                $error.html('');
                $fetchButton.prop('disabled', true).text('Searching...');
                
                $.post(ajaxurl, {
                    action: 'dtr_get_workbooks_event_fields',
                    nonce: '<?php echo esc_js(wp_create_nonce('workbooks_nonce')); ?>',
                    event_id: eventId
                }, function(resp){
                    console.log('Direct lookup response:', resp);
                    if (resp.success && resp.data && resp.data.event) {
                        displayEventDetails(resp.data.event, resp.data.endpoint_used, true);
                        $error.html('<span style="color: green;">✅ Event found successfully!</span>');
                    } else {
                        $table.html('');
                        var errorMsg = resp.data && resp.data.message ? resp.data.message : 'Event not found with ID: ' + eventId;
                        $error.html('<span style="color: red;">❌ ' + errorMsg + '</span>');
                        
                        if (resp.data && resp.data.errors) {
                            console.log('Direct lookup errors:', resp.data.errors);
                        }
                    }
                }).fail(function(xhr, status, error){
                    console.error('Direct lookup AJAX error:', xhr, status, error);
                    $table.html('');
                    $error.html('<span style="color: red;">❌ AJAX Error: ' + xhr.status + ' ' + xhr.statusText + '</span>');
                }).always(function() {
                    $fetchButton.prop('disabled', false).text('<?php echo esc_js(__('Fetch Event Details', 'dtr-workbooks')); ?>');
                });
            });
            
            // Allow Enter key to trigger search
            $directLookup.on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    $fetchButton.click();
                }
            });
            
            // Fetch events for dropdown
            $.post(ajaxurl, {
                action: 'dtr_list_workbooks_events',
                nonce: '<?php echo esc_js(wp_create_nonce('workbooks_nonce')); ?>'
            }, function(resp){
                console.log('Events response:', resp);
                $select.empty();
                if (resp.success && resp.data && resp.data.events && resp.data.events.length) {
                    $select.append('<option value=""><?php echo esc_js(__('Select an event...', 'dtr-workbooks')); ?></option>');
                    $.each(resp.data.events, function(i, event){
                        var label = event.name + ' (ID: ' + event.id + ')';
                        $select.append('<option value="' + event.id + '">' + label + '</option>');
                    });
                    $select.prop('disabled', false);
                    
                    // Show successful endpoint info
                    if (resp.data.endpoint_used) {
                        console.log('✅ Working endpoint found:', resp.data.endpoint_used);
                        $error.html('<span style="color: green;">✅ Events loaded successfully using endpoint: <code>' + resp.data.endpoint_used + '</code></span>');
                    }
                } else {
                    $select.append('<option value=""><?php echo esc_js(__('No events found', 'dtr-workbooks')); ?></option>');
                    var errorMsg = resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js(__('No events loaded - check API connection and debug logs.', 'dtr-workbooks')); ?>';
                    $error.html('<span style="color: red;">' + errorMsg + '</span>');
                    
                    // Show diagnostic information
                    if (resp.data) {
                        console.log('Diagnostic information:', resp.data);
                        
                        // Show diagnostic information
                        if (resp.data.diagnostic_info && resp.data.diagnostic_info.length > 0) {
                            var diagnosticInfo = '<br><div style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-radius: 4px; color: #0073aa;">';
                            diagnosticInfo += '<strong>🔧 Diagnostic Info:</strong><br>';
                            diagnosticInfo += resp.data.diagnostic_info.join('<br>');
                            diagnosticInfo += '</div>';
                            $error.append(diagnosticInfo);
                        }
                        
                        // Show recommendations for Russell Publishing instances
                        if (resp.data.recommendations && resp.data.recommendations.length > 0) {
                            var recommendations = '<br><div style="margin-top: 10px; padding: 10px; background: #d1ecf1; border-radius: 4px; color: #0c5460;">';
                            recommendations += '<strong>📋 Recommendations:</strong><br>';
                            recommendations += '<ul style="margin: 5px 0 0 20px;">';
                            $.each(resp.data.recommendations, function(i, rec) {
                                recommendations += '<li>' + rec + '</li>';
                            });
                            recommendations += '</ul></div>';
                            $error.append(recommendations);
                        }
                        
                        // Show suggestion if available
                        if (resp.data.suggestion) {
                            var suggestionInfo = '<br><div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 4px; color: #856404;">';
                            suggestionInfo += '<strong>💡 Suggestion:</strong><br>' + resp.data.suggestion;
                            suggestionInfo += '</div>';
                            $error.append(suggestionInfo);
                        }
                        
                        // Show detailed error information
                        if (resp.data.errors) {
                            console.log('Endpoint errors:', resp.data.errors);
                            var errorDetails = '<br><details style="margin-top: 10px;"><summary style="cursor: pointer;">🔍 Technical Details (click to expand)</summary>';
                            errorDetails += '<div style="margin-top: 10px; font-size: 12px; background: #f8f9fa; padding: 10px; border-radius: 4px;">';
                            errorDetails += '<strong>Endpoints tested:</strong><br>';
                            $.each(resp.data.errors, function(endpoint, error) {
                                errorDetails += '<code>' + endpoint + '</code>: ' + error.substring(0, 100) + (error.length > 100 ? '...' : '') + '<br>';
                            });
                            errorDetails += '</div></details>';
                            $error.append(errorDetails);
                        }
                    }
                }
            }).fail(function(xhr, status, error){
                console.error('AJAX Error Details:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: status,
                    errorThrown: error
                });
                $select.empty().append('<option value=""><?php echo esc_js(__('AJAX error', 'dtr-workbooks')); ?></option>');
                $error.text('AJAX Error: ' + xhr.status + ' ' + xhr.statusText + '. Check console for details.');
                
                // Try to show actual response for debugging
                if (xhr.responseText) {
                    console.log('Raw response text:', xhr.responseText);
                    $error.append('<br><small>Raw response (check console): ' + xhr.responseText.substring(0, 200) + (xhr.responseText.length > 200 ? '...' : '') + '</small>');
                }
            });
            
            // On selection, fetch event fields
            $select.on('change', function(){
                var event_id = $(this).val();
                $table.html('');
                $error.text('');
                if (!event_id) return;
                
                $table.html('<div style="text-align: center; padding: 20px; color: #666;">📋 Loading event details...</div>');
                $.post(ajaxurl, {
                    action: 'dtr_get_workbooks_event_fields',
                    nonce: '<?php echo esc_js(wp_create_nonce('workbooks_nonce')); ?>',
                    event_id: event_id
                }, function(resp){
                    console.log('Event fields response:', resp);
                    if (resp.success && resp.data && resp.data.event) {
                        displayEventDetails(resp.data.event, resp.data.endpoint_used, false);
                    } else {
                        $table.html('');
                        var errorMsg = resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js(__('Error fetching event details.', 'dtr-workbooks')); ?>';
                        $error.html('<span style="color: red;">' + errorMsg + '</span>');
                        
                        // Show detailed error information
                        if (resp.data && resp.data.errors) {
                            console.log('Endpoint errors for event fields:', resp.data.errors);
                            var errorDetails = '<br><small>Endpoint errors:<br>';
                            $.each(resp.data.errors, function(endpoint, error) {
                                errorDetails += '<code>' + endpoint + '</code>: ' + error + '<br>';
                            });
                            errorDetails += '</small>';
                            $error.append(errorDetails);
                        }
                    }
                }).fail(function(xhr, status, error){
                    console.error('Event Fields AJAX Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: status,
                        errorThrown: error
                    });
                    $table.html('');
                    $error.text('AJAX Error: ' + xhr.status + ' ' + xhr.statusText + '. Check console for details.');
                    
                    if (xhr.responseText) {
                        console.log('Raw response text:', xhr.responseText);
                    }
                });
            });
        });
        </script>
    </div>
    <?php
}

// --- AJAX handlers (same as before, but with improved error returns) ---

add_action('wp_ajax_dtr_list_workbooks_events', function() {
    // Start output buffering to catch any unwanted output
    ob_start();
    
    try {
        check_ajax_referer('workbooks_nonce', 'nonce');
        
        $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
        if (!$workbooks) {
            ob_clean(); // Clean any buffered output
            wp_send_json_error(['message' => 'Workbooks API not available. Check API settings and debug log.']);
            return;
        }
        
        // Russell Publishing Workbooks Instance - Special Handling
        // All standard endpoints are returning 404, so we need a different approach
        
        $diagnostic_info = [];
        $errors = [];
        $working_endpoints = [];
        $api_connection_test = false;
        
        // Test basic API connection using the actual working endpoints found in your form handlers
        $working_endpoints = [];
        $tested_endpoints = [];
        
        // These are the actual working endpoints found in your form handlers
        $known_working_endpoints = [
            'crm/people.api',           // Used in webinar and membership registrations
            'event/events.api',         // Used in webinar registrations  
            'email/mailing_list_entries.api', // Used in webinar registrations
            'crm/people',               // Alternative format used in some handlers
        ];
        
        foreach ($known_working_endpoints as $endpoint) {
            try {
                $test_result = $workbooks->get($endpoint, ['_start' => 0, '_limit' => 1]);
                if ($test_result) {
                    $working_endpoints[] = $endpoint;
                }
                $tested_endpoints[] = $endpoint . ' (tested)';
            } catch (Exception $e) {
                $tested_endpoints[] = $endpoint . ' (404: ' . $e->getMessage() . ')';
            }
        }
        
        if (empty($working_endpoints)) {
            // No working endpoints found - API connection issue
            ob_clean();
            wp_send_json_error([
                'message' => 'No working Workbooks API endpoints found',
                'diagnostic_info' => [
                    'Tested known working endpoints from your form handlers',
                    'All endpoints returned 404 errors',
                    'This suggests an API configuration or authentication issue'
                ],
                'tested_endpoints' => $tested_endpoints,
                'recommendations' => [
                    'Check your Workbooks API settings in WordPress admin',
                    'Verify the API base URL is correct',
                    'Test API credentials with your Workbooks administrator',
                    'Your form handlers use these endpoints successfully: crm/people.api, event/events.api'
                ]
            ]);
            return;
        }
        
        // Now try event-specific endpoints using the working patterns
        $event_endpoints_to_try = [
            // Primary event endpoint used in your webinar handlers
            'event/events.api',
            // Alternative patterns based on your working endpoints  
            'crm/events.api',
            'event/activities.api',
            'crm/activities.api',
            // Fallback patterns without .api suffix
            'event/events',
            'crm/events', 
            'activities',
            'events'
        ];
        
        $successful_endpoint = null;
        $events_result = null;
        
        foreach ($event_endpoints_to_try as $endpoint) {
            try {
                // Use basic get() without assertGet to avoid exceptions
                $test_result = $workbooks->get($endpoint, [
                    '_start' => 0,
                    '_limit' => 10,
                    '_select_columns[]' => ['id', 'name'],
                    '_sort_column' => 'id',
                    '_sort_direction' => 'DESC'
                ]);
                
                // Check if we got a valid response structure
                if ($test_result && 
                    (isset($test_result['data']) || isset($test_result['records']) || isset($test_result['items']))) {
                    
                    // Try to standardize the response format
                    $data_key = 'data';
                    if (isset($test_result['records'])) {
                        $data_key = 'records';
                    } elseif (isset($test_result['items'])) {
                        $data_key = 'items';
                    }
                    
                    if (is_array($test_result[$data_key])) {
                        $successful_endpoint = $endpoint;
                        $events_result = [
                            'data' => $test_result[$data_key],
                            'original_structure' => $data_key
                        ];
                        break;
                    }
                }
            } catch (Exception $e) {
                $errors[$endpoint] = $e->getMessage();
                continue;
            }
        }
        
        if ($successful_endpoint && $events_result) {
            $events = $events_result['data'] ?? [];
            
            // Clean any buffered output before sending JSON
            ob_clean();
            wp_send_json_success([
                'events' => $events,
                'endpoint_used' => $successful_endpoint,
                'working_endpoints' => $working_endpoints,
                'data_structure' => $events_result['original_structure'] ?? 'data',
                'diagnostic_info' => [
                    "Successfully found working event endpoint: {$successful_endpoint}",
                    "Your Workbooks instance uses the .api suffix pattern",
                    "Form handlers are working with: " . implode(', ', $working_endpoints)
                ],
                'debug' => 'Successfully found working endpoint: ' . $successful_endpoint,
                'instance_info' => 'Your Workbooks instance - .api endpoint pattern detected'
            ]);
        } else {
            // Clean any buffered output before sending error
            ob_clean();
            wp_send_json_error([
                'message' => 'No event endpoints found, but your form handlers are working.',
                'working_endpoints' => $working_endpoints,
                'tried_endpoints' => array_keys($errors),
                'errors' => $errors,
                'diagnostic_info' => [
                    'Your form handlers successfully use these endpoints: ' . implode(', ', $working_endpoints),
                    'But no event-specific endpoints were found',
                    'Events might be stored in activities or have a different structure'
                ],
                'recommendations' => [
                    'Events might be stored as activities - check crm/activities.api',
                    'Contact your Workbooks administrator to confirm event storage structure',  
                    'Your webinar registrations work, so the API connection is functional'
                ]
            ]);
        }
        
    } catch (Exception $e) {
        // Clean any buffered output before sending error
        ob_clean();
        wp_send_json_error([
            'message' => 'Workbooks API error: ' . $e->getMessage(),
            'diagnostic_info' => ['Exception occurred during API testing'],
            'recommendations' => ['Check API credentials, URL, and permissions for your Workbooks instance.']
        ]);
    }
});

add_action('wp_ajax_dtr_get_workbooks_event_fields', function() {
    // Start output buffering to catch any unwanted output
    ob_start();
    
    try {
        check_ajax_referer('workbooks_nonce', 'nonce');
        
        $event_id = intval($_POST['event_id'] ?? 0);
        if (!$event_id) {
            ob_clean();
            wp_send_json_error(['message' => 'Missing event ID']);
            return;
        }
        
        $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
        if (!$workbooks) {
            ob_clean();
            wp_send_json_error(['message' => 'Workbooks API not available']);
            return;
        }
        
        // Try the same endpoint patterns that worked for event listing
        $possible_endpoints = [
            'event/events.api',      // This is working for event listing
            'crm/events.api',        // Alternative .api pattern
            'event/activities.api',  // In case events are stored as activities
            'crm/activities.api',
            // Fallback patterns without .api suffix
            'event/events',
            'events',
            'crm/events', 
            'crm/event',
            'event',
            'crm/marketing_events',
            'marketing_events'
        ];
        
        $result = null;
        $successful_endpoint = null;
        $errors = [];
        
        foreach ($possible_endpoints as $endpoint) {
            try {
                // Try with comprehensive field selection first
                $result = $workbooks->assertGet($endpoint, [
                    '_start' => 0,
                    '_limit' => 1,
                    '_ff[]' => 'id',
                    '_ft[]' => 'eq', 
                    '_fc[]' => $event_id,
                    '_select_columns[]' => '*'  // Request all available columns
                ]);
                
                if ($result && isset($result['data']) && !empty($result['data'][0])) {
                    $successful_endpoint = $endpoint;
                    break;
                }
                
                // If that didn't work, try without column selection
                $result = $workbooks->assertGet($endpoint, [
                    '_start' => 0,
                    '_limit' => 1,
                    '_ff[]' => 'id',
                    '_ft[]' => 'eq', 
                    '_fc[]' => $event_id
                ]);
                
                if ($result && isset($result['data']) && !empty($result['data'][0])) {
                    $successful_endpoint = $endpoint;
                    break;
                }
                
            } catch (Exception $e) {
                $errors[$endpoint] = $e->getMessage();
                continue;
            }
        }
        
        if ($successful_endpoint && $result && !empty($result['data'][0])) {
            $event_data = $result['data'][0];
            
            // Try to fetch additional related data if we have the event
            $additional_data = [];
            
            // Try to get event registrations/attendees if available
            if (!empty($event_data['id'])) {
                try {
                    $registrations = $workbooks->assertGet('crm/event_registrations', [
                        '_ff[]' => 'event_id',
                        '_ft[]' => 'eq',
                        '_fc[]' => $event_data['id'],
                        '_limit' => 5  // Get first few registrations as sample
                    ]);
                    if (!empty($registrations['data'])) {
                        $additional_data['sample_registrations'] = $registrations['data'];
                        $additional_data['registration_count_sample'] = count($registrations['data']);
                    }
                } catch (Exception $e) {
                    // Registration data not available or different endpoint
                    $additional_data['registration_error'] = 'Could not fetch registrations: ' . $e->getMessage();
                }
            }
            
            // Merge additional data into event data for comprehensive view
            if (!empty($additional_data)) {
                $event_data['_additional_data'] = $additional_data;
            }
            
            // Clean any buffered output before sending JSON
            ob_clean();
            wp_send_json_success([
                'event' => $event_data,
                'endpoint_used' => $successful_endpoint,
                'debug' => 'Successfully used endpoint: ' . $successful_endpoint,
                'has_additional_data' => !empty($additional_data)
            ]);
        } else {
            ob_clean();
            wp_send_json_error([
                'message' => 'Event not found (ID: ' . $event_id . ') - tried endpoints: ' . implode(', ', $possible_endpoints),
                'errors' => $errors,
                'debug_info' => 'No endpoint returned event data for ID: ' . $event_id,
                'suggestion' => 'The event ID ' . $event_id . ' may not exist, or it might be stored in a different object type. Try checking the event list first.'
            ]);
        }
        
    } catch (Exception $e) {
        // Clean any buffered output before sending error
        ob_clean();
        wp_send_json_error(['message' => 'Workbooks API error: ' . $e->getMessage()]);
    }
});

// Add Ninja Forms loader for all submissions
/* add_action('wp_footer', function() {
    // Get loader settings
    $loader_options = get_option('dtr_ninja_forms_loader', [
        'enabled' => 1,
        'logo_color' => get_template_directory_uri() . '/img/logos/DTR_Logo-02.svg',
        'logo_white' => get_template_directory_uri() . '/img/logos/DTR_Logo-01.svg',
        'background_color' => 'rgba(255,255,255,0.8)',
        'progress_color' => '#871f80',
        'text_color' => '#871f80',
        'submitting_text' => 'Submitting...',
        'success_text' => 'Submission Successful',
        'logo_size' => '7rem',
        'animation_speed' => '0.3s',
    ]);
    
    // Only render if enabled
    if (!$loader_options['enabled']) {
        return;
    }
    ?>
    <style>
    #nf-modern-loader-overlay {
      position: fixed;
      top: 0; left: 0;
      width: 100vw; height: 100vh;
      background: <?php echo esc_attr($loader_options['background_color']); ?>;
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      overflow: hidden;
      opacity: 0;
      pointer-events: none;
      transition: opacity <?php echo esc_attr($loader_options['animation_speed']); ?> ease;
    }
    #nf-modern-loader-overlay.active {
      opacity: 1;
      pointer-events: all;
    }
    .nf-modern-loader-content {
      position: relative;
      z-index: 2;
      text-align: center;
    }
    #nf-modern-loader-logo {
      max-width: <?php echo esc_attr($loader_options['logo_size']); ?>;
      transform: scale(0);
      opacity: 0;
      transition: transform <?php echo esc_attr($loader_options['animation_speed']); ?> ease 0.4s, opacity <?php echo esc_attr($loader_options['animation_speed']); ?> ease 0.4s;
      margin-bottom: 1rem;
    }
    #nf-modern-loader-overlay.active #nf-modern-loader-logo {
      transform: scale(1);
      opacity: 1;
    }
    #nf-modern-loader-progress {
      position: absolute;
      bottom: -100%;
      left: 0;
      width: 100%;
      height: 100%;
      background: <?php echo esc_attr($loader_options['progress_color']); ?>;
      z-index: 1;
      transition: bottom 0.2s linear;
    }
    #nf-modern-loader-message {
      font-size: 1.25rem;
      color: <?php echo esc_attr($loader_options['text_color']); ?>;
      font-weight: bold;
      margin-top: 1rem;
      font-family: "Titillium Web", Sans-serif !important;
      z-index: 3;
      position: relative;
      opacity: 0;
      transition: opacity 0.5s;
    }
    #nf-modern-loader-message.visible {
      opacity: 1;
    }
    #nf-modern-loader-message::before {
      content: attr(data-text);
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      color: #fff;
      z-index: 1;
      clip-path: inset(100% 0 0 0);
      transition: clip-path 0.2s linear;
    }
    #nf-modern-loader-message.progress-active::before {
      clip-path: var(--progress-clip, inset(100% 0 0 0));
    }
    </style>

    <div id="nf-modern-loader-overlay">
      <div class="nf-modern-loader-content">
        <img id="nf-modern-loader-logo"
             src="<?php echo esc_url($loader_options['logo_color']); ?>"
             data-logo-color="<?php echo esc_url($loader_options['logo_color']); ?>"
             data-logo-white="<?php echo esc_url($loader_options['logo_white']); ?>"
             alt="Logo">
        <div id="nf-modern-loader-message" data-text="<?php echo esc_attr($loader_options['submitting_text']); ?>"><?php echo esc_html($loader_options['submitting_text']); ?></div>
      </div>
      <div id="nf-modern-loader-progress"></div>
    </div>

    <script>
    jQuery(function($) {
      // Check if loader is enabled
      console.log('Ninja Forms Loader initialized');
      
      var $overlay = $('#nf-modern-loader-overlay');
      var $logo = $('#nf-modern-loader-logo');
      var $progress = $('#nf-modern-loader-progress');
      var $message = $('#nf-modern-loader-message');

      var submitMsgTimeout, successMsgTimeout, hideTimeout;
      var submittingShown = false;

      function resetMessage() {
        $message.removeClass('visible progress-active');
        $message.text('<?php echo esc_js($loader_options['submitting_text']); ?>');
        $message.attr('data-text', '<?php echo esc_js($loader_options['submitting_text']); ?>');
        $message.css('--progress-clip', 'inset(100% 0 0 0)');
        submittingShown = false;
      }

      function startLoader() {
        console.log('startLoader() called');
        clearTimeout(submitMsgTimeout);
        clearTimeout(successMsgTimeout);
        clearTimeout(hideTimeout);
        $overlay.addClass('active');
        console.log('Overlay activated');
        $logo.attr('src', $logo.data('logo-color'));
        $progress.css('bottom', '-100%');
        resetMessage();

        // Fade in "Submitting..." after 2s
        submitMsgTimeout = setTimeout(function() {
          $message.text('<?php echo esc_js($loader_options['submitting_text']); ?>');
          $message.attr('data-text', '<?php echo esc_js($loader_options['submitting_text']); ?>');
          $message.addClass('visible progress-active');
          console.log('Message shown: submitting');
          submittingShown = true;
        }, 2000);
      }
      function updateProgress(percent) {
        var newBottom = -100 + percent;
        $progress.css('bottom', newBottom + '%');
        
        // Change logo to white version when progress reaches 50%
        if (percent >= 50) {
          $logo.attr('src', $logo.data('logo-white'));
        }
        
        // Update text clipping mask to reveal white text progressively
        var clipValue = Math.max(0, 100 - percent);
        $message.css('--progress-clip', 'inset(' + clipValue + '% 0 0 0)');
      }
      function endLoader() {
        $progress.css('bottom', '0%');
        // Ensure text is fully white when progress reaches 100%
        $message.css('--progress-clip', 'inset(0% 0 0 0)');

        // If "Submitting..." hasn't shown yet, show it now (for edge cases)
        if (!submittingShown) {
          $message.text('<?php echo esc_js($loader_options['submitting_text']); ?>');
          $message.attr('data-text', '<?php echo esc_js($loader_options['submitting_text']); ?>');
          $message.addClass('visible progress-active');
          submittingShown = true;
        }

        // Change to "Submission Successful" and keep visible for 2 seconds
        successMsgTimeout = setTimeout(function() {
          var successText = '<?php echo esc_js($loader_options['success_text']); ?>';
          $message.text(successText);
          $message.attr('data-text', successText);
          // Remain visible for another 2 seconds, then fade out
          hideTimeout = setTimeout(function() {
            $overlay.removeClass('active');
            $progress.css('bottom', '-100%');
            // Fade out message for next time
            $message.removeClass('visible progress-active');
            $message.css('--progress-clip', 'inset(100% 0 0 0)');
          }, 2000);
        }, 200); // slight pause for smoothness, adjust as desired
      }

      // Show loader on Ninja Forms submit button click
      $(document).on('click', '.nf-form-content input[type="submit"], .nf-form-content button[type="submit"], .ninja-forms-field[type="submit"], input.ninja-forms-field[type="submit"]', function(e) {
        console.log('Ninja Forms submit button clicked, starting loader...');
        startLoader();
      });

      // Alternative: Also trigger on form submission
      $(document).on('submit', '.nf-form', function(e) {
        console.log('Ninja Forms form submitted, starting loader...');
        startLoader();
      });

      // Listen for Ninja Forms specific events
      $(document).on('nf:submit', function(e) {
        console.log('Ninja Forms nf:submit event, starting loader...');
        startLoader();
      });

      // Catch any submit button clicks more broadly
      $(document).on('click', 'input[type="submit"], button[type="submit"]', function(e) {
        // Check if this is within a Ninja Form
        if ($(this).closest('.nf-form-cont').length > 0 || $(this).closest('.ninja-forms-form').length > 0) {
          console.log('Submit button in Ninja Form detected, starting loader...');
          startLoader();
        }
      });

      // Progress bar for AJAX
      $(document).on('nfFormSubmit', function(e, formData) {
        $(document).ajaxSend(function(event, jqXHR, settings) {
          if (settings.url && settings.url.indexOf('ninja-forms-ajax.php') !== -1) {
            if (jqXHR.upload) {
              jqXHR.upload.addEventListener('progress', function(evt) {
                if (evt.lengthComputable) {
                  var percent = Math.round((evt.loaded / evt.total) * 100);
                  updateProgress(percent);
                }
              }, false);
            }
          }
        });
      });

      // Change message and hide loader on submission complete
      $(document).on('nfFormSubmitResponse', function(e, response) {
        endLoader();
      });
    });
    </script>
    <?php
}); */

/**
 * Add Test Loader button to WordPress admin bar for administrators
 */
add_action('admin_bar_menu', 'dtr_add_test_loader_admin_bar', 100);

function dtr_add_test_loader_admin_bar($wp_admin_bar) {
    // Only show for administrators
    if (!current_user_can('administrator')) {
        return;
    }
    
    // Add the menu item
    $wp_admin_bar->add_menu(array(
        'id'    => 'dtr-test-loader',
        'title' => '🧪 Test Loader',
        'href'  => '#',
        'meta'  => array(
            'onclick' => 'dtrTestLoader(); return false;',
            'title' => 'Test the progress loader functionality'
        )
    ));
}

/**
 * Add JavaScript for Test Loader functionality
 */
add_action('wp_footer', 'dtr_add_test_loader_script');
add_action('admin_footer', 'dtr_add_test_loader_script');

function dtr_add_test_loader_script() {
    // Only show for administrators
    if (!current_user_can('administrator')) {
        return;
    }
    ?>
    <script>
    function dtrTestLoader() {
        console.log('🧪 DTR Test Loader initiated');
        
        // Check if progress loader elements exist
        const loadingOverlay = document.getElementById('formLoaderOverlay');
        const progressCircle = document.getElementById('progressCircle');
        const progressValue = document.getElementById('progressValue');
        
        if (!loadingOverlay || !progressCircle || !progressValue) {
            alert('⚠️ Progress loader elements not found on this page.\n\nThe loader is only available on pages with forms that have the progress overlay.');
            console.log('❌ Progress loader elements not found:', {
                loadingOverlay: !!loadingOverlay,
                progressCircle: !!progressCircle,
                progressValue: !!progressValue
            });
            return;
        }
        
        console.log('✅ All progress loader elements found, starting test...');
        
        // Debug: Check function availability
        console.log('Function availability:', {
            showProgressLoader: typeof showProgressLoader,
            updateFormProgress: typeof updateFormProgress,
            hideProgressLoader: typeof hideProgressLoader
        });
        
        // Show the loader
        if (typeof showProgressLoader === 'function') {
            showProgressLoader();
        } else {
            // Manual loader display if function not available
            loadingOverlay.style.display = 'flex';
            setTimeout(() => loadingOverlay.classList.add('show'), 10);
        }
        
        // Simulate progress updates
        const progressSteps = [
            { delay: 500, progress: 15, message: 'Testing progress...' },
            { delay: 1000, progress: 35, message: 'Testing progress...' },
            { delay: 1500, progress: 55, message: 'Testing progress...' },
            { delay: 2000, progress: 75, message: 'Testing progress...' },
            { delay: 2500, progress: 90, message: 'Testing progress...' },
            { delay: 3000, progress: 100, message: 'Test complete!' }
        ];
        
        progressSteps.forEach(step => {
            setTimeout(() => {
                console.log(`🔄 Test progress: ${step.progress}%`);
                
                if (typeof updateFormProgress === 'function') {
                    console.log('📞 Calling updateFormProgress function');
                    updateFormProgress(step.progress, step.message);
                } else {
                    console.log('📞 Using manual progress update fallback');
                    // Manual progress update if function not available
                    const offset = 283 - (step.progress / 100) * 283;
                    
                    console.log(`🔧 Manual update - calculated offset: ${offset}`);
                    progressCircle.style.strokeDashoffset = offset.toString();
                    progressValue.textContent = step.progress + '%';
                    
                    // Force a repaint
                    progressCircle.style.display = 'none';
                    progressCircle.offsetHeight; // Trigger reflow
                    progressCircle.style.display = '';
                    
                    console.log(`� Applied values - strokeDashoffset: ${progressCircle.style.strokeDashoffset}, text: ${progressValue.textContent}`);
                }
            }, step.delay);
        });
        
        // Hide loader after test completes
        setTimeout(() => {
            if (typeof hideProgressLoader === 'function') {
                hideProgressLoader();
            } else {
                // Manual loader hide if function not available
                loadingOverlay.classList.remove('show');
                setTimeout(() => {
                    loadingOverlay.style.display = 'none';
                }, 300);
            }
            console.log('✅ Test loader completed');
            
            // Reset progress for next test
            setTimeout(() => {
                if (progressCircle && progressValue) {
                    progressCircle.style.strokeDashoffset = '283'; // 0%
                    progressValue.textContent = '0%';
                }
            }, 500);
        }, 4000);
    }
    </script>
    <?php
}

?>
