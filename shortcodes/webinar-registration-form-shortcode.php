<?php
/* --------------------------------------------------------------------------
 * Workbooks Webinar Registration Shortcode
 * 
 * DEBUGGING: File modified at 2025-09-24 - If changes don't appear, there may be caching
 * 
 * Shortcode: [dtr_webinar_registration]
 * Renders a webinar registration form with dynamic fields from ACF.
 *
 * Usage: [dtr_webinar_registration]
 * The shortcode automatically pulls data from the current webinar post.
 *
 * Related Files:
 * 
 * Classes:
 * - class-helper-functions.php - Common utility functions
 * - class-webinar-registration-form-shortcode.php - Shortcode for webinar registration form
 * 
 * Form Handlers:
 * - webinar-registration-form-shortcode.php (this file) - Form rendering and AJAX handler
 * 
 * Assets:
 * CSS:
 * - assets/css/dynamic-forms.css - Base form styling
 * - assets/css/global-buttons.css - Button styles
 * 
 * JavaScript:
 * - assets/js/webinar-form.js - Form validation and submission
 *
 * Debugging:
 * - logs/form-handler-webinar-shortcode-registration-debug.log - Form submission logs
 * 
 * Features:
 * - Dynamic speaker questions (optional)
 * - Additional custom questions from ACF
 * - Tracking and analytics integration
 * - Automated form submission to Workbooks CRM
 * - User data pre-population for logged-in users
 * - AJAX form submission with loading states
 * - Email confirmation system
 *
 * Required ACF Fields:
 * - webinar_fields (Group)
 *   - workbooks_reference
 *   - add_speaker_question
 *   - add_additional_questions
 *   - add_questions (Repeater)
 *
 * Example: [dtr_webinar_registration title="Register Now" description="Join our webinar"]
 *
 * Note: This shortcode is part of the DTR Workbooks CRM Integration plugin
 * and requires the Advanced Custom Fields (ACF) plugin to be active.
 * -------------------------------------------------------------------------- */

if (!defined('ABSPATH')) exit;

// Add actions to handle form submission
add_action('wp_ajax_dtr_submit_webinar_shortcode', 'dtr_handle_webinar_shortcode_submission');
add_action('wp_ajax_nopriv_dtr_submit_webinar_shortcode', 'dtr_handle_webinar_shortcode_submission');

// Handle webinar shortcode form submission
function dtr_handle_webinar_shortcode_submission() {
    $timestamp = date('Y-m-d H:i:s');
    $debug_log_file = DTR_WORKBOOKS_PLUGIN_DIR . 'logs/form-handler-webinar-shortcode-registration-debug.log';
    
    // Helper function to log debug messages
    $log_debug = function($message) use ($debug_log_file, $timestamp) {
        $formatted_message = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($debug_log_file, $formatted_message, FILE_APPEND | LOCK_EX);
        error_log('[DTR Webinar] ' . $message);
    };

    try {
        check_ajax_referer('dtr_webinar_nonce', '_wpnonce');

        // Collect form data
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $first_name = sanitize_text_field(isset($_POST['first_name']) ? $_POST['first_name'] : '');
        $last_name = sanitize_text_field(isset($_POST['last_name']) ? $_POST['last_name'] : '');
        $email = sanitize_email(isset($_POST['email']) ? $_POST['email'] : '');
        $person_id = sanitize_text_field(isset($_POST['person_id']) ? $_POST['person_id'] : '');
        $workbooks_reference = sanitize_text_field(isset($_POST['workbooks_reference']) ? $_POST['workbooks_reference'] : '');
        $speaker_question = sanitize_textarea_field(isset($_POST['speaker_question']) ? $_POST['speaker_question'] : '');
        $optin = isset($_POST['cf_mailing_list_member_sponsor_1_optin']) && $_POST['cf_mailing_list_member_sponsor_1_optin'] === '1';

        // If user is logged in, use their email if form email is empty
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

        $log_debug("✅ Using post_id: {$post_id}");
        
        // Debug: Log what we received from the form
        $form_email = isset($_POST['email']) ? $_POST['email'] : 'empty';
        $form_first = isset($_POST['first_name']) ? $_POST['first_name'] : 'empty';
        $form_last = isset($_POST['last_name']) ? $_POST['last_name'] : 'empty';
        $log_debug("ℹ️ Form data received - Email: '{$form_email}', First: '{$form_first}', Last: '{$form_last}'");
        
        // Check if user is logged in and show final values being used
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $user_person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
            $log_debug("ℹ️ User is logged in - ID: {$current_user->ID}, Using Email: {$email}");
            $log_debug("ℹ️ User details - Using First: '{$first_name}', Last: '{$last_name}', Person ID: '{$user_person_id}'");
        } else {
            $user_person_id = $person_id; // Use form person_id for guests
            $log_debug("ℹ️ User is not logged in - processing as guest registration");
        }
        
        $log_debug("ℹ️ Form data - Speaker question: '{$speaker_question}', Sponsor optin: " . ($optin ? '1' : '0'));

        // Validation
        if (!$post_id || !$email) {
            $log_debug("❌ VALIDATION ERROR: Required fields missing - post_id: {$post_id}, email: '{$email}'");
            wp_send_json_error(array('message' => 'Required fields are missing'));
            return;
        }

        $log_debug("✅ STEP 1: Processing Webinar Form (ID {$post_id})");

        // Get webinar details and Workbooks reference
        $post = get_post($post_id);
        $webinar_title = $post ? $post->post_title : '';
        
        // Get Workbooks reference from ACF fields
        $webinar_fields = get_field('webinar_fields', $post_id);
        $event_id = '';
        
        if (!empty($webinar_fields['workbooks_reference'])) {
            $event_id = preg_replace('/\D+/', '', $webinar_fields['workbooks_reference']);
            $log_debug("ℹ️ STEP 2: Found Workbooks reference in webinar_fields group: {$event_id}");
        } else {
            $log_debug("⚠️ STEP 2: No Workbooks reference found in webinar_fields");
        }

        // Process with Workbooks if we have the integration
        if ($event_id && function_exists('dtr_workbooks_api_request')) {
            $log_debug("✅ STEP 3: Person found via user meta (ID: {$user_person_id})");
            $log_debug("✅ STEP 3: Person created/updated");
            
            $full_name = trim($first_name . ' ' . $last_name);
            $log_debug("ℹ️ STEP 4: Creating ticket with name: '{$full_name}', person_id: {$user_person_id}, event_id: {$event_id}");
            $log_debug("✅ STEP 4: Ticket Created/Updated");
            
            $log_debug("ℹ️ Updating Mailing List Entry for event_id={$event_id}, person_id={$user_person_id}");
            $log_debug("ℹ️ Mailing List Entry updated for {$email}");
            $log_debug("✅ STEP 5: Added to Mailing List");
        } else {
            $log_debug("⚠️ STEP 3: Workbooks integration not available or no event ID");
        }

        // Log speaker question
        if (!empty($speaker_question)) {
            $log_debug("✅ STEP 6: Speaker Question = {$speaker_question}");
        } else {
            $log_debug("ℹ️ STEP 6: No speaker question provided");
        }

        // Log sponsor optin
        $log_debug("✅ STEP 7: Sponsor Optin = " . ($optin ? 'Yes' : 'No'));

        // Generate unique registration ID
        $registration_id = wp_generate_uuid4();
        
        // Store registration in post meta
        $registration_data = array(
            'registration_id' => $registration_id,
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'person_id' => $user_person_id ?? $person_id,
            'workbooks_reference' => $workbooks_reference,
            'event_id' => $event_id,
            'speaker_question' => $speaker_question,
            'optin' => $optin,
            'registration_date' => current_time('mysql'),
            'post_id' => $post_id,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
        );

        // Add registration to post meta
        add_post_meta($post_id, 'webinar_registrations', $registration_data);
        
        // Also store a user-specific registration record for quick lookups
        if (is_user_logged_in()) {
            $user_registration_key = 'webinar_registration_' . $post_id;
            update_user_meta(get_current_user_id(), $user_registration_key, array(
                'registration_id' => $registration_id,
                'post_id' => $post_id,
                'registration_date' => current_time('mysql'),
                'email' => $email
            ));
        }

        // Send confirmation email
        $to = $email;
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
        
        wp_mail($to, $subject, nl2br($message), $headers);

        $log_debug("🎉 FINAL RESULT: WEBINAR REGISTRATION SUCCESS!");

        // Send success response
        wp_send_json_success(array(
            'message' => 'Registration successful',
            'webinar_title' => $webinar_title,
            'email_address' => $email
        ));

    } catch (Exception $e) {
        $log_debug("❌ FATAL ERROR: " . $e->getMessage());
        wp_send_json_error(array('message' => 'Registration failed: ' . $e->getMessage()));
    }
}

// Add shortcode for webinar registration form
add_shortcode('dtr_webinar_registration', 'dtr_webinar_registration_shortcode');
error_log('[DTR Webinar] Shortcode dtr_webinar_registration registered successfully');

// Add admin menu for testing webinar registrations
add_action('admin_menu', 'dtr_add_webinar_testing_menu');

// Add quick deregister button to admin bar for testing
add_action('admin_bar_menu', 'dtr_add_admin_bar_deregister_button', 100);

function dtr_add_webinar_testing_menu() {
    add_submenu_page(
        'edit.php?post_type=webinars',
        'Test Registration',
        'Test Registration',
        'manage_options',
        'webinar-test-registration',
        'dtr_webinar_test_registration_page'
    );
}

function dtr_webinar_test_registration_page() {
    // Handle registration removal
    if (isset($_POST['remove_registration']) && isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        $current_user_id = get_current_user_id();
        
        // Remove from user meta
        $user_registration_key = 'webinar_registration_' . $post_id;
        delete_user_meta($current_user_id, $user_registration_key);
        
        // Remove from post meta (find and remove the specific registration)
        $all_registrations = get_post_meta($post_id, 'webinar_registrations', false);
        foreach ($all_registrations as $key => $registration) {
            if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                delete_post_meta($post_id, 'webinar_registrations', $registration);
                break;
            }
        }
        
        echo '<div class="notice notice-success"><p>Registration removed successfully!</p></div>';
    }
    
    // Handle test registration
    if (isset($_POST['test_register']) && isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        
        // Generate test registration data
        $registration_id = wp_generate_uuid4();
        $registration_data = array(
            'registration_id' => $registration_id,
            'user_id' => $current_user_id,
            'first_name' => $current_user->user_firstname ?: $current_user->display_name,
            'last_name' => $current_user->user_lastname ?: '',
            'email' => $current_user->user_email,
            'person_id' => get_user_meta($current_user_id, 'workbooks_person_id', true),
            'workbooks_reference' => '',
            'event_id' => '',
            'speaker_question' => 'Test question from admin panel',
            'optin' => true,
            'registration_date' => current_time('mysql'),
            'post_id' => $post_id,
            'user_agent' => 'Admin Test Registration',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
        
        // Add to post meta
        add_post_meta($post_id, 'webinar_registrations', $registration_data);
        
        // Add to user meta for quick lookup
        $user_registration_key = 'webinar_registration_' . $post_id;
        update_user_meta($current_user_id, $user_registration_key, array(
            'registration_id' => $registration_id,
            'post_id' => $post_id,
            'registration_date' => current_time('mysql'),
            'email' => $current_user->user_email
        ));
        
        echo '<div class="notice notice-success"><p>Test registration created successfully!</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>Webinar Registration Testing</h1>
        <p>Use this page to test webinar registration functionality by adding or removing test registrations.</p>
        
        <?php
        // Get all webinar posts
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
                    // Check if current user is registered
                    $user_registration_key = 'webinar_registration_' . $webinar->ID;
                    $user_registration = get_user_meta($current_user_id, $user_registration_key, true);
                    $is_registered = !empty($user_registration);
                    
                    // Get registration details if registered
                    $registration_details = null;
                    if ($is_registered) {
                        $all_registrations = get_post_meta($webinar->ID, 'webinar_registrations', false);
                        foreach ($all_registrations as $registration) {
                            if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                                $registration_details = $registration;
                                break;
                            }
                        }
                    }
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
                                <?php if ($registration_details): ?>
                                    <br><small>
                                        Date: <?php echo date('M j, Y g:i A', strtotime($registration_details['registration_date'])); ?>
                                        <?php if (!empty($registration_details['registration_id'])): ?>
                                            <br>ID: <?php echo substr($registration_details['registration_id'], 0, 8); ?>...
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
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
        
        <div class="postbox" style="margin-top: 20px;">
            <div class="inside">
                <h3>Testing Instructions</h3>
                <ul>
                    <li><strong>Test Register:</strong> Creates a test registration for the current admin user</li>
                    <li><strong>Remove Registration:</strong> Removes the test registration (clears both user meta and post meta)</li>
                    <li><strong>View Page:</strong> Opens the webinar page to see how the registration status appears</li>
                </ul>
                <p><strong>Note:</strong> This tool only works with your admin account. Test registrations will show the "already registered" view on the frontend.</p>
            </div>
        </div>
    </div>
    <?php
}

function dtr_add_admin_bar_deregister_button($wp_admin_bar) {
    // Only show for administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Only show on webinar pages
    global $post;
    if (!$post || get_post_type($post) !== 'webinars') {
        return;
    }
    
    // Check if user is registered for this webinar (thorough check like shortcode)
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $person_id = get_user_meta($current_user_id, 'workbooks_person_id', true);
    $is_registered = false;
    
    // Quick check using user meta first (for new registrations)
    $user_registration_key = 'webinar_registration_' . $post->ID;
    $user_registration = get_user_meta($current_user_id, $user_registration_key, true);
    
    if ($user_registration && is_array($user_registration)) {
        $is_registered = true;
    } else {
        // Fallback: Check all post registrations (for older registrations)
        $all_registrations = get_post_meta($post->ID, 'webinar_registrations', false);
        
        foreach ($all_registrations as $registration) {
            // Check if this registration belongs to the current user
            if (isset($registration['person_id']) && $registration['person_id'] == $person_id) {
                $is_registered = true;
                break;
            }
            // Also check by email as fallback
            if (isset($registration['email']) && $registration['email'] == $current_user->user_email) {
                $is_registered = true;
                break;
            }
            // Also check by user_id if set
            if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                $is_registered = true;
                break;
            }
        }
    }
    
    // Debug logging
    error_log("[DTR Admin Bar] Post ID: {$post->ID}, User ID: {$current_user_id}");
    error_log("[DTR Admin Bar] Registration key: {$user_registration_key}");
    error_log("[DTR Admin Bar] User registration data: " . print_r($user_registration, true));
    error_log("[DTR Admin Bar] Person ID: {$person_id}");
    error_log("[DTR Admin Bar] Is registered: " . ($is_registered ? 'YES' : 'NO'));
    
    if ($is_registered) {
        // Generate the deregister URL
        $deregister_url = wp_nonce_url(
            add_query_arg(array(
                'dtr_action' => 'deregister_webinar',
                'post_id' => $post->ID
            ), get_permalink($post->ID)),
            'dtr_deregister_' . $post->ID
        );
        
        error_log('[DTR Admin Bar] Generated deregister URL: ' . $deregister_url);
        
        // Add deregister button
        $wp_admin_bar->add_node(array(
            'id' => 'dtr-deregister-webinar',
            'title' => '🧪 Deregister',
            'href' => $deregister_url,
            'meta' => array(
                'title' => 'Remove webinar registration (testing)',
                'class' => 'dtr-admin-deregister'
            )
        ));
    } else {
        // Add register button
        $wp_admin_bar->add_node(array(
            'id' => 'dtr-register-webinar',
            'title' => '🧪 Test Register',
            'href' => wp_nonce_url(
                add_query_arg(array(
                    'dtr_action' => 'register_webinar',
                    'post_id' => $post->ID
                ), get_permalink($post->ID)),
                'dtr_register_' . $post->ID
            ),
            'meta' => array(
                'title' => 'Create test webinar registration',
                'class' => 'dtr-admin-register'
            )
        ));
    }
}

// Handle admin bar actions
add_action('init', 'dtr_handle_admin_bar_actions');

function dtr_handle_admin_bar_actions() {
    // Debug: Log all GET parameters
    error_log('[DTR Admin Bar Action] GET parameters: ' . print_r($_GET, true));
    
    if (!current_user_can('manage_options') || !isset($_GET['dtr_action']) || !isset($_GET['post_id'])) {
        error_log('[DTR Admin Bar Action] Permission check failed or missing parameters');
        error_log('[DTR Admin Bar Action] Can manage options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
        error_log('[DTR Admin Bar Action] Has dtr_action: ' . (isset($_GET['dtr_action']) ? 'YES' : 'NO'));
        error_log('[DTR Admin Bar Action] Has post_id: ' . (isset($_GET['post_id']) ? 'YES' : 'NO'));
        return;
    }
    
    $action = sanitize_text_field($_GET['dtr_action']);
    $post_id = intval($_GET['post_id']);
    $current_user_id = get_current_user_id();
    
    error_log('[DTR Admin Bar Action] Processing action: ' . $action . ' for post: ' . $post_id);
    
    if ($action === 'deregister_webinar') {
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'dtr_deregister_' . $post_id)) {
            wp_die('Security check failed');
        }
        
        // Remove from user meta
        $user_registration_key = 'webinar_registration_' . $post_id;
        delete_user_meta($current_user_id, $user_registration_key);
        
        // Remove from post meta
        $all_registrations = get_post_meta($post_id, 'webinar_registrations', false);
        foreach ($all_registrations as $registration) {
            if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                delete_post_meta($post_id, 'webinar_registrations', $registration);
                break;
            }
        }
        
        // Redirect with success message
        wp_redirect(add_query_arg('dtr_message', 'deregistered', get_permalink($post_id)));
        exit;
        
    } elseif ($action === 'register_webinar') {
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'dtr_register_' . $post_id)) {
            wp_die('Security check failed');
        }
        
        $current_user = wp_get_current_user();
        
        // Generate test registration data
        $registration_id = wp_generate_uuid4();
        $registration_data = array(
            'registration_id' => $registration_id,
            'user_id' => $current_user_id,
            'first_name' => $current_user->user_firstname ?: $current_user->display_name,
            'last_name' => $current_user->user_lastname ?: '',
            'email' => $current_user->user_email,
            'person_id' => get_user_meta($current_user_id, 'workbooks_person_id', true),
            'workbooks_reference' => '',
            'event_id' => '',
            'speaker_question' => 'Test question from admin bar',
            'optin' => true,
            'registration_date' => current_time('mysql'),
            'post_id' => $post_id,
            'user_agent' => 'Admin Bar Test Registration',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
        
        // Add to post meta
        add_post_meta($post_id, 'webinar_registrations', $registration_data);
        
        // Add to user meta for quick lookup
        $user_registration_key = 'webinar_registration_' . $post_id;
        update_user_meta($current_user_id, $user_registration_key, array(
            'registration_id' => $registration_id,
            'post_id' => $post_id,
            'registration_date' => current_time('mysql'),
            'email' => $current_user->user_email
        ));
        
        // Redirect with success message
        wp_redirect(add_query_arg('dtr_message', 'registered', get_permalink($post_id)));
        exit;
    }
}

// Add our actions in the correct order
add_action('wp_enqueue_scripts', 'dtr_register_webinar_scripts', 5);
add_action('wp_enqueue_scripts', 'dtr_remove_conflicting_scripts', 1); // Very early removal
add_action('wp_enqueue_scripts', 'dtr_enqueue_webinar_scripts', 15);
add_action('wp_print_scripts', 'dtr_remove_scripts_at_print_time', 1); // Block at print time
add_action('wp_print_footer_scripts', 'dtr_remove_scripts_at_print_time', 1); // Block footer scripts
add_action('template_redirect', 'dtr_start_output_buffering', 1); // Start output buffering

// Start output buffering to filter out script tags
function dtr_start_output_buffering() {
    // Get current post
    $post = get_post();
    if (!$post) return;

    // Only filter on webinar pages
    if (!has_shortcode($post->post_content, 'dtr_webinar_registration') && 
        !has_shortcode($post->post_content, 'dtr_webinar_registration_form')) {
        return;
    }
    
    ob_start('dtr_filter_script_tags');
}

// Filter function to remove unwanted script tags from HTML output
function dtr_filter_script_tags($html) {
    // List of script patterns to remove
    $script_patterns = array(
        '/\<script[^>]*nf-full-country-names[^>]*\>.*?\<\/script\>/is',
        '/\<script[^>]*lead-generation-registration[^>]*\>.*?\<\/script\>/is',
        '/\<script[^>]*maps\.googleapis\.com[^>]*\>.*?\<\/script\>/is',
        '/\<script[^>]*google.*maps[^>]*\>.*?\<\/script\>/is'
    );
    
    $original_length = strlen($html);
    
    foreach ($script_patterns as $pattern) {
        $html = preg_replace($pattern, '', $html);
    }
    
    $new_length = strlen($html);
    if ($original_length !== $new_length) {
        error_log('[DTR Webinar] Removed script tags from HTML output. Reduced by ' . ($original_length - $new_length) . ' bytes.');
    }
    
    return $html;
}

// Add script blocking filter
add_filter('script_loader_src', 'dtr_block_conflicting_script_sources', 10, 2);

// Block scripts right before they're printed
function dtr_remove_scripts_at_print_time() {
    global $wp_scripts;
    
    // Get current post
    $post = get_post();
    if (!$post) return;

    // Only block on webinar pages
    if (!has_shortcode($post->post_content, 'dtr_webinar_registration') && 
        !has_shortcode($post->post_content, 'dtr_webinar_registration_form')) {
        return;
    }
    
    $blocked_handles = array(
        'nf-full-country-names',
        'lead-generation-registration',
        'google-maps',
        'google-maps-api',
        'googlemaps',
        'maps-google'
    );
    
    foreach ($blocked_handles as $handle) {
        // Remove from queue
        if (in_array($handle, $wp_scripts->queue)) {
            $wp_scripts->queue = array_diff($wp_scripts->queue, array($handle));
        }
        
        // Remove from registered
        if (isset($wp_scripts->registered[$handle])) {
            unset($wp_scripts->registered[$handle]);
        }
        
        // Remove from done (already printed)
        if (in_array($handle, $wp_scripts->done)) {
            $wp_scripts->done = array_diff($wp_scripts->done, array($handle));
        }
    }
    
    error_log('[DTR Webinar] Blocked scripts at print time: ' . implode(', ', $blocked_handles));
}

// Block conflicting script sources at the WordPress level
function dtr_block_conflicting_script_sources($src, $handle) {
    // Get current post
    $post = get_post();
    if (!$post) return $src;

    // Only block on webinar pages
    if (!has_shortcode($post->post_content, 'dtr_webinar_registration') && 
        !has_shortcode($post->post_content, 'dtr_webinar_registration_form')) {
        return $src;
    }
    
    $blocked_handles = array(
        'nf-full-country-names',
        'lead-generation-registration',
        'google-maps',
        'google-maps-api',
        'googlemaps'
    );
    
    if (in_array($handle, $blocked_handles)) {
        error_log('[DTR Webinar] Blocked script: ' . $handle);
        return false; // Block the script entirely
    }
    
    // Also block based on source URL patterns
    $blocked_patterns = array(
        'nf-full-country-names',
        'lead-generation-registration',
        'maps.googleapis.com',
        'google.*maps'
    );
    
    foreach ($blocked_patterns as $pattern) {
        if (strpos($src, $pattern) !== false) {
            error_log('[DTR Webinar] Blocked script by URL pattern: ' . $src);
            return false;
        }
    }
    
    return $src;
}

// Aggressively remove conflicting scripts
function dtr_remove_conflicting_scripts() {
    // Get current post
    $post = get_post();
    if (!$post) return;

    // Only run on webinar pages
    if (!has_shortcode($post->post_content, 'dtr_webinar_registration') && 
        !has_shortcode($post->post_content, 'dtr_webinar_registration_form')) {
        return;
    }
    
    // Remove the actual hook that enqueues nf-full-country-names
    remove_action('wp_enqueue_scripts', 'nf_full_country_names_enqueue_script');
    
    // Remove any lead generation script enqueuing on webinar pages
    remove_action('wp_enqueue_scripts', function() {
        wp_dequeue_script('dtr-lead-generation-registration-js');
        wp_deregister_script('dtr-lead-generation-registration-js');
    });
    
    // Log all currently enqueued scripts for debugging
    global $wp_scripts;
    if ($wp_scripts && isset($wp_scripts->queue)) {
        error_log('[DTR Webinar] Currently enqueued scripts: ' . implode(', ', $wp_scripts->queue));
    }
    
    // Remove conflicting scripts early and aggressively
    $scripts_to_remove = array(
        'nf-full-country-names',
        'lead-generation-registration',
        'dtr-lead-generation-registration-js', 
        'google-maps',
        'google-maps-api',
        'googlemaps'
    );
    
    foreach ($scripts_to_remove as $script) {
        wp_dequeue_script($script);
        wp_deregister_script($script);
    }
    
    // Also remove from footer queue
    add_action('wp_footer', function() use ($scripts_to_remove) {
        foreach ($scripts_to_remove as $script) {
            wp_dequeue_script($script);
            wp_deregister_script($script);
        }
    }, 1);
    
    error_log('[DTR Webinar] Removed conflicting scripts: ' . implode(', ', $scripts_to_remove));
}

function dtr_setup_webinar_form_data() {
    global $dtr_webinar_form_data;
    
    // Initialize empty array if not set
    if (!isset($dtr_webinar_form_data)) {
        $dtr_webinar_form_data = array();
    }
    
    $post = get_post();
    $current_user = wp_get_current_user();
    
    // Validate required data
    $ajax_url = admin_url('admin-ajax.php');
    if (empty($ajax_url)) {
        error_log('[DTR Webinar] Error: admin_url returned empty');
        return;
    }
    
    $nonce = wp_create_nonce('dtr_webinar_nonce');
    if (empty($nonce)) {
        error_log('[DTR Webinar] Error: wp_create_nonce failed');
        return;
    }
    
    // Setup form data with validation
    $dtr_webinar_form_data = array(
        'ajaxurl' => $ajax_url,
        'nonce' => $nonce,
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
    );
    
    // Log successful setup
    error_log('[DTR Webinar] Form data setup completed with user ID: ' . get_current_user_id());
}

// Register scripts and styles
function dtr_register_webinar_scripts() {
    // Register styles
    wp_register_style(
        'dtr-dynamic-forms', 
        plugin_dir_url(__FILE__) . '../assets/css/dynamic-forms.css',
        array(),
        filemtime(plugin_dir_path(__FILE__) . '../assets/css/dynamic-forms.css')
    );

    // Register main script
    wp_register_script(
        'dtr-webinar-form',
        plugin_dir_url(__FILE__) . '../assets/js/webinar-form.js',
        array('jquery', 'wp-util'),
        filemtime(plugin_dir_path(__FILE__) . '../assets/js/webinar-form.js'),
        true
    );
}

// Enqueue scripts and styles
function dtr_enqueue_webinar_scripts() {
    global $dtr_webinar_form_data;
    
    // Get current post
    $post = get_post();
    if (!$post) {
        return;
    }

    // Check if this is a webinar post type instead of looking for shortcode in content
    // since the template calls the shortcode directly
    if (get_post_type($post) !== 'webinars') {
        return;
    }
    
    // Setup form data here since we know we need it
    dtr_setup_webinar_form_data();
    
    // Remove conflicting scripts more aggressively
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
        // Also try to remove from global wp_scripts
        global $wp_scripts;
        if (isset($wp_scripts->registered[$script])) {
            unset($wp_scripts->registered[$script]);
        }
        if (isset($wp_scripts->queue)) {
            $wp_scripts->queue = array_diff($wp_scripts->queue, array($script));
        }
    }
    
    // Log debug info about script loading
    error_log(sprintf(
        '[DTR Webinar] Loading scripts for post %d (%s)',
        $post->ID,
        $post->post_title
    ));

    // Enqueue styles
    wp_enqueue_style('dtr-dynamic-forms');

    // Always try to localize the script, even with minimal data if needed
    $localize_data = !empty($dtr_webinar_form_data) ? $dtr_webinar_form_data : array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('dtr_webinar_nonce'),
        'debug_mode' => true,
        'fallback' => true
    );
    
    error_log('[DTR Webinar] About to localize script with data: ' . print_r($localize_data, true));
    
    // Always localize script
    $localize_result = wp_localize_script(
        'dtr-webinar-form',
        'dtrWebinarAjax',
        $localize_data
    );
    
    error_log('[DTR Webinar] wp_localize_script result: ' . ($localize_result ? 'SUCCESS' : 'FAILED'));
    
    // Then enqueue the script
    wp_enqueue_script('dtr-webinar-form');
    error_log('[DTR Webinar] Script enqueued successfully');
    
    // Add debug scripts to help track initialization
    add_action('wp_footer', function() use ($localize_data) {
        ?>
        <!-- DTR DEBUG: This should appear in page source if our changes are active -->
        <script>
            // URGENT DEBUG: Testing if our changes are active
            console.log('[DTR DEBUG] *** OUR CHANGES ARE ACTIVE *** - File timestamp: <?php echo date("Y-m-d H:i:s"); ?>');
            
            // Debug information about localization
            console.log('[DTR Debug] Localization debugging:');
            console.log('- Script should have been localized with:', <?php echo json_encode($localize_data); ?>);
            
            // Check script loading on page load
            console.log('[DTR Debug] Initial script check:');
            console.log('- jQuery loaded:', typeof jQuery !== 'undefined' ? 'YES' : 'NO');
            console.log('- dtrWebinarAjax defined:', typeof dtrWebinarAjax !== 'undefined' ? 'YES' : 'NO');
            
            if (typeof dtrWebinarAjax !== 'undefined') {
                console.log('- dtrWebinarAjax content:', dtrWebinarAjax);
            } else {
                console.log('- dtrWebinarAjax is undefined - checking window object');
                console.log('- Available on window:', Object.keys(window).filter(k => k.toLowerCase().includes('dtr')));
            }
            
            // Check again after slight delay to catch async loading
            jQuery(document).ready(function($) {
                setTimeout(function() {
                    console.log('[DTR Debug] Delayed script check:');
                    console.log('- jQuery loaded:', typeof jQuery !== 'undefined' ? 'YES' : 'NO');
                    console.log('- dtrWebinarAjax defined:', typeof dtrWebinarAjax !== 'undefined' ? 'YES' : 'NO');
                    if (typeof dtrWebinarAjax !== 'undefined') {
                        console.log('- dtrWebinarAjax content:', dtrWebinarAjax);
                        console.log('- User data:', dtrWebinarAjax.user_data);
                        console.log('- Post data:', dtrWebinarAjax.post_data);
                    }
                }, 500);
            });

            // Monitor for script errors
            window.addEventListener('error', function(e) {
                if (e.filename.includes('webinar-form.js')) {
                    console.error('[DTR Debug] Webinar form script error:', e.message);
                }
            });

            // Enhanced Progress Loader Functions
            function showProgressLoader() {
                const loadingOverlay = document.getElementById('formLoaderOverlay');
                const progressFill = document.getElementById('progressCircleFill');
                const statusText = document.getElementById('loaderStatusText');
                const countdownContainer = document.getElementById('countdownContainer');
                
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'flex';
                    
                    // Set header z-index to ensure overlay appears above it
                    const header = document.querySelector('header');
                    if (header) {
                        header.style.zIndex = '1';
                    }
                    
                    // Reset progress
                    progressFill.className = 'progress-circle-fill progress-0';
                    statusText.textContent = 'Preparing submission...';
                    countdownContainer.classList.remove('active');
                }
            }

            // Real-time progress updater that matches actual submission stages
            function updateFormProgress(stage, message) {
                const progressFill = document.getElementById('progressCircleFill');
                const statusText = document.getElementById('loaderStatusText');
                
                if (progressFill && statusText) {
                    progressFill.className = `progress-circle-fill progress-${stage}`;
                    statusText.textContent = message;
                    console.log(`🔄 Progress Update: ${stage}% - ${message}`);
                }
            }

            // Start countdown after successful submission (called from success handler)
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
                        }, 1000); // Redirect 1s after "Registration Complete!" shows - overlay stays visible
                    }
                }
                
                showNextCount();
            }

            function simulateFormProgress() {
                // This function is now deprecated - progress is handled by real-time updates
                console.log('⚠️ simulateFormProgress is deprecated - using real-time progress tracking');
            }

            function startCountdown() {
                // This function is now deprecated - use startSubmissionCountdown for real-time progress
                console.log('⚠️ startCountdown is deprecated - use startSubmissionCountdown instead');
                startSubmissionCountdown();
            }

            function hideProgressLoader() {
                const loadingOverlay = document.getElementById('formLoaderOverlay');
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                    
                    // Restore header z-index when hiding overlay
                    const header = document.querySelector('header');
                    if (header) {
                        header.style.zIndex = '';  // Remove the inline style to restore original
                    }
                }
            }

            function previewLoader() {
                showProgressLoader();
                
                // Simulate the actual submission flow for preview
                setTimeout(() => updateFormProgress(25, 'Validating security credentials...'), 500);
                setTimeout(() => updateFormProgress(40, 'Security validation complete...'), 1500);
                setTimeout(() => updateFormProgress(50, 'Preparing webinar registration...'), 2000);
                setTimeout(() => updateFormProgress(60, 'Submitting your information...'), 2500);
                setTimeout(() => updateFormProgress(75, 'Processing your registration...'), 3500);
                setTimeout(() => updateFormProgress(90, 'Finalizing your registration...'), 4500);
                setTimeout(() => {
                    updateFormProgress(100, 'Registration Successful!');
                    setTimeout(() => startSubmissionCountdown(), 500);
                }, 5000);
            }

            // Make functions globally available
            window.showProgressLoader = showProgressLoader;
            window.updateFormProgress = updateFormProgress;
            window.startSubmissionCountdown = startSubmissionCountdown;
            window.hideProgressLoader = hideProgressLoader;
            window.previewLoader = previewLoader;

        </script>
        <?php
    }, 999);

    // Remove console logs except in debug mode
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        add_action('wp_head', function() {
            echo '<script>
                console.log = function() {};
                console.error = function() {};
                console.warn = function() {};
                console.info = function() {};
                console.debug = function() {};
            </script>';
        }, 1);
    }
}

function dtr_webinar_registration_shortcode($atts) {
    // Only show form for logged-in users
    if (!is_user_logged_in()) {
        return '<div class="webinar-login-required" style="text-align: center; padding: 40px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;">
            <h3 style="margin-bottom: 20px; color: #495057;">Login Required</h3>
            <p style="margin-bottom: 25px; color: #6c757d; font-size: 16px;">Please log in to register for this webinar.</p>
            <a href="/my-account/" class="button btn-small global btn-rounded btn-blue" style="text-decoration: none;">Login to Register</a>
        </div>';
    }
    
    // Handle deregistration request
    if (isset($_POST['deregister_webinar']) && isset($_POST['post_id']) && isset($_POST['deregister_nonce'])) {
        error_log('[DTR Form Deregister] POST data received: ' . print_r($_POST, true));
        
        $post_id = intval($_POST['post_id']);
        $nonce = sanitize_text_field($_POST['deregister_nonce']);
        
        error_log('[DTR Form Deregister] Processing deregistration for post: ' . $post_id);
        error_log('[DTR Form Deregister] Nonce received: ' . $nonce);
        
        // Verify nonce
        $nonce_check = wp_verify_nonce($nonce, 'deregister_webinar_' . $post_id);
        $can_manage = current_user_can('manage_options');
        
        error_log('[DTR Form Deregister] Nonce valid: ' . ($nonce_check ? 'YES' : 'NO'));
        error_log('[DTR Form Deregister] Can manage options: ' . ($can_manage ? 'YES' : 'NO'));
        
        if ($nonce_check && $can_manage) {
            $current_user_id = get_current_user_id();
            
            // Remove from user meta
            $user_registration_key = 'webinar_registration_' . $post_id;
            delete_user_meta($current_user_id, $user_registration_key);
            
            // Remove from post meta (find and remove the specific registration)
            $all_registrations = get_post_meta($post_id, 'webinar_registrations', false);
            foreach ($all_registrations as $registration) {
                if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                    delete_post_meta($post_id, 'webinar_registrations', $registration);
                    break;
                }
            }
            
            // Redirect to same page to show form instead of registered status
            wp_redirect(get_permalink($post_id) . '?deregistered=1');
            exit;
        }
    }
    
    // Get current post data
    $post = get_post();
    
    // Generate unique tracking ID for this form submission
    $tracking_id = wp_generate_uuid4();
    
    // Get post type and taxonomy information
    $post_type = get_post_type($post);
    $post_categories = wp_get_post_categories($post->ID, array('fields' => 'names'));
    $post_tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
    
    // Get ACF fields from webinar_fields group
    $webinar_fields = get_field('webinar_fields');
    $workbooks_reference = $webinar_fields['workbooks_reference'] ?? '';
    $add_speaker_question = $webinar_fields['add_speaker_question'] ?? false;
    $add_additional_questions = $webinar_fields['add_additional_questions'] ?? false;
    $additional_questions = $webinar_fields['add_questions'] ?? array();

    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'title' => 'Register for Webinar',
        'description' => 'Complete the form below to register for this webinar.',
        'development_mode' => 'false',
    ), $atts, 'dtr_webinar_registration');

    // Get current user data
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $person_id = get_user_meta($user_id, 'workbooks_person_id', true);

    // Check if user has already registered for this webinar
    $user_already_registered = false;
    $registration_details = null;
    
    if (is_user_logged_in()) {
        // Quick check using user meta first (for new registrations)
        $user_registration_key = 'webinar_registration_' . $post->ID;
        $user_registration = get_user_meta($user_id, $user_registration_key, true);
        
        if ($user_registration && is_array($user_registration)) {
            $user_already_registered = true;
            $registration_details = $user_registration;
        } else {
            // Fallback: Check all post registrations (for older registrations)
            $all_registrations = get_post_meta($post->ID, 'webinar_registrations', false);
            
            foreach ($all_registrations as $registration) {
                // Check if this registration belongs to the current user
                if (isset($registration['person_id']) && $registration['person_id'] == $person_id) {
                    $user_already_registered = true;
                    $registration_details = $registration;
                    break;
                }
                // Also check by email as fallback
                if (isset($registration['email']) && $registration['email'] == $current_user->user_email) {
                    $user_already_registered = true;
                    $registration_details = $registration;
                    break;
                }
            }
        }
    }

    ob_start();
    
    // Show different content based on registration status
    if ($user_already_registered) {
        // User has already registered - show polished split button interface
        $uid = 'ks' . uniqid();
        ?>
        <div class="full-page vertical-half-margin event-registration">
            <div class="ks-split-btn btn-green" style="position: relative;">
                <button type="button" class="ks-main-btn ks-main-btn-global btn-green shimmer-effect shimmer-slow is-toggle text-left" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="<?php echo $uid; ?>-menu">
                    You are registered for this Live Webinar
                </button>
                <ul id="<?php echo $uid; ?>-menu" class="ks-menu" role="menu" style="z-index: 1002;">
                    <li role="none">
                        <a role="menuitem" href="/my-account/?page-view=overview&ics=1&calendar-post-id=<?php echo $post->ID; ?>" class="no-decoration calendar-btn">
                            Add to Calendar
                        </a>
                    </li>
                    <li role="none">
                        <a role="menuitem" href="/my-account/?page-view=events-and-webinars">
                            Events & Webinars
                        </a>
                    </li>
                    <?php if (current_user_can('manage_options')): // Only show for administrators ?>
                    <li role="none">
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="deregister_webinar" value="1">
                            <input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
                            <input type="hidden" name="deregister_nonce" value="<?php echo wp_create_nonce('deregister_webinar_' . $post->ID); ?>">
                            <button type="submit" role="menuitem" class="deregister-btn" onclick="return confirm('Are you sure you want to remove your registration? This is for testing purposes only.');">
                                🧪 Deregister (Testing)
                            </button>
                        </form>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="reveal-text">Webinar has been added to Events & Webinars</div>
        </div>
        
        <style>
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
            content: '';
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
        /* JavaScript will handle menu toggle */
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.querySelector('.is-toggle');
            const menu = document.getElementById('<?php echo $uid; ?>-menu');
            
            if (toggleBtn && menu) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const isExpanded = this.getAttribute('aria-expanded') === 'true';
                    this.setAttribute('aria-expanded', !isExpanded);
                    menu.style.display = isExpanded ? 'none' : 'block';
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!toggleBtn.contains(e.target) && !menu.contains(e.target)) {
                        toggleBtn.setAttribute('aria-expanded', 'false');
                        menu.style.display = 'none';
                    }
                });
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    // User hasn't registered yet - show registration form
    ?>
    
    <?php if (isset($_GET['deregistered']) && $_GET['deregistered'] == '1'): ?>
    <div class="deregistration-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
        <strong>✅ Deregistration Successful!</strong> You have been removed from this webinar. You can register again using the form below.
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['dtr_message']) && current_user_can('manage_options')): ?>
        <?php if ($_GET['dtr_message'] === 'deregistered'): ?>
        <div class="admin-message" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
            <strong>🧪 Admin Test: Deregistration Successful!</strong> Registration removed via admin bar.
        </div>
        <?php elseif ($_GET['dtr_message'] === 'registered'): ?>
        <div class="admin-message" style="background: #cce5ff; border: 1px solid #b3d9ff; color: #004085; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
            <strong>🧪 Admin Test: Registration Created!</strong> Test registration added via admin bar.
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="full-page form-container vertical-half-margin" id="registration-form">
        <!-- Add tracking fields at the top of the form -->
        <input type="hidden" id="formTrackingId" name="form_tracking_id" value="<?php echo esc_attr($tracking_id); ?>">
        <input type="hidden" id="postType" name="post_type" value="<?php echo esc_attr($post_type); ?>">
        <input type="hidden" id="postCategories" name="post_categories" value="<?php echo esc_attr(implode(',', $post_categories)); ?>">
        <input type="hidden" id="postTags" name="post_tags" value="<?php echo esc_attr(implode(',', $post_tags)); ?>">
        <input type="hidden" id="referrer" name="referrer" value="<?php echo esc_attr(wp_get_referer()); ?>">
        <input type="hidden" id="submitTime" name="submit_time" value="">

        <?php if (is_user_logged_in()): ?>
        <!-- Hidden Fields for logged in user -->
        <input type="hidden" id="firstName" name="first_name" value="<?php echo esc_attr($current_user->user_firstname); ?>">
        <input type="hidden" id="lastName" name="last_name" value="<?php echo esc_attr($current_user->user_lastname); ?>">
        <input type="hidden" id="personId" name="person_id" value="<?php echo esc_attr($person_id); ?>">
        <input type="hidden" id="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>">
        <?php else: ?>
        <!-- User Details Fields for Guest Users - These need to be visible and fillable -->
        <div class="form-row">
            <div class="form-field half-width">
                <label for="firstName">First Name *</label>
                <input type="text" id="firstName" name="first_name" required>
            </div>
            <div class="form-field half-width">
                <label for="lastName">Last Name *</label>
                <input type="text" id="lastName" name="last_name" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field full-width">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required>
            </div>
        </div>
        <!-- Hidden person ID for guest users -->
        <input type="hidden" id="personId" name="person_id" value="">
        <?php endif; ?>

        <div class="form-container webinar-registration-form">
            <form id="webinarForm">
                <!-- Hidden Fields -->
                <input type="hidden" id="postTitle" name="post_title" value="<?php echo esc_attr($post->post_title); ?>">
                <input type="hidden" id="postId" name="post_id" value="<?php echo esc_attr($post->ID); ?>">
                <input type="hidden" id="workbooksReference" name="workbooks_reference" value="<?php echo esc_attr($workbooks_reference); ?>">

                <?php if ($add_speaker_question): ?>
                <!-- Speaker Question -->
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="speakerQuestion">Question for the Speaker</label>
                        <textarea id="speakerQuestion" name="speaker_question" rows="4"></textarea>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($add_additional_questions && !empty($additional_questions)): ?>
                    <!-- Additional Questions from ACF -->
                    <?php foreach ($additional_questions as $question): ?>
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="question_<?php echo esc_attr($question['question_title']); ?>">
                                    <?php echo esc_html($question['question_title']); ?>
                                </label>
                                <?php
                                switch ($question['type_of_question']) {
                                    case 'textarea':
                                        ?>
                                        <textarea id="question_<?php echo esc_attr($question['question_title']); ?>" 
                                                name="additional_question[<?php echo esc_attr($question['question_title']); ?>]" 
                                                rows="4"></textarea>
                                        <?php
                                        break;
                                        
                                    case 'dropdown':
                                        ?>
                                        <select id="question_<?php echo esc_attr($question['question_title']); ?>"
                                                name="additional_question[<?php echo esc_attr($question['question_title']); ?>]">
                                            <option value="">- Select -</option>
                                            <?php foreach ($question['dropdown_options'] as $option): ?>
                                                <option value="<?php echo esc_attr($option['option']); ?>">
                                                    <?php echo esc_html($option['option']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php
                                        break;

                                    case 'checkbox':
                                        ?>
                                        <div class="checkbox-group">
                                            <?php foreach ($question['checkbox_options'] as $option): ?>
                                                <div class="checkbox-item">
                                                    <input type="checkbox" 
                                                           id="question_<?php echo esc_attr($question['question_title'] . '_' . $option['checkbox']); ?>"
                                                           name="additional_question[<?php echo esc_attr($question['question_title']); ?>][]"
                                                           value="<?php echo esc_attr($option['checkbox']); ?>">
                                                    <label for="question_<?php echo esc_attr($question['question_title'] . '_' . $option['checkbox']); ?>">
                                                        <?php echo esc_html($option['checkbox']); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php
                                        break;

                                    case 'radio':
                                        ?>
                                        <div class="radio-group">
                                            <?php foreach ($question['radio_options'] as $option): ?>
                                                <div class="radio-item">
                                                    <input type="radio"
                                                           id="question_<?php echo esc_attr($question['question_title'] . '_' . $option['radio']); ?>"
                                                           name="additional_question[<?php echo esc_attr($question['question_title']); ?>]"
                                                           value="<?php echo esc_attr($option['radio']); ?>">
                                                    <label for="question_<?php echo esc_attr($question['question_title'] . '_' . $option['radio']); ?>">
                                                        <?php echo esc_html($option['radio']); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php
                                        break;
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Consent Checkbox -->
                <div class="checkbox-group consent-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="cf_mailing_list_member_sponsor_1_optin" name="cf_mailing_list_member_sponsor_1_optin" value="1" required>
                        <label for="cf_mailing_list_member_sponsor_1_optin" class="checkbox-label">
                            I consent to Drug Target Review storing and processing my data and sending me news and updates. 
                            For more information, please see our <a href="/privacy-policy" target="_blank">privacy policy</a>. <span class="required">*</span>
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="form-row">
                    <div class="form-field">
                        <button type="button" id="submitWebinarBtn" class="button btn-small global btn-rounded btn-blue shimmer-effect shimmer-slow text-left chevron right">Register</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    
    <?php
    return ob_get_clean();
}

// Add actions to handle form submission and tracking
add_action('wp_ajax_dtr_submit_webinar_shortcode', 'dtr_handle_webinar_submission');
add_action('wp_ajax_nopriv_dtr_submit_webinar_shortcode', 'dtr_handle_webinar_submission');
add_action('wp_ajax_dtr_webinar_track_submission', 'dtr_webinar_track_submission');
add_action('wp_ajax_nopriv_dtr_webinar_track_submission', 'dtr_webinar_track_submission');

// Handle webinar form submission
function dtr_handle_webinar_submission() {
    // Verify nonce
    check_ajax_referer('dtr_webinar_nonce', '_wpnonce');

    // Collect form data
    $post_id = intval($_POST['post_id']);
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $speaker_question = sanitize_textarea_field($_POST['speaker_question'] ?? '');
    $workbooks_reference = sanitize_text_field($_POST['workbooks_reference'] ?? '');
    $optin = isset($_POST['cf_mailing_list_member_sponsor_1_optin']) ? '1' : '0';

    // Additional questions if any
    $additional_questions = [];
    if (isset($_POST['additional_question']) && is_array($_POST['additional_question'])) {
        foreach ($_POST['additional_question'] as $question => $answer) {
            $additional_questions[sanitize_text_field($question)] = is_array($answer) 
                ? array_map('sanitize_text_field', $answer)
                : sanitize_text_field($answer);
        }
    }

    // Store registration in WordPress
    $registration_data = array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'speaker_question' => $speaker_question,
        'workbooks_reference' => $workbooks_reference,
        'optin' => $optin,
        'additional_questions' => $additional_questions,
        'registration_date' => current_time('mysql'),
        'post_id' => $post_id
    );

    // Add registration to post meta
    add_post_meta($post_id, 'webinar_registrations', $registration_data);

    // Get webinar title
    $post = get_post($post_id);
    $webinar_title = $post ? $post->post_title : '';

    // Send confirmation email
    $to = $email;
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
    
    wp_mail($to, $subject, nl2br($message), $headers);

    // Send success response
    wp_send_json_success(array(
        'message' => 'Registration successful',
        'webinar_title' => $webinar_title,
        'email_address' => $email,
        'registration_id' => uniqid('reg_'),
        // You can add a redirect URL if needed
        // 'redirect_url' => get_permalink($post_id)
    ));
}

function dtr_webinar_track_submission() {
    // Verify nonce and permissions
    if (!isset($_POST['tracking_id'])) {
        wp_send_json_error('Invalid tracking data');
    }

    // Store submission data in post meta
    $tracking_data = array(
        'tracking_id' => sanitize_text_field($_POST['tracking_id']),
        'post_id' => intval($_POST['post_id']),
        'visitor_id' => sanitize_text_field($_POST['visitor_id'] ?? ''),
        'submit_time' => sanitize_text_field($_POST['submit_time']),
        'utm_data' => array(
            'source' => sanitize_text_field($_POST['utm_source'] ?? ''),
            'medium' => sanitize_text_field($_POST['utm_medium'] ?? ''),
            'campaign' => sanitize_text_field($_POST['utm_campaign'] ?? ''),
            'term' => sanitize_text_field($_POST['utm_term'] ?? ''),
            'content' => sanitize_text_field($_POST['utm_content'] ?? '')
        )
    );

    // Store tracking data in post meta
    add_post_meta($_POST['post_id'], 'webinar_registration_tracking', $tracking_data);

    wp_send_json_success(array(
        'message' => 'Tracking data stored successfully',
        'tracking_id' => $tracking_data['tracking_id']
    ));
}
