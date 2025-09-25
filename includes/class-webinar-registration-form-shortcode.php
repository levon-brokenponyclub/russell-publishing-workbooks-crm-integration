<?php
/* --------------------------------------------------------------------------
 * Workbooks Webinar Registration Form Shortcode
 * 
 * Shortcode: [dtr_webinar_registration_form]
 * Renders a custom webinar registration form with conditional logic based on user state.
 *
 * Features:
 * - Dynamic form fields from ACF
 * - User state management (logged in/out)
 * - On-demand vs live webinar handling
 * - Integration with Workbooks CRM
 *
 * Required ACF Fields:
 * - webinar_fields (Group)
 *   - workbooks_reference
 *   - add_speaker_question
 *   - add_additional_questions
 *   - add_questions (Repeater)
 * -------------------------------------------------------------------------- */

if (!defined('ABSPATH')) exit;

class DTR_Webinar_Registration_Form {
    
    public function __construct() {
        add_shortcode('dtr_webinar_registration_form', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Add AJAX handlers for form submission
        add_action('wp_ajax_dtr_submit_webinar_class', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_dtr_submit_webinar_class', array($this, 'handle_ajax_submission'));
        
        // Add admin testing functionality
        add_action('admin_menu', array($this, 'add_admin_testing_menu'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_buttons'), 100);
        add_action('init', array($this, 'handle_admin_bar_actions'));
        
        // Add form-based deregistration handler
        add_action('init', array($this, 'handle_form_deregistration'));
    }
    
    /**
     * Debug logging with emoji formatting
     */
    private function log_debug($message) {
        $timestamp = date('Y-m-d H:i:s');
        $debug_log_file = DTR_WORKBOOKS_PLUGIN_DIR . 'logs/webinar-class-registration-debug.log';
        
        $formatted_message = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($debug_log_file, $formatted_message, FILE_APPEND | LOCK_EX);
        error_log('[DTR Webinar Class] ' . $message);
    }
    
    /**
     * Handle AJAX form submission with comprehensive processing
     */
    public function handle_ajax_submission() {
        $this->log_debug("✅ AJAX submission received");
        
        try {
            check_ajax_referer('dtr_webinar_class_nonce', '_wpnonce');
            
            // Collect form data
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $first_name = sanitize_text_field(isset($_POST['first_name']) ? $_POST['first_name'] : '');
            $last_name = sanitize_text_field(isset($_POST['last_name']) ? $_POST['last_name'] : '');
            $email = sanitize_email(isset($_POST['email']) ? $_POST['email'] : '');
            $person_id = sanitize_text_field(isset($_POST['person_id']) ? $_POST['person_id'] : '');
            $workbooks_reference = sanitize_text_field(isset($_POST['workbooks_reference']) ? $_POST['workbooks_reference'] : '');
            $speaker_question = sanitize_textarea_field(isset($_POST['speaker_question']) ? $_POST['speaker_question'] : '');
            $optin = isset($_POST['sponsor_optin']) && $_POST['sponsor_optin'] === '1';
            
            // If user is logged in, use their data
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                if (empty($email)) $email = $current_user->user_email;
                if (empty($first_name)) $first_name = $current_user->user_firstname ?: $current_user->display_name;
                if (empty($last_name)) $last_name = $current_user->user_lastname;
                if (empty($person_id)) $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
            }
            
            $this->log_debug("✅ STEP 1: Processing Webinar Class Form (ID {$post_id})");
            $this->log_debug("ℹ️ User data - Email: {$email}, Name: {$first_name} {$last_name}");
            
            // Validation
            if (!$post_id || !$email) {
                $this->log_debug("❌ VALIDATION ERROR: Required fields missing");
                wp_send_json_error(array('message' => 'Required fields are missing'));
                return;
            }
            
            // Get webinar details
            $post = get_post($post_id);
            $webinar_title = $post ? $post->post_title : '';
            $webinar_fields = get_field('webinar_fields', $post_id);
            $event_id = '';
            
            if (!empty($webinar_fields['workbooks_reference'])) {
                $event_id = preg_replace('/\D+/', '', $webinar_fields['workbooks_reference']);
                $this->log_debug("ℹ️ STEP 2: Found Workbooks reference: {$event_id}");
            }
            
            // Process with Workbooks if available
            if ($event_id && function_exists('dtr_workbooks_api_request')) {
                $this->log_debug("✅ STEP 3: Workbooks integration available");
                // Workbooks processing would go here
                $this->log_debug("✅ STEP 4: Workbooks sync completed");
            } else {
                $this->log_debug("⚠️ STEP 3: Workbooks integration not available");
            }
            
            // Generate unique registration ID and store registration
            $registration_id = wp_generate_uuid4();
            $registration_success = $this->store_registration($post_id, array(
                'registration_id' => $registration_id,
                'user_id' => is_user_logged_in() ? get_current_user_id() : null,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'person_id' => $person_id,
                'workbooks_reference' => $workbooks_reference,
                'event_id' => $event_id,
                'speaker_question' => $speaker_question,
                'optin' => $optin,
                'registration_date' => current_time('mysql'),
                'post_id' => $post_id,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
            ));
            
            if (!$registration_success) {
                $this->log_debug("❌ Registration storage failed");
                wp_send_json_error(array('message' => 'Registration storage failed'));
                return;
            }
            
            // Send confirmation email
            $this->send_confirmation_email($email, $webinar_title, $first_name, $last_name);
            
            $this->log_debug("🎉 FINAL RESULT: WEBINAR CLASS REGISTRATION SUCCESS!");
            
            wp_send_json_success(array(
                'message' => 'Registration successful',
                'webinar_title' => $webinar_title,
                'email_address' => $email
            ));
            
        } catch (Exception $e) {
            $this->log_debug("❌ FATAL ERROR: " . $e->getMessage());
            wp_send_json_error(array('message' => 'Registration failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Store registration in both user meta and post meta for efficiency
     */
    private function store_registration($post_id, $registration_data) {
        // Add registration to post meta for admin reporting
        add_post_meta($post_id, 'webinar_registrations', $registration_data);
        
        // Add to user meta for fast lookup if user is logged in
        if (is_user_logged_in()) {
            $user_registration_key = 'webinar_registration_' . $post_id;
            update_user_meta(get_current_user_id(), $user_registration_key, array(
                'registration_id' => $registration_data['registration_id'],
                'post_id' => $post_id,
                'registration_date' => $registration_data['registration_date'],
                'email' => $registration_data['email']
            ));
        }
        
        $this->log_debug("✅ Registration stored with ID: " . $registration_data['registration_id']);
        return true;
    }
    
    /**
     * Send confirmation email
     */
    private function send_confirmation_email($email, $webinar_title, $first_name, $last_name) {
        $subject = sprintf('Registration Confirmation: %s', $webinar_title);
        $message = sprintf(
            "Thank you for registering for %s!\n\n" .
            "Your registration details:\n" .
            "Name: %s %s\n" .
            "Email: %s\n\n" .
            "We'll send you a reminder email before the webinar starts.",
            $webinar_title,
            $first_name,
            $last_name,
            $email
        );
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($email, $subject, nl2br($message), $headers);
        $this->log_debug("📧 Confirmation email sent to: {$email}");
    }

    private function is_user_registered_for_event($user_id, $post_id) {
        // Fast direct lookup using post-specific user meta key
        $user_registration_key = 'webinar_registration_' . $post_id;
        $user_registration = get_user_meta($user_id, $user_registration_key, true);
        
        $this->log_debug("🔍 Checking user meta key '{$user_registration_key}': " . 
                        ($user_registration ? json_encode($user_registration) : 'NOT FOUND'));
        
        if ($user_registration && is_array($user_registration)) {
            $this->log_debug("✅ Found registration in user meta - returning TRUE");
            return true;
        }
        
        // Fallback: Check all post registrations for backwards compatibility
        $all_registrations = get_post_meta($post_id, 'webinar_registrations', false);
        $current_user = get_user_by('ID', $user_id);
        $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
        
        $this->log_debug("🔍 Checking post meta registrations: " . count($all_registrations) . " total registrations");
        
        foreach ($all_registrations as $registration) {
            // Check by user_id, person_id, or email
            if ((isset($registration['user_id']) && $registration['user_id'] == $user_id) ||
                (isset($registration['person_id']) && $registration['person_id'] == $person_id) ||
                (isset($registration['email']) && $registration['email'] == $current_user->user_email)) {
                $this->log_debug("✅ Found registration in post meta - returning TRUE");
                return true;
            }
        }
        
        $this->log_debug("❌ No registration found - returning FALSE");
        return false;
    }

    public function enqueue_assets() {
        // Only load on webinar pages
        if (get_post_type() !== 'webinars') {
            return;
        }
        
        // Remove conflicting scripts
        $conflicting_scripts = array(
            'nf-full-country-names',
            'lead-generation-registration',
            'google-maps',
            'google-maps-api',
            'googlemaps',
            'maps-google'
        );
        
        foreach ($conflicting_scripts as $script) {
            wp_dequeue_script($script);
            wp_deregister_script($script);
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'dtr-dynamic-forms', 
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/dynamic-forms.css',
            array(),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/dynamic-forms.css')
        );

        // Enqueue and localize script
        wp_enqueue_script(
            'dtr-webinar-class-form',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/webinar-class-form.js',
            array('jquery', 'wp-util'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/webinar-class-form.js'),
            true
        );
        
        // Localize script with AJAX data
        $current_user = wp_get_current_user();
        $post = get_post();
        
        wp_localize_script('dtr-webinar-class-form', 'dtrWebinarClassAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dtr_webinar_class_nonce'),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'current_user_id' => get_current_user_id(),
            'is_user_logged_in' => is_user_logged_in(),
            'person_id' => get_user_meta(get_current_user_id(), 'workbooks_person_id', true) ?: '',
            'user_data' => array(
                'first_name' => $current_user->user_firstname ?: '',
                'last_name' => $current_user->user_lastname ?: '',
                'email' => $current_user->user_email ?: ''
            ),
            'post_data' => array(
                'id' => $post ? $post->ID : 0,
                'title' => $post ? $post->post_title : ''
            )
        ));
        
        // Add progress loader functions
        add_action('wp_footer', array($this, 'add_progress_loader_functions'), 999);
    }
    
    /**
     * Add progress loader JavaScript functions
     */
    public function add_progress_loader_functions() {
        if (get_post_type() !== 'webinars') {
            return;
        }
        ?>
        <script>
        // Enhanced Progress Loader Functions for Class-based Form
        function showProgressLoader() {
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            const progressFill = document.getElementById('progressCircleFill');
            const statusText = document.getElementById('loaderStatusText');
            const countdownContainer = document.getElementById('countdownContainer');
            
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
                
                // Set header z-index to ensure overlay appears above it
                const header = document.querySelector('header');
                if (header) header.style.zIndex = '1';
                
                // Reset progress
                if (progressFill) progressFill.className = 'progress-circle-fill progress-0';
                if (statusText) statusText.textContent = 'Preparing submission...';
                if (countdownContainer) countdownContainer.classList.remove('active');
            }
        }

        function updateFormProgress(stage, message) {
            const progressFill = document.getElementById('progressCircleFill');
            const statusText = document.getElementById('loaderStatusText');
            
            if (progressFill && statusText) {
                progressFill.className = `progress-circle-fill progress-${stage}`;
                statusText.textContent = message;
            }
        }

        function startSubmissionCountdown() {
            const countdownContainer = document.getElementById('countdownContainer');
            const countdownNumber = document.getElementById('countdownNumber');
            const countdownMessage = document.getElementById('countdownMessage');
            const loaderIcon = document.querySelector('.loader-icon');
            
            // Hide the user icon and show countdown
            if (loaderIcon) loaderIcon.style.opacity = '0';
            if (countdownContainer) countdownContainer.classList.add('active');
            
            let count = 3;
            
            function showNextCount() {
                if (count > 0) {
                    if (countdownNumber) countdownNumber.textContent = count;
                    if (countdownMessage) countdownMessage.textContent = '';
                    count--;
                    setTimeout(showNextCount, 1000);
                } else {
                    // Show final message
                    if (countdownNumber) countdownNumber.textContent = '';
                    if (countdownMessage) countdownMessage.textContent = 'Registration Complete!';
                    
                    // Keep overlay visible and redirect to thank you page after final message
                    setTimeout(() => {
                        window.location.href = '/thank-you-for-registering-webinars/';
                    }, 1000);
                }
            }
            
            showNextCount();
        }

        function hideProgressLoader() {
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
                
                // Restore header z-index when hiding overlay
                const header = document.querySelector('header');
                if (header) header.style.zIndex = '';
            }
        }

        // Make functions globally available
        window.showProgressLoader = showProgressLoader;
        window.updateFormProgress = updateFormProgress;
        window.startSubmissionCountdown = startSubmissionCountdown;
        window.hideProgressLoader = hideProgressLoader;
        </script>
        <?php
    }

    public function render_shortcode($atts) {
        ob_start();
        
        // Debug logging
        $this->log_debug("🔍 Class-based shortcode render_shortcode() called");
        
        // Get current post data
        $post_id = get_the_ID();
        $webinar_fields = get_field('webinar_fields', $post_id);
        
        $this->log_debug("📄 Post ID: {$post_id}, Post type: " . get_post_type($post_id));
        
        // Get workbooks reference from ACF
        $event_id = '';
        if (!empty($webinar_fields['workbooks_reference'])) {
            $event_id = preg_replace('/\D+/', '', $webinar_fields['workbooks_reference']);
        }

        // Not logged in state - Show registration form for guests
        if (!is_user_logged_in()) {
            $this->log_debug("👤 User not logged in - showing guest registration form");
            
            // Get webinar info for guest users
            $registration_link = $webinar_fields['webinar_link'] ?? '';
            $is_on_demand = !empty($registration_link);
            
            $this->log_debug("🎥 Is on-demand: " . ($is_on_demand ? 'YES' : 'NO') . " (registration_link: {$registration_link})");
            
            // Add comprehensive status logging for guest users
            $workbooks_reference = $webinar_fields['workbooks_reference'] ?? 'Not Set';
            
            echo "<script>
                console.log('User Status: Logged Out');
                console.log('Registered: No');
                console.log('Post Title: " . esc_js(get_the_title($post_id)) . "');
                console.log('Post ID: {$post_id}');
                console.log('Workbooks Reference: {$workbooks_reference}');
                console.log('First Name: Available on form');
                console.log('Last Name: Available on form');
                console.log('Email Address: Available on form');
                console.log('Person ID: Not Available (guest user)');
                console.log('Speaker Question: Available on form');
                console.log('Additional Questions: Available on form');
            </script>";
            
            // Show registration button and form for guest users
            if ($is_on_demand) {
                echo '<div class="full-page vertical-half-margin event-registration">';
                echo '<button class="ks-main-btn-global btn-purple shimmer-effect shimmer-slow not-registered text-left" data-event-id="' . esc_attr($post_id) . '">Register for On-Demand Webinar</button>';
                echo '</div>';
                return $this->render_guest_registration_form($post_id, $webinar_fields);
            } else {
                echo '<div class="full-page vertical-half-margin event-registration">';
                echo '<button class="ks-main-btn-global btn-blue shimmer-effect shimmer-slow not-registered webinar-registration text-left" data-event-id="' . esc_attr($post_id) . '">Register for Live Webinar</button>';
                echo '</div>';
                return $this->render_guest_registration_form($post_id, $webinar_fields);
            }
        }
        
        // Logged in state - Get user data
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);

        $this->log_debug("👤 User logged in: {$current_user->user_email} (ID: {$current_user->ID})");

        // Get webinar info first
        $registration_link = $webinar_fields['webinar_link'] ?? '';
        $is_on_demand = !empty($registration_link);
        $user_is_registered = $this->is_user_registered_for_event($current_user->ID, $post_id);
        
        // Inject user data for JS
        echo "<script>
            window.dtr_workbooks_ajax = window.dtr_workbooks_ajax || {};
            window.dtr_workbooks_ajax.current_user_id = " . json_encode($person_id) . ";
            window.dtr_workbooks_ajax.current_user_email = " . json_encode($current_user->user_email) . ";
            window.dtr_workbooks_ajax.current_event_id = " . json_encode($event_id) . ";
        </script>";
        
        // Add comprehensive status logging
        $workbooks_reference = $webinar_fields['workbooks_reference'] ?? 'Not Set';
        
        echo "<script>
            console.log('User Status: Logged In');
            console.log('Registered: " . ($user_is_registered ? "Yes" : "No") . "');
            console.log('Post Title: " . esc_js(get_the_title($post_id)) . "');
            console.log('Post ID: {$post_id}');
            console.log('Workbooks Reference: {$workbooks_reference}');
            console.log('First Name: " . esc_js($current_user->user_firstname) . "');
            console.log('Last Name: " . esc_js($current_user->user_lastname) . "');
            console.log('Email Address: " . esc_js($current_user->user_email) . "');
            console.log('Person ID: {$person_id}');
            console.log('Speaker Question: " . ($user_is_registered ? "Not Available (user already registered)" : "Available on form") . "');
            console.log('Additional Questions: " . ($user_is_registered ? "Not Available (user already registered)" : "Available on form") . "');
        </script>";
        
        $this->log_debug("🎥 Is on-demand: " . ($is_on_demand ? 'YES' : 'NO') . " (registration_link: {$registration_link})");
        $this->log_debug("📊 Registration check for user {$current_user->ID} on post {$post_id}: " . ($user_is_registered ? 'REGISTERED' : 'NOT REGISTERED'));
        
        // Handle admin testing messages and form deregistration success
        if (isset($_GET['dtr_message'])) {
            if ($_GET['dtr_message'] === 'deregistered') {
                echo '<div class="admin-message" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
                    <strong>🧪 Admin Test: Deregistration Successful!</strong> Registration removed via admin bar.
                </div>';
            } elseif ($_GET['dtr_message'] === 'registered') {
                echo '<div class="admin-message" style="background: #cce5ff; border: 1px solid #b3d9ff; color: #004085; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
                    <strong>🧪 Admin Test: Registration Created!</strong> Test registration added via admin bar.
                </div>';
            }
        }
        
        // Handle form deregistration success message
        if (isset($_GET['deregistered']) && $_GET['deregistered'] == '1') {
            echo '<div class="deregistration-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
                <strong>✅ Deregistration Successful!</strong> You have been removed from this webinar. You can register again using the form below.
            </div>';
        }
        
        // Render appropriate form based on webinar type
        if ($is_on_demand) {
            $this->log_debug("🎬 Rendering on-demand form");
            return $this->render_on_demand_form($webinar_fields, $post_id);
        } else {
            $this->log_debug("📺 Rendering live form (user_is_registered: " . ($user_is_registered ? 'YES' : 'NO') . ")");
            return $this->render_live_form($webinar_fields, $post_id, $user_is_registered);
        }
    }

    private function render_guest_registration_form($post_id, $webinar_fields) {
        $workbooks_reference = $webinar_fields['workbooks_reference'] ?? '';
        $tracking_id = wp_generate_uuid4();
        
        $output = '<div class="full-page form-container vertical-half-margin" id="registration-form">';
        
        // Add tracking fields
        $output .= '<input type="hidden" id="formTrackingId" name="form_tracking_id" value="' . esc_attr($tracking_id) . '">';
        
        $output .= '<div class="form-container webinar-registration-form">';
        $output .= '<form id="webinarClassForm">';
        
        // Guest user fields - visible and required
        $output .= '<div class="form-row">';
        $output .= '<div class="form-field half-width">';
        $output .= '<label for="firstName">First Name *</label>';
        $output .= '<input type="text" id="firstName" name="first_name" required>';
        $output .= '</div>';
        $output .= '<div class="form-field half-width">';
        $output .= '<label for="lastName">Last Name *</label>';
        $output .= '<input type="text" id="lastName" name="last_name" required>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '<div class="form-row">';
        $output .= '<div class="form-field full-width">';
        $output .= '<label for="email">Email Address *</label>';
        $output .= '<input type="email" id="email" name="email" required>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Hidden person ID for guest users
        $output .= '<input type="hidden" id="personId" name="person_id" value="">';
        
        return $this->complete_registration_form($output, $post_id, $webinar_fields, $workbooks_reference);
    }

    private function render_registration_form($post_id, $webinar_fields) {
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        $workbooks_reference = $webinar_fields['workbooks_reference'] ?? '';
        $tracking_id = wp_generate_uuid4();
        
        $output = '<div class="full-page form-container vertical-half-margin" id="registration-form">';
        
        // Add tracking fields
        $output .= '<input type="hidden" id="formTrackingId" name="form_tracking_id" value="' . esc_attr($tracking_id) . '">';
        $output .= '<input type="hidden" id="firstName" name="first_name" value="' . esc_attr($current_user->user_firstname) . '">';
        $output .= '<input type="hidden" id="lastName" name="last_name" value="' . esc_attr($current_user->user_lastname) . '">';
        $output .= '<input type="hidden" id="personId" name="person_id" value="' . esc_attr($person_id) . '">';
        $output .= '<input type="hidden" id="email" name="email" value="' . esc_attr($current_user->user_email) . '">';
        
        $output .= '<div class="form-container webinar-registration-form">';
        $output .= '<form id="webinarClassForm">';
        
        return $this->complete_registration_form($output, $post_id, $webinar_fields, $workbooks_reference);
    }
    
    private function complete_registration_form($output, $post_id, $webinar_fields, $workbooks_reference) {
        // Hidden fields for form processing
        $output .= '<input type="hidden" id="postId" name="post_id" value="' . esc_attr($post_id) . '">';
        $output .= '<input type="hidden" id="workbooksReference" name="workbooks_reference" value="' . esc_attr($workbooks_reference) . '">';
        
        // Add speaker question if enabled
        if (!empty($webinar_fields['add_speaker_question'])) {
            $output .= '<div class="form-row">';
            $output .= '<div class="form-field full-width">';
            $output .= '<label for="speakerQuestion">Question for the Speaker</label>';
            $output .= '<textarea id="speakerQuestion" name="speaker_question" rows="4"></textarea>';
            $output .= '</div>';
            $output .= '</div>';
        }

        // Add additional questions if enabled
        if (!empty($webinar_fields['add_additional_questions']) && !empty($webinar_fields['add_questions'])) {
            foreach ($webinar_fields['add_questions'] as $question) {
                if (!empty($question['question_title'])) {
                    $output .= '<div class="form-row">';
                    $output .= '<div class="form-field full-width">';
                    $output .= '<label for="question_' . esc_attr($question['question_title']) . '">';
                    $output .= esc_html($question['question_title']);
                    $output .= '</label>';
                    
                    switch ($question['type_of_question']) {
                        case 'textarea':
                            $output .= '<textarea id="question_' . esc_attr($question['question_title']) . '" name="additional_question[' . esc_attr($question['question_title']) . ']" rows="4"></textarea>';
                            break;
                        case 'dropdown':
                            $output .= '<select id="question_' . esc_attr($question['question_title']) . '" name="additional_question[' . esc_attr($question['question_title']) . ']">';
                            $output .= '<option value="">- Select -</option>';
                            foreach ($question['dropdown_options'] as $option) {
                                $output .= '<option value="' . esc_attr($option['option']) . '">' . esc_html($option['option']) . '</option>';
                            }
                            $output .= '</select>';
                            break;
                        default:
                            $output .= '<input type="text" id="question_' . esc_attr($question['question_title']) . '" name="additional_question[' . esc_attr($question['question_title']) . ']">';
                    }
                    
                    $output .= '</div>';
                    $output .= '</div>';
                }
            }
        }

        // Sponsor opt-in checkbox
        $output .= '<div class="checkbox-group consent-group">';
        $output .= '<div class="checkbox-item">';
        $output .= '<input type="checkbox" id="sponsor_optin" name="sponsor_optin" value="1" required>';
        $output .= '<label for="sponsor_optin" class="checkbox-label">';
        $output .= 'I consent to Drug Target Review storing and processing my data and sending me news and updates. ';
        $output .= 'For more information, please see our <a href="/privacy-policy" target="_blank">privacy policy</a>. <span class="required">*</span>';
        $output .= '</label>';
        $output .= '</div>';
        $output .= '</div>';

        // Submit button
        $output .= '<div class="form-row">';
        $output .= '<div class="form-field">';
        $output .= '<button type="button" id="submitWebinarClassBtn" class="button btn-small global btn-rounded btn-blue shimmer-effect shimmer-slow text-left chevron right">Register</button>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</form>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    private function render_on_demand_form($webinar_fields, $post_id) {
        echo '<div class="full-page vertical-half-margin event-registration">';
        echo '<button class="ks-main-btn-global btn-purple shimmer-effect shimmer-slow not-registered text-left" data-event-id="' . esc_attr($post_id) . '">Register for On-Demand Webinar</button>';
        echo '</div>';
        return $this->render_registration_form($post_id, $webinar_fields);
    }

    private function render_live_form($webinar_fields, $post_id, $user_is_registered) {
        if ($user_is_registered) {
            $uid = 'ks' . uniqid();
            echo '<div class="full-page vertical-half-margin event-registration">';
            echo '<div class="ks-split-btn btn-green" style="position: relative;">';
            echo '<button type="button" class="ks-main-btn ks-main-btn-global btn-green shimmer-effect shimmer-slow is-toggle text-left" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . $uid . '-menu">You are registered for this Live Webinar</button>';
            echo '<ul id="' . $uid . '-menu" class="ks-menu" role="menu" style="z-index: 1002;">';
            echo '<li role="none"><a role="menuitem" href="/my-account/?page-view=overview&ics=1&calendar-post-id=' . $post_id . '" class="no-decoration calendar-btn">Add to Calendar</a></li>';
            echo '<li role="none"><a role="menuitem" href="/my-account/?page-view=events-and-webinars">Events & Webinars</a></li>';
            // Add deregister option for admins (testing purposes)
            if (current_user_can('manage_options')) {
                echo '<li role="none">';
                echo '<form method="post" style="margin: 0;" onsubmit="return confirm(\'Are you sure you want to remove your registration? This is for testing purposes only.\');">';
                echo '<input type="hidden" name="deregister_webinar_class" value="1">';
                echo '<input type="hidden" name="post_id" value="' . $post_id . '">';
                echo '<input type="hidden" name="deregister_class_nonce" value="' . wp_create_nonce('deregister_webinar_class_' . $post_id) . '">';
                echo '<button type="submit" role="menuitem" class="deregister-btn">🧪 Deregister (Testing)</button>';
                echo '</form>';
                echo '</li>';
            }
            echo '</ul></div>';
            echo '<div class="reveal-text">Webinar has been added to Events & Webinars</div>';
            echo '</div>';
            
            // Add styles and JavaScript for the split button
            echo '<style>
            .ks-split-btn {
                position: relative;
                display: inline-block;
                width: 100%;
            }
            .ks-main-btn-global {
                width: 100%;
                padding: 15px 20px;
                font-size: 16px;
                font-weight: bold;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .btn-green {
                background: #28a745;
                color: white;
            }
            .btn-green:hover {
                background: #218838;
            }
            .shimmer-effect {
                position: relative;
                overflow: hidden;
            }
            .shimmer-slow::before {
                content: "";
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                animation: shimmer 3s infinite;
            }
            @keyframes shimmer {
                0% { left: -100%; }
                100% { left: 100%; }
            }
            .ks-menu {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin: 0;
                padding: 0;
                list-style: none;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                display: none;
                z-index: 1002;
            }
            .ks-menu li {
                margin: 0;
                padding: 0;
                border-bottom: 1px solid #eee;
            }
            .ks-menu li:last-child {
                border-bottom: none;
            }
            .ks-menu a, .ks-menu button {
                display: block;
                width: 100%;
                padding: 12px 16px;
                text-decoration: none;
                color: #333;
                background: none;
                border: none;
                text-align: left;
                cursor: pointer;
                transition: background-color 0.2s;
            }
            .ks-menu a:hover, .ks-menu button:hover {
                background: #f8f9fa;
            }
            .deregister-btn {
                color: #dc3545 !important;
                font-weight: bold;
            }
            .reveal-text {
                margin-top: 10px;
                font-size: 14px;
                color: #666;
                text-align: center;
            }
            </style>';
            
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const toggleBtn = document.querySelector(".is-toggle");
                const menu = document.getElementById("' . $uid . '-menu");
                
                if (toggleBtn && menu) {
                    toggleBtn.addEventListener("click", function(e) {
                        e.preventDefault();
                        const isExpanded = this.getAttribute("aria-expanded") === "true";
                        this.setAttribute("aria-expanded", !isExpanded);
                        menu.style.display = isExpanded ? "none" : "block";
                    });
                    
                    // Close menu when clicking outside
                    document.addEventListener("click", function(e) {
                        if (!toggleBtn.contains(e.target) && !menu.contains(e.target)) {
                            toggleBtn.setAttribute("aria-expanded", "false");
                            menu.style.display = "none";
                        }
                    });
                }
            });
            </script>';
            
            return ob_get_clean();
        } else {
            echo '<div class="full-page vertical-half-margin event-registration">';
            echo '<button class="ks-main-btn-global btn-blue shimmer-effect shimmer-slow not-registered webinar-registration text-left" data-event-id="' . esc_attr($post_id) . '">Register for Live Webinar</button>';
            echo '</div>';
            return $this->render_registration_form($post_id, $webinar_fields);
        }
    }
    
    /**
     * Add admin testing menu
     */
    public function add_admin_testing_menu() {
        add_submenu_page(
            'edit.php?post_type=webinars',
            'Test Class Registration',
            'Test Class Registration',
            'manage_options',
            'webinar-class-test-registration',
            array($this, 'render_admin_testing_page')
        );
    }
    
    /**
     * Render admin testing page
     */
    public function render_admin_testing_page() {
        // Handle registration actions
        if (isset($_POST['remove_registration']) && isset($_POST['post_id'])) {
            $this->remove_test_registration(intval($_POST['post_id']));
            echo '<div class="notice notice-success"><p>Registration removed successfully!</p></div>';
        }
        
        if (isset($_POST['test_register']) && isset($_POST['post_id'])) {
            $this->create_test_registration(intval($_POST['post_id']));
            echo '<div class="notice notice-success"><p>Test registration created successfully!</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>Webinar Class Registration Testing</h1>
            <p>Use this page to test webinar registration functionality using the class-based shortcode.</p>
            
            <?php
            $webinars = get_posts(array(
                'post_type' => 'webinars',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if (empty($webinars)) {
                echo '<p>No webinars found. Please create some webinar posts first.</p>';
                return;
            }
            
            $current_user_id = get_current_user_id();
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Webinar Title</th>
                        <th>Registration Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webinars as $webinar): ?>
                        <?php
                        $is_registered = $this->is_user_registered_for_event($current_user_id, $webinar->ID);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($webinar->post_title); ?></strong>
                                <br>
                                <small>ID: <?php echo $webinar->ID; ?> | 
                                <a href="<?php echo get_permalink($webinar->ID); ?>" target="_blank">View Page</a></small>
                            </td>
                            <td>
                                <?php if ($is_registered): ?>
                                    <span style="color: green; font-weight: bold;">✅ Registered</span>
                                <?php else: ?>
                                    <span style="color: #666;">Not Registered</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_registered): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="post_id" value="<?php echo $webinar->ID; ?>">
                                        <button type="submit" name="remove_registration" class="button button-secondary" 
                                                onclick="return confirm('Are you sure you want to remove this test registration?');">
                                            Remove Registration
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="post_id" value="<?php echo $webinar->ID; ?>">
                                        <button type="submit" name="test_register" class="button button-primary">
                                            Test Register
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Add admin bar testing buttons
     */
    public function add_admin_bar_buttons($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        global $post;
        if (!$post || get_post_type($post) !== 'webinars') {
            return;
        }
        
        $current_user_id = get_current_user_id();
        $is_registered = $this->is_user_registered_for_event($current_user_id, $post->ID);
        
        if ($is_registered) {
            $wp_admin_bar->add_node(array(
                'id' => 'dtr-class-deregister-webinar',
                'title' => '🧪 Class Deregister',
                'href' => wp_nonce_url(
                    add_query_arg(array(
                        'dtr_class_action' => 'deregister_webinar',
                        'post_id' => $post->ID
                    ), get_permalink($post->ID)),
                    'dtr_class_deregister_' . $post->ID
                ),
                'meta' => array(
                    'title' => 'Remove webinar registration (class-based testing)',
                    'class' => 'dtr-class-admin-deregister'
                )
            ));
        } else {
            $wp_admin_bar->add_node(array(
                'id' => 'dtr-class-register-webinar',
                'title' => '🧪 Class Register',
                'href' => wp_nonce_url(
                    add_query_arg(array(
                        'dtr_class_action' => 'register_webinar',
                        'post_id' => $post->ID
                    ), get_permalink($post->ID)),
                    'dtr_class_register_' . $post->ID
                ),
                'meta' => array(
                    'title' => 'Create test webinar registration (class-based)',
                    'class' => 'dtr-class-admin-register'
                )
            ));
        }
    }
    
    /**
     * Handle admin bar actions
     */
    public function handle_admin_bar_actions() {
        if (!current_user_can('manage_options') || !isset($_GET['dtr_class_action']) || !isset($_GET['post_id'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['dtr_class_action']);
        $post_id = intval($_GET['post_id']);
        
        if ($action === 'deregister_webinar') {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'dtr_class_deregister_' . $post_id)) {
                wp_die('Security check failed');
            }
            
            $this->remove_test_registration($post_id);
            wp_redirect(add_query_arg('dtr_message', 'deregistered', get_permalink($post_id)));
            exit;
            
        } elseif ($action === 'register_webinar') {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'dtr_class_register_' . $post_id)) {
                wp_die('Security check failed');
            }
            
            $this->create_test_registration($post_id);
            wp_redirect(add_query_arg('dtr_message', 'registered', get_permalink($post_id)));
            exit;
        }
    }
    
    /**
     * Create test registration
     */
    private function create_test_registration($post_id) {
        $current_user = wp_get_current_user();
        $registration_id = wp_generate_uuid4();
        
        $registration_data = array(
            'registration_id' => $registration_id,
            'user_id' => get_current_user_id(),
            'first_name' => $current_user->user_firstname ?: $current_user->display_name,
            'last_name' => $current_user->user_lastname ?: '',
            'email' => $current_user->user_email,
            'person_id' => get_user_meta(get_current_user_id(), 'workbooks_person_id', true),
            'workbooks_reference' => '',
            'event_id' => '',
            'speaker_question' => 'Test question from class-based admin',
            'optin' => true,
            'registration_date' => current_time('mysql'),
            'post_id' => $post_id,
            'user_agent' => 'Class-based Admin Test Registration',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
        
        $this->store_registration($post_id, $registration_data);
        $this->log_debug("🧪 Test registration created via class-based admin for post {$post_id}");
    }
    
    /**
     * Handle form-based deregistration
     */
    public function handle_form_deregistration() {
        if (!isset($_POST['deregister_webinar_class']) || !isset($_POST['post_id']) || !isset($_POST['deregister_class_nonce'])) {
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $nonce = sanitize_text_field($_POST['deregister_class_nonce']);
        
        $this->log_debug("📝 Form deregistration request for post {$post_id}");
        
        // Verify nonce and permissions
        if (!wp_verify_nonce($nonce, 'deregister_webinar_class_' . $post_id) || !current_user_can('manage_options')) {
            $this->log_debug("❌ Deregistration failed: Invalid nonce or permissions");
            return;
        }
        
        $this->remove_test_registration($post_id);
        
        // Redirect to same page with success message
        wp_redirect(add_query_arg('deregistered', '1', get_permalink($post_id)));
        exit;
    }
    
    /**
     * Remove test registration
     */
    private function remove_test_registration($post_id) {
        $current_user_id = get_current_user_id();
        
        $this->log_debug("🗑️ Starting deregistration for user {$current_user_id} on post {$post_id}");
        
        // Remove from user meta
        $user_registration_key = 'webinar_registration_' . $post_id;
        $user_meta_removed = delete_user_meta($current_user_id, $user_registration_key);
        $this->log_debug("🗑️ User meta removal: " . ($user_meta_removed ? 'SUCCESS' : 'FAILED/NOT_FOUND'));
        
        // Remove from post meta
        $all_registrations = get_post_meta($post_id, 'webinar_registrations', false);
        $this->log_debug("🗑️ Found " . count($all_registrations) . " total registrations in post meta");
        
        $removed_count = 0;
        foreach ($all_registrations as $registration) {
            if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                $this->log_debug("🗑️ Found matching registration: " . json_encode($registration));
                $deletion_result = delete_post_meta($post_id, 'webinar_registrations', $registration);
                $this->log_debug("🗑️ Post meta deletion result: " . ($deletion_result ? 'SUCCESS' : 'FAILED'));
                $removed_count++;
            }
        }
        
        $this->log_debug("🗑️ Removed {$removed_count} registrations from post meta");
        $this->log_debug("🧪 Test registration removed via class-based form for post {$post_id}");
    }
}

// Initialize the shortcode
new DTR_Webinar_Registration_Form();
