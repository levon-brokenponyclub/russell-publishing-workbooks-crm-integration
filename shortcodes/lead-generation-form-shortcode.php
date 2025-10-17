<?php
/* --------------------------------------------------------------------------
 * Workbooks Lead Generation Registration Shortcode
 * 
 * DEBUGGING: File modified at 2025-09-26 - If changes don't appear, there may be caching
 *
 * Shortcode: [workbooks-lead-generation-registration]  
 * Comprehensive lead generation system with multiple user states and content gating.
 * Pure HTML form implementation with HeroUI-inspired design and save-to-collection functionality.
 *
 * SYSTEM STATES:
 * 
 * UNRESTRICTED CONTENT (restrict_post = false):
 * 1. NOT LOGGED IN:
 *    - Shows login/register split button (matching webinar design)
 *    - Uses gated-content.php template pattern for content control
 * 
 * 2. LOGGED IN:
 *    - Shows unrestricted content (no form needed)
 *    - Sidebar form is hidden completely
 *    - Uses main-content.php template for content control
 * 
 * RESTRICTED CONTENT (restrict_post = true):
 * 1. NOT LOGGED IN:
 *    - Shows preview content with post feature image
 *    - Login/Register split button (matching webinar design)
 *    - Uses gated-content.php template pattern
 *    - No form access until authenticated
 *
 * 2. LOGGED IN - NOT REGISTERED:
 *    - Shows extended preview content
 *    - HTML registration form with ACF-driven dynamic questions
 *    - Optin checkbox for mailing list
 *    - Submit button with HeroUI circular progress loader
 *    - Uses gated-content-logged-in.php template pattern
 *
 * 3. LOGGED IN - REGISTERED (Form Completed):
 *    - Shows full unrestricted content
 *    - Save to Collection button functionality
 *    - Content collection management
 *    - No registration form displayed
 *
 * 4. SAVED TO COLLECTION:
 *    - "Saved to Collection" button with split action
 *    - Remove | View Collection button options
 *    - Collection state persistence
 *
 * Usage Examples:
 * [workbooks-lead-generation-registration] - Standard sidebar form
 * [workbooks-lead-generation-registration control_content="true"] - Content gating control
 *
 * Related Files:
 * 
 * Templates:
 * - components/single-content/gated-content.php - Not logged in state
 * - components/single-content/gated-content-logged-in.php - Logged in states  
 * - single-publications.php - Integration point with shortcode calls
 *
 * Form Handlers:
 * - lead-generation-form-shortcode.php (this file) - Main shortcode logic and form rendering
 * - form-handler-lead-generation-registration.php - Core Workbooks CRM integration
 * - form-submission-processors-ninjaform-hooks.php - Legacy dispatcher (lead gen disabled)
 * 
 * Assets:
 * CSS:
 * - assets/css/dynamic-forms.css - Base form styling framework
 * - assets/css/lead-generation-form-shortcode.css - Lead generation specific styles with HeroUI loader
 * 
 * JavaScript:
 * - assets/js/frontend.js - Global form handling and overlay management
 * - assets/js/lead-generation-form.js - Lead generation form validation and AJAX
 *
 * Debugging:
 * - logs/lead-generation-registration-debug.log - Structured debug logs with emoji indicators
 * 
 * ACF Field Structure (restricted_content_fields group):
 * - restrict_post (true/false) - Enable content restriction
 * - restricted_content_fields (group):
 *   - preview (textarea) - Preview content for non-registered users
 *   - workbooks_reference (text) - CRM integration ID
 *   - campaign_reference (text) - Campaign tracking ID
 *   - form_type (ninja_forms_field) - Legacy form selection
 *   - logged_in_user_-_before_form_submission (textarea) - Pre-registration content
 *   - after_form_submission (true/false) - Enable redirect after submission
 *   - redirect_url (url) - Post-submission redirect (conditional)
 *   - add_additional_questions (true/false) - Enable dynamic questions
 *   - add_questions (repeater) - Custom question builder:
 *     - type_of_question (select): textarea, dropdown, checkbox, radio
 *     - question_title (text) - Question label
 *     - textarea_options (repeater) - For textarea questions
 *     - dropdown_options (repeater) - For dropdown questions  
 *     - checkbox_options (repeater) - For checkbox questions
 *     - radio_options (repeater) - For radio questions
 *
 * Key Features:
 * - Multi-state content gating system
 * - ACF-powered dynamic form generation
 * - HeroUI-inspired circular progress loader
 * - Save-to-collection functionality with persistence
 * - Server-side user data extraction (no user info form fields)
 * - Workbooks CRM integration with lead tracking
 * - Registration status management
 * - Collection state management
 * - Email confirmation system
 * - Comprehensive debug logging
 * - Mobile-responsive design
 *
 * AJAX Handlers:
 * - dtr_handle_lead_generation_submission - Main form submission for lead registration
 * - dtr_handle_save_to_collection - Save/remove collection state management
 *
 * Integration Points:
 * - Post Types: articles, publications, events, podcasts, whitepapers, videos
 * - User Management: WordPress user system integration
 * - CRM: Workbooks API integration for lead management
 * - Collections: User content collection system
 *
 * Dependencies:
 * - WordPress (5.0+)
 * - Advanced Custom Fields Pro (ACF)
 * - DTR Workbooks CRM Integration plugin
 * - jQuery (WordPress core)
 *
 * Note: This system replaces the legacy Ninja Forms implementation with a pure HTML
 * form approach for better performance and tighter integration with the content gating system.
 * Collection functionality is a new feature unique to lead generation forms.
 * -------------------------------------------------------------------------- */

if (!defined('ABSPATH')) exit;

// Add shortcode for lead generation registration form
add_shortcode('workbooks-lead-generation-registration', 'dtr_lead_generation_registration_shortcode');
error_log('[DTR Lead Gen] Shortcode workbooks-lead-generation-registration registered successfully');

// Add admin menu for testing lead generation registrations
add_action('admin_menu', 'dtr_add_lead_gen_testing_menu');

// Add quick deregister button to admin bar for testing
add_action('admin_bar_menu', 'dtr_add_admin_bar_lead_gen_deregister_button', 100);

function dtr_add_lead_gen_testing_menu() {
    add_submenu_page(
        'edit.php?post_type=publications',
        'Test Lead Gen Registration',
        'Test Registration',
        'manage_options',
        'lead-gen-test-registration',
        'dtr_lead_gen_test_registration_page'
    );
}

function dtr_lead_gen_test_registration_page() {
    // Handle registration removal
    if (isset($_POST['remove_registration']) && isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        $current_user_id = get_current_user_id();
        
        // Remove from user meta
        $user_registration_key = 'lead_generation_registration_' . $post_id;
        delete_user_meta($current_user_id, $user_registration_key);
        
        // Remove from post meta (find and remove the specific registration)
        $all_registrations = get_post_meta($post_id, 'lead_generation_registrations', false);
        foreach ($all_registrations as $key => $registration) {
            if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                delete_post_meta($post_id, 'lead_generation_registrations', $registration);
                break;
            }
        }
        
        echo '<div class="notice notice-success"><p>Lead generation registration removed successfully!</p></div>';
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
            'lead_id' => '',
            'lead_question' => 'Test question from admin panel',
            'optin' => true,
            'registration_date' => current_time('mysql'),
            'post_id' => $post_id,
            'user_agent' => 'Admin Test Registration',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
        
        // Add to post meta
        add_post_meta($post_id, 'lead_generation_registrations', $registration_data);
        
        // Add to user meta for quick lookup
        $user_registration_key = 'lead_generation_registration_' . $post_id;
        update_user_meta($current_user_id, $user_registration_key, array(
            'registration_id' => $registration_id,
            'post_id' => $post_id,
            'registration_date' => current_time('mysql'),
            'email' => $current_user->user_email
        ));
        
        echo '<div class="notice notice-success"><p>Test lead generation registration created successfully!</p></div>';
    }
    
    // Handle save to collection test
    if (isset($_POST['test_save_collection']) && isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        $current_user_id = get_current_user_id();
        
        // Add to user's collection
        $user_collections = get_user_meta($current_user_id, 'saved_collection', true);
        if (!is_array($user_collections)) {
            $user_collections = array();
        }
        
        if (!in_array($post_id, $user_collections)) {
            $user_collections[] = $post_id;
            update_user_meta($current_user_id, 'saved_collection', $user_collections);
            echo '<div class="notice notice-success"><p>Content added to collection successfully!</p></div>';
        } else {
            echo '<div class="notice notice-info"><p>Content is already in your collection.</p></div>';
        }
    }
    
    // Handle remove from collection test
    if (isset($_POST['test_remove_collection']) && isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        $current_user_id = get_current_user_id();
        
        // Remove from user's collection
        $user_collections = get_user_meta($current_user_id, 'saved_collection', true);
        if (is_array($user_collections)) {
            $key = array_search($post_id, $user_collections);
            if ($key !== false) {
                unset($user_collections[$key]);
                update_user_meta($current_user_id, 'saved_collection', array_values($user_collections));
                echo '<div class="notice notice-success"><p>Content removed from collection successfully!</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>Content was not in your collection.</p></div>';
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Lead Generation Registration Testing</h1>
        <p>Use this page to test lead generation registration functionality and collection management.</p>
        
        <?php
        // Get all publication posts with restricted content
        $publications = get_posts(array(
            'post_type' => array('publications', 'articles', 'whitepapers'),
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'restrict_post',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        if (empty($publications)) {
            echo '<p>No restricted content posts found. Please create some publications with restricted content first.</p>';
            return;
        }
        
        $current_user_id = get_current_user_id();
        $user_collections = get_user_meta($current_user_id, 'saved_collection', true);
        if (!is_array($user_collections)) {
            $user_collections = array();
        }
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Publication Title</th>
                    <th>Registration Status</th>
                    <th>Collection Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($publications as $publication): ?>
                    <?php
                    // Check if current user is registered
                    $user_registration_key = 'lead_generation_registration_' . $publication->ID;
                    $user_registration = get_user_meta($current_user_id, $user_registration_key, true);
                    $is_registered = !empty($user_registration);
                    
                    // Check if in collection
                    $in_collection = in_array($publication->ID, $user_collections);
                    
                    // Get registration details if registered
                    $registration_details = null;
                    if ($is_registered) {
                        $all_registrations = get_post_meta($publication->ID, 'lead_generation_registrations', false);
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
                            <strong><?php echo esc_html($publication->post_title); ?></strong><br>
                            <small>ID: <?php echo $publication->ID; ?> | <a href="<?php echo get_permalink($publication->ID); ?>" target="_blank">View Post</a></small>
                        </td>
                        <td>
                            <?php if ($is_registered): ?>
                                <span style="color: green; font-weight: bold;">✓ Registered</span><br>
                                <?php if ($registration_details): ?>
                                    <small>Date: <?php echo esc_html($registration_details['registration_date'] ?? 'Unknown'); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #666;">Not Registered</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($in_collection): ?>
                                <span style="color: blue; font-weight: bold;">📚 In Collection</span>
                            <?php else: ?>
                                <span style="color: #666;">Not Saved</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$is_registered): ?>
                                <form method="post" style="display: inline-block; margin-right: 10px;">
                                    <input type="hidden" name="test_register" value="1">
                                    <input type="hidden" name="post_id" value="<?php echo $publication->ID; ?>">
                                    <button type="submit" class="button button-primary button-small">Test Register</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display: inline-block; margin-right: 10px;">
                                    <input type="hidden" name="remove_registration" value="1">
                                    <input type="hidden" name="post_id" value="<?php echo $publication->ID; ?>">
                                    <button type="submit" class="button button-secondary button-small" onclick="return confirm('Remove registration?');">Remove Registration</button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if (!$in_collection): ?>
                                <form method="post" style="display: inline-block; margin-right: 10px;">
                                    <input type="hidden" name="test_save_collection" value="1">
                                    <input type="hidden" name="post_id" value="<?php echo $publication->ID; ?>">
                                    <button type="submit" class="button button-primary button-small">Add to Collection</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display: inline-block; margin-right: 10px;">
                                    <input type="hidden" name="test_remove_collection" value="1">
                                    <input type="hidden" name="post_id" value="<?php echo $publication->ID; ?>">
                                    <button type="submit" class="button button-secondary button-small" onclick="return confirm('Remove from collection?');">Remove from Collection</button>
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

function dtr_add_admin_bar_lead_gen_deregister_button($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $post;
    if (!$post || !in_array($post->post_type, ['publications', 'articles', 'whitepapers'])) {
        return;
    }
    
    // Check if content is restricted
    $restrict_post = get_field('restrict_post', $post->ID);
    if (!$restrict_post) {
        return;
    }
    
    // Check if user is registered
    $current_user_id = get_current_user_id();
    $user_registration_key = 'lead_generation_registration_' . $post->ID;
    $user_registration = get_user_meta($current_user_id, $user_registration_key, true);
    
    if ($user_registration) {
        $wp_admin_bar->add_node(array(
            'id' => 'dtr-lead-gen-deregister',
            'title' => '🧪 Deregister Lead Gen (Testing)',
            'href' => '#',
            'meta' => array(
                'class' => 'dtr-lead-gen-deregister-link'
            )
        ));
        
        // Add JavaScript for deregistration
        add_action('wp_footer', function() use ($post) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.dtr-lead-gen-deregister-link').click(function(e) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to remove your lead generation registration? This is for testing purposes only.')) {
                        // Create a form and submit it
                        var form = $('<form method="post">')
                            .append('<input type="hidden" name="deregister_lead_gen" value="1">')
                            .append('<input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">')
                            .append('<input type="hidden" name="deregister_nonce" value="<?php echo wp_create_nonce('deregister_lead_gen_' . $post->ID); ?>">');
                        $('body').append(form);
                        form.submit();
                    }
                });
            });
            </script>
            <?php
        });
    }
}

/**
 * Main Lead Generation Registration Shortcode Function
 * 
 * Handles multiple states:
 * 1. Not logged in - Preview with login/register buttons
 * 2. Logged in, not registered - Show form with ACF questions
 * 3. Logged in, registered - Show full content with save-to-collection button
 * 4. Saved to collection - Show collection management buttons
 */
function dtr_lead_generation_registration_shortcode($atts) {
    try {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'control_content' => 'false', // When true, controls content visibility
            'title' => 'Access This Content',
            'description' => 'Complete the form below to access this content.',
        ), $atts, 'workbooks-lead-generation-registration');
    
    $control_content = filter_var($atts['control_content'], FILTER_VALIDATE_BOOLEAN);
    
    // Get current post data
    $post = get_post();
    if (!$post) {
        return '';
    }
    
    // Get current user data
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $is_logged_in = is_user_logged_in();
    
    // Check if this post has restricted content enabled
    $restrict_post = get_field('restrict_post', $post->ID);
    if (!$restrict_post) {
        // Content is NOT restricted
        if (!$is_logged_in) {
            // User not logged in: Show main content when controlling content, login sidebar when not
            if ($control_content) {
                // When controlling content, show the actual main content from ACF (like webinars)
                ob_start();
                
                // Check for ACF main content first, then fallback to WordPress content
                $acf_main_content = get_field('main_content', $post->ID);
                $acf_stand_first = get_field('stand_first', $post->ID);
                $wp_content = get_the_content();
                
                // Display Stand First if available
                if (!empty(trim($acf_stand_first))) {
                    echo '<div class="standfirst">';
                    echo $acf_stand_first;
                    echo '</div>';
                }
                
                // Display Main Content if available
                if (!empty(trim($acf_main_content))) {
                    echo $acf_main_content;
                } elseif (!empty(trim($wp_content))) {
                    // Fallback to WordPress content
                    the_content();
                } else {
                    // Show placeholder if no content found
                    echo '<div class="content-placeholder">';
                    echo '<h2>Content Coming Soon</h2>';
                    echo '<p>This content is being prepared. Please check back soon.</p>';
                    echo '</div>';
                }
                
                return ob_get_clean();
            } else {
                // Sidebar: Show login/register prompt (unchanged)
                $uid = 'leadgen' . uniqid();
                return '
                <div class="full-page vertical-half-margin event-registration" style="margin-top:0;">
                    <div class="ks-split-btn" style="position: relative;">
                        <button type="button" class="ks-main-btn ks-main-btn-global btn-blue shimmer-effect shimmer-slow is-toggle text-left" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . $uid . '-menu">Login or Register Now</button>
                        <ul id="' . $uid . '-menu" class="ks-menu" role="menu" style="z-index: 1002;">
                            <li role="none"><a role="menuitem" href="#" class="login-button dark-blue">Login</a></li>
                            <li role="none"><a role="menuitem" href="/free-membership">Become a Member</a></li>
                        </ul>
                    </div>
                    <div class="reveal-text">Login or Register for this content</div>
                </div>';
            }
        } else {
            // User is logged in AND content is not restricted: Hide form completely
            if ($control_content) {
                // Show the normal content since it's not restricted
                ob_start();
                
                // Check for ACF main content first, then fallback to WordPress content
                $acf_main_content = get_field('main_content', $post->ID);
                $acf_stand_first = get_field('stand_first', $post->ID);
                $wp_content = get_the_content();
                
                // Display Stand First if available
                if (!empty(trim($acf_stand_first))) {
                    echo '<div class="standfirst">';
                    echo $acf_stand_first;
                    echo '</div>';
                }
                
                // Display Main Content if available
                if (!empty(trim($acf_main_content))) {
                    echo $acf_main_content;
                } elseif (!empty(trim($wp_content))) {
                    // Fallback to WordPress content
                    the_content();
                } else {
                    // Show placeholder if no content found
                    echo '<div class="content-placeholder">';
                    echo '<h2>Content Coming Soon</h2>';
                    echo '<p>This content is being prepared. Please check back soon.</p>';
                    echo '</div>';
                }
                
                return ob_get_clean();
            } else {
                // Sidebar: Show save to collection functionality for logged-in users on unrestricted content
                // Check collection status
                $user_collections = get_user_meta($user_id, 'saved_collection', true);
                if (!is_array($user_collections)) {
                    $user_collections = array();
                }
                $in_collection = in_array($post->ID, $user_collections);
                
                // Render collection management interface
                $uid = 'ks' . uniqid();
                
                ob_start();
                ?>
                <div class="full-page vertical-half-margin lead-gen-registration" style="margin-top:0;">
                    <?php if (!$in_collection): ?>
                        <!-- Save to Collection Button -->
                        <div class="ks-split-btn btn-blue" style="position: relative;">
                            <button type="button" class="ks-main-btn ks-main-btn-global btn-blue shimmer-effect shimmer-slow save-to-collection" 
                                    data-post-id="<?php echo $post->ID; ?>" role="button">
                                Save to Collection
                            </button>
                        </div>
                        <div class="reveal-text">Save this content for later reading</div>
                    <?php else: ?>
                        <!-- Saved to Collection - Split Button -->
                        <div class="ks-split-btn btn-green" style="position: relative;">
                            <button type="button" class="ks-main-btn ks-main-btn-global btn-green shimmer-effect shimmer-slow is-toggle text-left" 
                                    role="button" aria-haspopup="true" aria-expanded="false" aria-controls="<?php echo $uid; ?>-menu">
                                Saved to Collection
                            </button>
                            <ul id="<?php echo $uid; ?>-menu" class="ks-menu ks-open" role="menu" style="z-index: 1002;">
                                <li role="none">
                                    <a role="menuitem" href="/my-account/?page-view=my-collection" class="no-decoration">
                                        View Collection
                                    </a>
                                </li>
                                <li role="none">
                                    <button type="button" role="menuitem" class="remove-from-collection-btn" 
                                            data-post-id="<?php echo $post->ID; ?>">
                                        Remove
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="reveal-text">Content saved in your collection</div>
                    <?php endif; ?>
                </div>
                <?php
                return ob_get_clean();
            }
        }
    }
    
    // Get restricted content fields
    $restricted_fields = get_field('restricted_content_fields', $post->ID);
    if (!$restricted_fields) {
        // If no restricted fields defined, show normal content when controlling content
        if ($control_content) {
            ob_start();
            get_template_part('components/global/main-content');
            return ob_get_clean();
        }
        return '';
    }
    
    // Check if user has completed the lead generation registration (using our new HTML form system)
    $user_registered = false;
    $user_collections = array();
    $in_collection = false;
    
    if ($is_logged_in) {
        // Debug: Check registration status using our new lead generation meta keys
        $user_registration_key = 'lead_generation_registration_' . $post->ID;
        $user_registration = get_user_meta($user_id, $user_registration_key, true);
        
        // DEBUG: Log registration status check
        error_log("LEAD GEN DEBUG: Post ID {$post->ID}, User ID {$user_id}");
        error_log("LEAD GEN DEBUG: User registration key: {$user_registration_key}");
        error_log("LEAD GEN DEBUG: User registration data: " . print_r($user_registration, true));
        
        if ($user_registration && is_array($user_registration)) {
            $user_registered = true;
            error_log("LEAD GEN DEBUG: User registered via user_meta");
        } else {
            // Fallback: Check all post registrations (for older registrations)
            $person_id = get_user_meta($user_id, 'workbooks_person_id', true);
            $all_registrations = get_post_meta($post->ID, 'lead_generation_registrations', false);
            
            error_log("LEAD GEN DEBUG: Person ID: {$person_id}");
            error_log("LEAD GEN DEBUG: All registrations: " . print_r($all_registrations, true));
            
            foreach ($all_registrations as $registration) {
                // Check if this registration belongs to the current user
                if (isset($registration['person_id']) && $registration['person_id'] == $person_id) {
                    $user_registered = true;
                    $registration_details = $registration;
                    error_log("LEAD GEN DEBUG: User registered via post_meta");
                    break;
                }
                // Also check by email as fallback
                if (isset($registration['email']) && $registration['email'] == $current_user->user_email) {
                    $user_registered = true;
                    $registration_details = $registration;
                    break;
                }
            }
        }
        
        // Check collection status
        $user_collections = get_user_meta($user_id, 'saved_collection', true);
        if (!is_array($user_collections)) {
            $user_collections = array();
        }
        $in_collection = in_array($post->ID, $user_collections);
    }
    
    // Handle deregistration request (admin only) - POST method
    if (isset($_POST['deregister_lead_gen']) && isset($_POST['post_id']) && isset($_POST['deregister_nonce'])) {
        $post_id = intval($_POST['post_id']);
        $nonce = sanitize_text_field($_POST['deregister_nonce']);
        
        // Verify nonce and admin permissions
        if (wp_verify_nonce($nonce, 'deregister_lead_gen_' . $post_id) && current_user_can('manage_options')) {
            $current_user_id = get_current_user_id();
            
            // Remove from user meta
            $user_registration_key = 'lead_generation_registration_' . $post_id;
            delete_user_meta($current_user_id, $user_registration_key);
            
            // Remove from post meta
            $all_registrations = get_post_meta($post_id, 'lead_generation_registrations', false);
            foreach ($all_registrations as $registration) {
                if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                    delete_post_meta($post_id, 'lead_generation_registrations', $registration);
                    break;
                }
            }
            
            // Redirect to same page to show form instead of registered status
            wp_redirect(get_permalink($post_id) . '?deregistered=1');
            exit;
        }
    }
    
    // Handle deregistration request (admin only) - GET method from admin bar
    if (isset($_GET['clear_form_completion']) && isset($_GET['post_id']) && current_user_can('manage_options')) {
        $post_id = intval($_GET['post_id']);
        $current_user_id = get_current_user_id();
        
        error_log("LEAD GEN DEREGISTER: Attempting to deregister User ID {$current_user_id} from Post ID {$post_id}");
        
        // Remove from user meta
        $user_registration_key = 'lead_generation_registration_' . $post_id;
        $deleted_user_meta = delete_user_meta($current_user_id, $user_registration_key);
        error_log("LEAD GEN DEREGISTER: User meta deleted: " . ($deleted_user_meta ? 'YES' : 'NO'));
        
        // Remove from post meta
        $all_registrations = get_post_meta($post_id, 'lead_generation_registrations', false);
        error_log("LEAD GEN DEREGISTER: Found " . count($all_registrations) . " registrations in post meta");
        
        foreach ($all_registrations as $registration) {
            if (isset($registration['user_id']) && $registration['user_id'] == $current_user_id) {
                $deleted_post_meta = delete_post_meta($post_id, 'lead_generation_registrations', $registration);
                error_log("LEAD GEN DEREGISTER: Post meta deleted: " . ($deleted_post_meta ? 'YES' : 'NO'));
                break;
            }
        }
        
        // Force refresh the page to show form
        wp_redirect(get_permalink($post_id) . '?deregistered=1');
        exit;
    }
    
    // Determine which template to render based on user state
    if (!$is_logged_in) {
        // STATE 1: Not logged in - Show preview with login/register buttons
        return dtr_render_not_logged_in_state($post, $restricted_fields, $control_content);
    } elseif (!$user_registered) {
        // STATE 2: Logged in but not registered - Show form
        return dtr_render_logged_in_not_registered_state($post, $restricted_fields, $atts, $control_content);
    } else {
        // STATE 3 & 4: Logged in and registered - Show content with collection management
        return dtr_render_logged_in_registered_state($post, $restricted_fields, $in_collection, $control_content);
    }
    
    } catch (Exception $e) {
        error_log('[DTR Lead Gen] Exception in shortcode: ' . $e->getMessage());
        return '<!-- DTR Lead Gen Error: ' . esc_html($e->getMessage()) . ' -->';
    } catch (Error $e) {
        error_log('[DTR Lead Gen] Fatal error in shortcode: ' . $e->getMessage());
        return '<!-- DTR Lead Gen Fatal Error: ' . esc_html($e->getMessage()) . ' -->';
    }
}

/**
 * STATE 1: Not logged in - Show preview content with login/register buttons
 */
function dtr_render_not_logged_in_state($post, $restricted_fields, $control_content) {
    $preview_content = $restricted_fields['preview'] ?? '';
    $post_feature_image = get_field('post_feature_image', $post->ID);
    
    if ($control_content) {
        // State 1: Not logged in - show gated-content.php template
        ob_start();
        get_template_part('components/single-content/gated-content');
        return ob_get_clean();
    } else {
        // This is a sidebar - show registration prompt with unique ID
        $uid = 'leadgen' . uniqid();
        return '
        <div class="full-page vertical-half-margin event-registration" style="margin-top:0;">
            <!-- split button -->
            <div class="ks-split-btn" style="position: relative;">
                <button type="button" class="ks-main-btn ks-main-btn-global btn-blue shimmer-effect shimmer-slow is-toggle text-left" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . $uid . '-menu">Login or Register Now</button>

                <ul id="' . $uid . '-menu" class="ks-menu" role="menu" style="z-index: 1002;">
                    <li role="none"><a role="menuitem" href="#" class="login-button dark-blue">Login</a></li>
                    <li role="none"><a role="menuitem" href="/free-membership">Become a Member</a></li>
                </ul>
            </div>
            <div class="reveal-text">Login or Register for this event</div>
        </div>';
    }
}

/**
 * STATE 2: Logged in but not registered - Show form with ACF questions
 */
function dtr_render_logged_in_not_registered_state($post, $restricted_fields, $atts, $control_content) {
    if ($control_content) {
        // State 2: Logged in but not registered - show gated-content-logged-in.php template
        ob_start();
        get_template_part('components/single-content/gated-content-logged-in');
        return ob_get_clean();
    } else {
        // Sidebar form
        return dtr_render_lead_gen_sidebar_form($post, $atts);
    }
}

/**
 * STATE 3 & 4: Logged in and registered - Show content with collection management
 */
function dtr_render_logged_in_registered_state($post, $restricted_fields, $in_collection, $control_content) {
    if ($control_content) {
        // State 3: Registered - show main-content.php template
        ob_start();
        get_template_part('components/global/main-content');
        return ob_get_clean();
    } else {
        // Show collection management in sidebar
        $uid = 'ks' . uniqid();
        
        ob_start();
        ?>
        <div class="full-page vertical-half-margin lead-gen-registration" style="margin-top:0;">
            <?php if (!$in_collection): ?>
                <!-- Save to Collection Button -->
                <div class="ks-split-btn btn-blue" style="position: relative;">
                    <button type="button" class="ks-main-btn ks-main-btn-global btn-blue shimmer-effect shimmer-slow save-to-collection" 
                            data-post-id="<?php echo $post->ID; ?>" role="button">
                        Save to Collection
                    </button>
                </div>
                <div class="reveal-text">Save this content for later reading</div>
            <?php else: ?>
                <!-- Saved to Collection - Split Button -->
                <div class="ks-split-btn btn-green" style="position: relative;">
                    <button type="button" class="ks-main-btn ks-main-btn-global btn-green shimmer-effect shimmer-slow is-toggle text-left" 
                            role="button" aria-haspopup="true" aria-expanded="false" aria-controls="<?php echo $uid; ?>-menu">
                        Saved to Collection
                    </button>
                    <ul id="<?php echo $uid; ?>-menu" class="ks-menu ks-open" role="menu" style="z-index: 1002;">
                        <li role="none">
                            <a role="menuitem" href="/my-account/?page-view=my-collection" class="no-decoration">
                                View Collection
                            </a>
                        </li>
                        <li role="none">
                            <button type="button" role="menuitem" class="remove-from-collection-btn" 
                                    data-post-id="<?php echo $post->ID; ?>">
                                Remove
                            </button>
                        </li>
                        <?php if (current_user_can('manage_options')): ?>
                        <li role="none">
                            <form method="post" style="margin: 0;">
                                <input type="hidden" name="deregister_lead_gen" value="1">
                                <input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
                                <input type="hidden" name="deregister_nonce" value="<?php echo wp_create_nonce('deregister_lead_gen_' . $post->ID); ?>">
                                <button type="submit" role="menuitem" class="deregister-btn" onclick="return confirm('Are you sure you want to remove your registration? This is for testing purposes only.');">
                                    🧪 Deregister (Testing)
                                </button>
                            </form>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="reveal-text">Content saved in your collection</div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Render the HTML form for lead generation registration
 */
function dtr_render_lead_gen_html_form($post, $restricted_fields) {
    $current_user = wp_get_current_user();
    $workbooks_reference = $restricted_fields['workbooks_reference'] ?? '';
    $add_additional_questions = $restricted_fields['add_additional_questions'] ?? false;
    $additional_questions = $restricted_fields['add_questions'] ?? array();
    
    // Generate unique form ID
    $form_id = 'lead-gen-form-' . $post->ID;
    $tracking_id = wp_generate_uuid4();
    
    ob_start();
    ?>
    
    <!-- Lead Generation Registration Form Container -->
    <div class="gated-content-form-container" id="<?php echo $form_id; ?>-container">
        <div class="lead-gen-form-wrapper">
      
            <button class="ks-main-btn-global btn-blue shimmer-effect shimmer-slow not-registered text-left" onclick="document.querySelector('.gated-lead-form-content').scrollIntoView({behavior: 'smooth'});" disabled="">Unlock Premium Content</button>
            <!-- <div class="reveal-text">to unlock this premium content.</div> -->
            <form id="<?php echo $form_id; ?>" class="lead-gen-registration-form gated-lead-form-content" data-post-id="<?php echo $post->ID; ?>" data-tracking-id="<?php echo $tracking_id; ?>">
                
                <!-- Hidden Fields -->
                <input type="hidden" name="action" value="dtr_handle_lead_generation_submission">
                <input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
                <input type="hidden" name="workbooks_reference" value="<?php echo esc_attr($workbooks_reference); ?>">
                <input type="hidden" name="tracking_id" value="<?php echo $tracking_id; ?>">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('lead_generation_registration_' . $post->ID); ?>">
                
                <!-- User Info (Auto-populated, hidden) -->
                <input type="hidden" name="first_name" value="<?php echo esc_attr($current_user->user_firstname ?: $current_user->display_name); ?>">
                <input type="hidden" name="last_name" value="<?php echo esc_attr($current_user->user_lastname); ?>">
                <input type="hidden" name="email" value="<?php echo esc_attr($current_user->user_email); ?>">
                <input type="hidden" name="person_id" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'workbooks_person_id', true)); ?>">
                
                <!-- Dynamic Questions from ACF -->
                <?php if ($add_additional_questions && !empty($additional_questions)): ?>
                    <div class="form-questions-section">
                        <?php foreach ($additional_questions as $index => $question): ?>
                            <?php
                            $question_type = $question['type_of_question'] ?? 'textarea';
                            $question_title = $question['question_title'] ?? 'Question';
                            $field_name = 'additional_question_' . $index;
                            ?>
                            
                            <div class="form-field">
                                <label for="<?php echo $field_name; ?>"><?php echo esc_html($question_title); ?></label>
                                
                                <?php if ($question_type === 'textarea'): ?>
                                    <textarea name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" rows="4" placeholder="Enter your response..."></textarea>
                                
                                <?php elseif ($question_type === 'dropdown'): ?>
                                    <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>">
                                        <option value="">Please select...</option>
                                        <?php
                                        $dropdown_options = $question['dropdown_options'] ?? array();
                                        foreach ($dropdown_options as $option):
                                        ?>
                                            <option value="<?php echo esc_attr($option['option']); ?>"><?php echo esc_html($option['option']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                
                                <?php elseif ($question_type === 'checkbox'): ?>
                                    <div class="checkbox-options">
                                        <?php
                                        $checkbox_options = $question['checkbox_options'] ?? array();
                                        foreach ($checkbox_options as $cb_index => $option):
                                        ?>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="<?php echo $field_name; ?>[]" value="<?php echo esc_attr($option['checkbox']); ?>">
                                                <?php echo esc_html($option['checkbox']); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                
                                <?php elseif ($question_type === 'radio'): ?>
                                    <div class="radio-options">
                                        <?php
                                        $radio_options = $question['radio_options'] ?? array();
                                        foreach ($radio_options as $radio_index => $option):
                                        ?>
                                            <label class="radio-label">
                                                <input type="radio" name="<?php echo $field_name; ?>" value="<?php echo esc_attr($option['radio']); ?>">
                                                <?php echo esc_html($option['radio']); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Consent Checkbox -->
                <div class="checkbox-group consent-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="cf_mailing_list_member_sponsor_1_optin" name="cf_mailing_list_member_sponsor_1_optin" value="1" required="">
                        <label for="cf_mailing_list_member_sponsor_1_optin" class="checkbox-label">
                            By completing this form you are agreeing to our terms and conditions and privacy policy. As part of your content download, and in compliance with GDPR, we will share your data with the specific sponsor(s)/partner(s) of this content who may wish to contact you. You may opt-out at any time by using the unsubscribe link in one of our, or our sponsor's, marketing emails, or by contacting<span class="required">*</span>
                        </label>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="form-submit-section">
                    <button type="submit" class="lead-gen-submit-btn button global btn-small btn-rounded btn-blue shimmer-effect shimmer-slow is-toggle text-left chevron right full-width" style="margin:5px 0 10px 0">
                        Register
                    </button>
                </div>
                
            </form>
        </div>
    </div>
    
    <!-- Loader will be created dynamically and appended to body -->
    
    <?php
    return ob_get_clean();
}

/**
 * Render sidebar form for non-content-controlling shortcodes
 */
function dtr_render_lead_gen_sidebar_form($post, $atts) {
    // Only show form for logged-in users (exactly like webinar shortcode)
    if (!is_user_logged_in()) {
        $uid = 'sidebar' . uniqid();
        return '
        <div class="full-page vertical-half-margin event-registration">
            <!-- split button -->
            <div class="ks-split-btn" style="position: relative;">
                <button type="button" class="ks-main-btn ks-main-btn-global btn-blue shimmer-effect shimmer-slow is-toggle text-left" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . $uid . '-menu">Login or Register Now</button>

                <ul id="' . $uid . '-menu" class="ks-menu ks-open" role="menu" style="z-index: 1002;">
                    <li role="none"><a role="menuitem" href="#" class="login-button dark-blue">Login</a></li>
                    <li role="none"><a role="menuitem" href="/free-membership">Become a Member</a></li>
                </ul>
            </div>
            <div class="reveal-text">Login or Register for this event</div>
        </div>';
    }
    
    // For logged-in users, show HTML registration form in sidebar
    $restricted_fields = get_field('restricted_content_fields', $post->ID);
    ob_start();
    ?>
    <div class="lead-gen-sidebar-form">
        
        <!-- HTML Registration Form for Sidebar -->
        <?php echo dtr_render_lead_gen_html_form($post, $restricted_fields); ?>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX Handlers for Lead Generation Forms
add_action('wp_ajax_dtr_handle_lead_generation_submission', 'dtr_handle_lead_generation_submission_ajax');
add_action('wp_ajax_nopriv_dtr_handle_lead_generation_submission', 'dtr_handle_lead_generation_submission_ajax_nopriv');

add_action('wp_ajax_dtr_handle_save_to_collection', 'dtr_handle_save_to_collection_ajax');
add_action('wp_ajax_dtr_handle_remove_from_collection', 'dtr_handle_remove_from_collection_ajax');
add_action('wp_ajax_dtr_load_login_modal', 'dtr_load_login_modal_ajax');
add_action('wp_ajax_nopriv_dtr_load_login_modal', 'dtr_load_login_modal_ajax');

/**
 * AJAX Handler for Lead Generation Form Submission (Logged-in users only)
 */
function dtr_handle_lead_generation_submission_ajax() {
    error_log('[DTR Lead Gen AJAX] Form submission started');
    
    // Verify user is logged in
    if (!is_user_logged_in()) {
        error_log('[DTR Lead Gen AJAX] User not logged in');
        wp_send_json_error(array(
            'message' => 'You must be logged in to register for content.',
            'redirect' => wp_login_url(get_permalink())
        ));
        return;
    }
    
    // Verify nonce
    $nonce = sanitize_text_field($_POST['nonce'] ?? '');
    $post_id = intval($_POST['post_id'] ?? 0);
    
    if (!wp_verify_nonce($nonce, 'lead_generation_registration_' . $post_id)) {
        error_log('[DTR Lead Gen AJAX] Nonce verification failed');
        wp_send_json_error(array('message' => 'Security verification failed. Please refresh the page and try again.'));
        return;
    }
    
    // Sanitize and validate form data
    $form_data = array(
        'post_id' => $post_id,
        'workbooks_reference' => sanitize_text_field($_POST['workbooks_reference'] ?? ''),
        'tracking_id' => sanitize_text_field($_POST['tracking_id'] ?? ''),
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'person_id' => sanitize_text_field($_POST['person_id'] ?? ''),
        'cf_mailing_list_member_sponsor_1_optin' => isset($_POST['cf_mailing_list_member_sponsor_1_optin']) ? 1 : 0,
        'additional_questions' => array()
    );
    
    // Collect additional questions
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'additional_question_') === 0) {
            if (is_array($value)) {
                $form_data['additional_questions'][$key] = array_map('sanitize_text_field', $value);
            } else {
                $form_data['additional_questions'][$key] = sanitize_textarea_field($value);
            }
        }
    }
    
    // Build lead question from additional questions
    $lead_question_parts = array();
    foreach ($form_data['additional_questions'] as $key => $value) {
        if (is_array($value)) {
            $lead_question_parts[] = $key . ': ' . implode(', ', $value);
        } else {
            $lead_question_parts[] = $key . ': ' . $value;
        }
    }
    $lead_question = implode("\n", $lead_question_parts);
    
    error_log('[DTR Lead Gen AJAX] Form data collected: ' . print_r($form_data, true));
    
    // Prepare data for the handler
    $lead_data = array(
        'post_id' => $form_data['post_id'],
        'email' => $form_data['email'],
        'first_name' => $form_data['first_name'],
        'last_name' => $form_data['last_name'],
        'person_id' => $form_data['person_id'],
        'lead_question' => $lead_question,
        'cf_mailing_list_member_sponsor_1_optin' => $form_data['cf_mailing_list_member_sponsor_1_optin']
    );
    
    // Include handler file if it exists
    $handler_file = plugin_dir_path(__FILE__) . '../includes/form-handler-lead-generation-registration.php';
    if (file_exists($handler_file)) {
        include_once $handler_file;
    }
    
    // Call the main handler function
    if (function_exists('dtr_handle_lead_generation_registration')) {
        $result = dtr_handle_lead_generation_registration($lead_data);
        
        if ($result && isset($result['success']) && $result['success']) {
            // Save registration to user meta for quick access
            $current_user_id = get_current_user_id();
            $user_registration_key = 'lead_generation_registration_' . $post_id;
            
            $registration_meta = array(
                'registration_id' => $result['registration_id'] ?? wp_generate_uuid4(),
                'post_id' => $post_id,
                'registration_date' => current_time('mysql'),
                'email' => $form_data['email'],
                'lead_id' => $result['lead_id'] ?? '',
                'person_id' => $form_data['person_id']
            );
            
            update_user_meta($current_user_id, $user_registration_key, $registration_meta);
            
            // Also save to post meta for admin visibility
            $registration_data = array_merge($registration_meta, array(
                'user_id' => $current_user_id,
                'first_name' => $form_data['first_name'],
                'last_name' => $form_data['last_name'],
                'workbooks_reference' => $form_data['workbooks_reference'],
                'lead_question' => $lead_question,
                'optin' => $form_data['cf_mailing_list_member_sponsor_1_optin'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'tracking_id' => $form_data['tracking_id']
            ));
            
            add_post_meta($post_id, 'lead_generation_registrations', $registration_data);
            
            error_log('[DTR Lead Gen AJAX] Registration successful');
            
            wp_send_json_success(array(
                'message' => 'Registration completed successfully!',
                'registration_id' => $registration_meta['registration_id'],
                'redirect' => false, // Stay on same page to show full content
                'reload_page' => true // Reload to show registered state
            ));
        } else {
            error_log('[DTR Lead Gen AJAX] Registration failed: ' . print_r($result, true));
            wp_send_json_error(array(
                'message' => 'Registration failed. Please try again.',
                'details' => $result['error'] ?? 'Unknown error'
            ));
        }
    } else {
        error_log('[DTR Lead Gen AJAX] Handler function not found');
        wp_send_json_error(array('message' => 'Registration handler not available. Please contact support.'));
    }
}

/**
 * AJAX Handler for non-logged-in users (redirect to login)
 */
function dtr_handle_lead_generation_submission_ajax_nopriv() {
    wp_send_json_error(array(
        'message' => 'You must be logged in to register for content.',
        'redirect' => wp_login_url()
    ));
}

/**
 * AJAX Handler for Save to Collection
 */
function dtr_handle_save_to_collection_ajax() {
    // Verify user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in to save content.'));
        return;
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    $current_user_id = get_current_user_id();
    
    if (!$post_id) {
        wp_send_json_error(array('message' => 'Invalid content ID.'));
        return;
    }
    
    // Get user's collection
    $user_collections = get_user_meta($current_user_id, 'saved_collection', true);
    if (!is_array($user_collections)) {
        $user_collections = array();
    }
    
    // Add to collection if not already there
    if (!in_array($post_id, $user_collections)) {
        $user_collections[] = $post_id;
        update_user_meta($current_user_id, 'saved_collection', $user_collections);
        
        error_log('[DTR Collection] Content ' . $post_id . ' added to collection for user ' . $current_user_id);
        
        wp_send_json_success(array(
            'message' => 'Content saved to your collection!',
            'in_collection' => true
        ));
    } else {
        wp_send_json_success(array(
            'message' => 'Content is already in your collection.',
            'in_collection' => true
        ));
    }
}

/**
 * AJAX Handler for Remove from Collection
 */
function dtr_handle_remove_from_collection_ajax() {
    // Verify user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in to manage your collection.'));
        return;
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    $current_user_id = get_current_user_id();
    
    if (!$post_id) {
        wp_send_json_error(array('message' => 'Invalid content ID.'));
        return;
    }
    
    // Get user's collection
    $user_collections = get_user_meta($current_user_id, 'saved_collection', true);
    if (!is_array($user_collections)) {
        $user_collections = array();
    }
    
    // Remove from collection
    $key = array_search($post_id, $user_collections);
    if ($key !== false) {
        unset($user_collections[$key]);
        update_user_meta($current_user_id, 'saved_collection', array_values($user_collections));
        
        error_log('[DTR Collection] Content ' . $post_id . ' removed from collection for user ' . $current_user_id);
        
        wp_send_json_success(array(
            'message' => 'Content removed from your collection.',
            'in_collection' => false
        ));
    } else {
        wp_send_json_success(array(
            'message' => 'Content was not in your collection.',
            'in_collection' => false
        ));
    }
}

/**
 * AJAX Handler for Loading Login Modal Dynamically
 */
function dtr_load_login_modal_ajax() {
    // Load the login modal template
    ob_start();
    
    // Check if the template part exists
    $template_path = get_template_directory() . '/components/membership/login-modal.php';
    if (file_exists($template_path)) {
        include $template_path;
        $modal_html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $modal_html,
            'message' => 'Login modal loaded successfully'
        ));
    } else {
        ob_end_clean();
        wp_send_json_error(array(
            'message' => 'Login modal template not found',
            'template_path' => $template_path
        ));
    }
}

// Enqueue JavaScript and CSS for lead generation forms
add_action('wp_enqueue_scripts', 'dtr_enqueue_lead_generation_assets');

function dtr_enqueue_lead_generation_assets() {
    // Only enqueue on pages that might have the shortcode
    global $post;
    if (!$post) return;
    
    // Check if this post has restricted content or if it's a post type that might use the shortcode
    $post_types = ['publications', 'articles', 'events', 'podcasts', 'whitepapers', 'videos'];
    if (!in_array($post->post_type, $post_types)) return;
    
    // Enqueue CSS
    wp_enqueue_style(
        'dtr-lead-generation-form-shortcode',
        plugin_dir_url(__FILE__) . '../assets/css/lead-generation-registration.css',
        array(),
        '1.0.0'
    );
    
    // Enqueue JavaScript
    wp_enqueue_script('jquery');
    
    // IMPORTANT: Enqueue frontend script for split button functionality
    wp_enqueue_script(
        'dtr-frontend',
        plugin_dir_url(__FILE__) . '../assets/js/frontend.js',
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . '../assets/js/frontend.js'),
        true
    );
    
    // Add inline JavaScript for lead generation functionality
    add_action('wp_footer', 'dtr_add_lead_generation_javascript');
}

function dtr_add_lead_generation_javascript() {
    global $post;
    if (!$post) return;
    
    // Check if login modal exists, if not, add it
    if (!is_user_logged_in()) {
        echo '<script>
        jQuery(document).ready(function($) {
            // Check if login modal exists after DOM is ready
            if (!document.getElementById("login-modal-container")) {
                console.log("[DTR Lead Gen] Login modal not found, loading it dynamically...");
                
                // Load modal via AJAX
                $.ajax({
                    url: "' . admin_url('admin-ajax.php') . '",
                    type: "POST",
                    data: {
                        action: "dtr_load_login_modal"
                    },
                    success: function(response) {
                        if (response.success && response.data.html) {
                            console.log("[DTR Lead Gen] Login modal loaded and appended to body");
                            $("body").append(response.data.html);
                        }
                    },
                    error: function() {
                        console.log("[DTR Lead Gen] Failed to load login modal via AJAX");
                    }
                });
            } else {
                console.log("[DTR Lead Gen] Login modal already exists in DOM");
            }
        });
        </script>';
    }
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('[DTR Lead Gen] JavaScript loaded');
        
        // Check for successful registration and show toast notification
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('registered') === '1') {
            console.log('[DTR Lead Gen] Registration success detected, showing toast');
            
            // Clean up URL without page reload
            const cleanUrl = window.location.href.split('?')[0];
            window.history.replaceState({}, document.title, cleanUrl);
            
            // Show toast notification after a brief delay
            setTimeout(() => {
                showContentUnlockedToast();
            }, 500);
        }
        
        // Create full-page overlay and append to body
        createProgressOverlay();
        
        // Create the progress overlay and append to body
        function createProgressOverlay() {
            // Remove existing overlay if it exists
            const existingOverlay = document.getElementById('formLoaderOverlay');
            if (existingOverlay) {
                existingOverlay.remove();
            }
            
            // Create overlay HTML
            const overlayHTML = `
                <div class="form-loader-overlay" id="formLoaderOverlay" style="display: none;">
                    <div class="progress-card">
                        <div class="progress-body">
                            <div class="circular-progress">
                                <svg class="progress-svg" viewBox="0 0 100 100">
                                    <circle class="progress-track" cx="50" cy="50" r="45" />
                                    <circle class="progress-indicator" cx="50" cy="50" r="45" id="progressCircle" />
                                </svg>
                                <div class="progress-value" id="progressValue">0%</div>
                            </div>
                        </div>
                        <div class="progress-footer">
                            <div class="progress-chip">
                                Registration processing...
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Append to body
            document.body.insertAdjacentHTML('beforeend', overlayHTML);
        }

        // Enhanced Progress Loader Functions
        function showProgressLoader() {
            let overlay = document.getElementById('formLoaderOverlay');
            
            // Create overlay if it doesn't exist
            if (!overlay) {
                createProgressOverlay();
                overlay = document.getElementById('formLoaderOverlay');
            }
            
            if (overlay) {
                // Force styles to ensure visibility
                overlay.style.display = 'flex';
                overlay.style.opacity = '1';
                overlay.style.visibility = 'visible';
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100vw';
                overlay.style.height = '100vh';
                overlay.style.zIndex = '999999';
                overlay.classList.add('show');
                overlay.classList.remove('fade-out');
                
                // Set header z-index to ensure overlay appears above it
                const header = document.querySelector('header');
                if (header) {
                    header.style.zIndex = '999';
                }
                
                // Reset progress
                const progressCircle = document.getElementById('progressCircle');
                const progressValue = document.getElementById('progressValue');
                if (progressCircle) {
                    progressCircle.style.strokeDashoffset = '283'; // 0%
                }
                if (progressValue) {
                    progressValue.textContent = '0%';
                }
            }
        }

        // Real-time progress updater that matches actual submission stages
        function updateFormProgress(percentage, message) {
            const progressValue = document.getElementById('progressValue');
            const progressCircle = document.getElementById('progressCircle');
            const progressChip = document.querySelector('.progress-chip');
            
            if (progressValue) {
                progressValue.textContent = percentage + '%';
            }
            
            if (progressCircle) {
                const circumference = 2 * Math.PI * 45;
                const offset = circumference - (percentage / 100) * circumference;
                progressCircle.style.strokeDashoffset = offset;
            }
            
            if (progressChip && message) {
                progressChip.textContent = message;
            }
        }

        function hideProgressLoader() {
            const overlay = document.getElementById('formLoaderOverlay');
            if (overlay) {
                overlay.classList.add('fade-out');
                overlay.classList.remove('show');
                setTimeout(() => {
                    overlay.style.display = 'none';
                    overlay.style.opacity = '0';
                    overlay.style.visibility = 'hidden';
                }, 500);
                
                // Restore header z-index when hiding overlay
                const header = document.querySelector('header');
                if (header) {
                    header.style.zIndex = '';
                }
            }
        }

        function slideOutLoader() {
            const overlay = document.getElementById('formLoaderOverlay');
            if (overlay) {
                overlay.classList.add('fade-out');
                overlay.classList.remove('show');
                
                // Hide completely after fade animation completes
                setTimeout(() => {
                    overlay.style.display = 'none';
                    overlay.classList.remove('show', 'fade-out');
                    
                    // Restore header z-index
                    const header = document.querySelector('header');
                    if (header) {
                        header.style.zIndex = '';
                    }
                }, 500);
            }
        }
        
        function previewLoader() {
            showProgressLoader();
            updateFormProgress(0, 'Starting...');
            
            setTimeout(() => updateFormProgress(25, 'Validating data...'), 500);
            setTimeout(() => updateFormProgress(50, 'Processing registration...'), 1500);
            setTimeout(() => updateFormProgress(75, 'Syncing with CRM...'), 2500);
            setTimeout(() => updateFormProgress(100, 'Complete!'), 3500);
            setTimeout(() => hideProgressLoader(), 5000);
        }

        // Function to show content unlocked toast notification
        function showContentUnlockedToast() {
            // Create toast HTML
            const toast = $(`
                <div class="content-unlocked-toast" style="
                    position: fixed;
                    bottom: 35px;
                    left: 35px;
                    background: #871f80;
                    color: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    z-index: 9999;
                    max-width: 350px;
                    opacity: 0;
                    transform: translateY(20px);
                    transition: all 0.3s ease;
                ">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div class="toast-icon" style="
                            flex-shrink: 0;
                            margin-top: 2px;
                        "></div>
                        <div>
                            <h4 style="
                                margin: 0 0 8px 0;
                                font-size: 16px;
                                font-weight: 600;
                                color: white;
                            ">Content Unlocked!</h4>
                            <p style="
                                margin: 0;
                                font-size: 14px;
                                line-height: 1.4;
                                color: #bdc3c7;
                            ">You have completed the required form and can now access this content.</p>
                        </div>
                    </div>
                </div>
            `);
            
            // Add to body
            $('body').append(toast);
            
            // Trigger animation after brief delay
            setTimeout(() => {
                toast.css({
                    opacity: 1,
                    transform: 'translateY(0)'
                });
            }, 50);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                toast.css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                });
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 5000);
        }

        // Function to refresh form content without full page reload
        function refreshFormContent(postId) {
            // Show a subtle loading state
            $('.lead-gen-form-wrapper').fadeOut(300, function() {
                // Replace with registered state content - Save to Collection button
                $(this).html(`
                    <div class="full-page vertical-half-margin lead-gen-registration">
                        <div class="ks-split-btn btn-purple" style="position: relative;">
                            <button type="button" class="ks-main-btn ks-main-btn-global btn-purple shimmer-effect shimmer-slow save-to-collection" 
                                    data-post-id="${postId}" role="button">
                                Save to Collection
                            </button>
                        </div>
                        <div class="reveal-text">Save this content for later reading</div>
                    </div>
                `).fadeIn(500);
                
                // Note: Save to collection functionality is handled by delegated event handler
            });
        }

        // Button click handler for submit button with is-toggle class
        $('.lead-gen-submit-btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Trigger form submission
            $(this).closest('form').trigger('submit');
        });

        // Form submission handler
        $('.lead-gen-registration-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var postId = form.data('post-id');
            var submitButton = form.find('.lead-gen-submit-btn');
            
            console.log('[DTR Lead Gen] Form submission started for post ' + postId);
            
            // Disable submit button and show progress overlay
            submitButton.prop('disabled', true);
            submitButton.text('Processing...');
            
            // Show progress loader
            showProgressLoader();
            
            // Trigger page anchor immediately with slight delay
            setTimeout(function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }, 200);
            
            // Stage 1: Initial validation
            setTimeout(() => updateFormProgress(25, 'Validating data...'), 500);
            
            // Stage 2: Security validation complete
            setTimeout(() => updateFormProgress(40, 'Processing registration...'), 1000);
            
            // Stage 3: Processing with CRM
            setTimeout(() => updateFormProgress(60, 'Syncing with CRM...'), 1500);
            
            // Submit form via AJAX
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: form.serialize(),
                timeout: 30000,
                success: function(response) {
                    console.log('[DTR Lead Gen] AJAX success:', response);
                    
                    if (response.success) {
                        // Stage 4: Finalizing access
                        updateFormProgress(90, 'Finalizing access...');
                        
                        setTimeout(() => {
                            // Stage 5: Complete
                            updateFormProgress(100, 'Complete!');
                            
                            setTimeout(() => {
                                if (response.data.redirect) {
                                    // Show wipe animation, then redirect
                                    slideOutLoader();
                                    setTimeout(() => {
                                        window.location.href = response.data.redirect;
                                    }, 1100);
                                } else {
                                    // Redirect sooner for smoother transition (before loader fully completes)
                                    setTimeout(() => {
                                        window.location.href = window.location.href.split('?')[0] + '?registered=1';
                                    }, 300);
                                }
                            }, 1000);
                        }, 500);
                    } else {
                        // Show error state
                        updateFormProgress(0, 'Error occurred...');
                        
                        setTimeout(() => {
                            slideOutLoader();
                            setTimeout(() => {
                                submitButton.prop('disabled', false);
                                submitButton.text('Register');
                                alert(response.data.message || 'Registration failed. Please try again.');
                            }, 500);
                        }, 2000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[DTR Lead Gen] AJAX error:', status, error);
                    
                    // Show error state
                    updateFormProgress(0, 'Connection error...');
                    
                    setTimeout(() => {
                        slideOutLoader();
                        setTimeout(() => {
                            submitButton.prop('disabled', false);
                            submitButton.text('Register');
                            alert('Connection error. Please check your connection and try again.');
                        }, 500);
                    }, 2000);
                }
            });
        });
        
        // Save to Collection handler (delegated event for dynamic content)
        $(document).on('click', '.save-to-collection', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var postId = button.data('post-id');
            var revealText = button.closest('.full-page').find('.reveal-text');
            
            console.log('[DTR Collection] Save to collection clicked!');
            console.log('[DTR Collection] Button element:', button);
            console.log('[DTR Collection] Post ID:', postId);
            console.log('[DTR Collection] Saving content ' + postId + ' to collection');
            
            // Debug: check if jQuery and AJAX are available
            if (typeof $ === 'undefined') {
                console.error('[DTR Collection] jQuery not loaded!');
                alert('jQuery not loaded - cannot save to collection');
                return;
            }
            
            // Add loading classes and change text
            button.addClass('btn-loading btn-green');
            button.prop('disabled', true);
            button.text('Saving....');
            
            console.log('[DTR Collection] Making AJAX request to:', '<?php echo admin_url('admin-ajax.php'); ?>');
            console.log('[DTR Collection] Request data:', {
                action: 'dtr_handle_save_to_collection',
                post_id: postId
            });
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'dtr_handle_save_to_collection',
                    post_id: postId
                },
                beforeSend: function() {
                    console.log('[DTR Collection] AJAX request starting...');
                },
                success: function(response) {
                    console.log('[DTR Collection] AJAX Success! Response:', response);
                    
                    if (response.success) {
                        // Replace with split button saved state
                        var container = button.closest('.full-page');
                        var uniqueId = 'ks' + Math.random().toString(36).substr(2, 9);
                        
                        container.html(`
                            <div class="ks-split-btn btn-green" style="position: relative;">
                                <button type="button" class="ks-main-btn ks-main-btn-global btn-green shimmer-effect shimmer-slow is-toggle text-left" 
                                        role="button" aria-haspopup="true" aria-expanded="false" aria-controls="${uniqueId}-menu">
                                    Saved to Collection
                                </button>
                                <ul id="${uniqueId}-menu" class="ks-menu" role="menu" style="z-index: 1002;">
                                    <li role="none">
                                        <a role="menuitem" href="/my-account/?page-view=my-collection" class="no-decoration">
                                            View Collection
                                        </a>
                                    </li>
                                    <li role="none">
                                        <button type="button" role="menuitem" class="remove-from-collection-btn" 
                                                data-post-id="${postId}">
                                            Remove
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div class="reveal-text">This has been saved to your collection</div>
                        `);
                        
                        // Show toast notification for content added to collection
                        showToast('Content added to Collection', 'success');
                    } else {
                        // Reset on error
                        button.removeClass('btn-loading btn-green');
                        button.prop('disabled', false);
                        button.text('Save to Collection');
                        alert(response.data.message || 'Failed to save content to collection.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[DTR Collection] AJAX Error!');
                    console.error('[DTR Collection] Status:', status);
                    console.error('[DTR Collection] Error:', error);
                    console.error('[DTR Collection] Response:', xhr.responseText);
                    
                    // Reset on error
                    button.removeClass('btn-loading btn-green');
                    button.prop('disabled', false);
                    button.text('Save to Collection');
                    alert('Connection error: ' + status + '. Please try again.');
                }
            });
        });
        
        // Remove from Collection handler (delegated event for dynamic content)
        $(document).on('click', '.remove-from-collection-btn', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var postId = button.data('post-id');
            
            // Show CodyHouse modal dialog
            showRemoveDialog(postId, button);
        });
        
        // Function to show CodyHouse remove dialog
        function showRemoveDialog(postId, button) {
            // Create modal HTML if it doesn't exist
            if (!$('#remove-collection-dialog').length) {
                var modalHtml = `
                    <div class="cd-dialog" id="remove-collection-dialog" role="dialog" aria-labelledby="dialog-title" aria-describedby="dialog-description">
                        <div class="cd-dialog__container">
                            <div class="cd-dialog__content">
                                <h2 id="dialog-title">Remove from Collection</h2>
                                <p id="dialog-description">Are you sure you want to remove this content from your collection?</p>
                                <div class="cd-dialog__buttons">
                                    <button class="cd-dialog__action cd-dialog__action--secondary" id="cancel-remove">Cancel</button>
                                    <button class="cd-dialog__action cd-dialog__action--primary" id="confirm-remove">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);
            }
            
            var dialog = $('#remove-collection-dialog');
            var confirmBtn = $('#confirm-remove');
            var cancelBtn = $('#cancel-remove');
            
            // Show dialog
            dialog.addClass('cd-dialog--visible');
            
            // Handle confirm remove
            confirmBtn.off('click').on('click', function() {
                // Hide dialog
                dialog.removeClass('cd-dialog--visible');
                
                console.log('[DTR Collection] Removing content ' + postId + ' from collection');
                
                // Find the main "Saved to Collection" button and change its text
                var mainButton = button.closest('.ks-split-btn').find('.ks-main-btn');
                var originalText = mainButton.text();
                
                // Disable button and show loading
                button.prop('disabled', true);
                button.text('Removing...');
                mainButton.prop('disabled', true);
                mainButton.text('Removing...');
                mainButton.addClass('btn-loading');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'dtr_handle_remove_from_collection',
                        post_id: postId
                    },
                    success: function(response) {
                        console.log('[DTR Collection] Remove response:', response);
                        
                        if (response.success) {
                            // Replace with save button
                            var container = button.closest('.full-page');
                            container.html(`
                                <div class="ks-split-btn btn-blue" style="position: relative;">
                                    <button type="button" class="ks-main-btn ks-main-btn-global btn-blue shimmer-effect shimmer-slow save-to-collection" 
                                            data-post-id="${postId}" role="button">
                                        Save to Collection
                                    </button>
                                </div>
                                <div class="reveal-text">Save this content for later reading</div>
                            `);
                            
                            // Note: Click handler will be automatically attached via delegated event
                            
                            // Show toast notification
                            showToast('Content removed from your collection', 'success');
                        } else {
                            alert(response.data.message || 'Failed to remove content from collection.');
                            // Restore original button states on error
                            button.prop('disabled', false);
                            button.text('Remove');
                            var mainButton = button.closest('.ks-split-btn').find('.ks-main-btn');
                            mainButton.prop('disabled', false);
                            mainButton.text('Saved to Collection');
                            mainButton.removeClass('btn-loading');
                        }
                    },
                    error: function() {
                        alert('Connection error. Please try again.');
                        // Restore original button states on error
                        button.prop('disabled', false);
                        button.text('Remove');
                        var mainButton = button.closest('.ks-split-btn').find('.ks-main-btn');
                        mainButton.prop('disabled', false);
                        mainButton.text('Saved to Collection');
                        mainButton.removeClass('btn-loading');
                    }
                });
            });
            
            // Handle cancel
            cancelBtn.off('click').on('click', function() {
                dialog.removeClass('cd-dialog--visible');
            });
            
            // Handle clicking outside dialog
            dialog.off('click').on('click', function(e) {
                if (e.target === this) {
                    dialog.removeClass('cd-dialog--visible');
                }
            });
            
            // Handle ESC key
            $(document).off('keydown.removeDialog').on('keydown.removeDialog', function(e) {
                if (e.keyCode === 27) { // ESC key
                    dialog.removeClass('cd-dialog--visible');
                    $(document).off('keydown.removeDialog');
                }
            });
        }
        
        // Function to show toast notifications
        function showToast(message, type) {
            type = type || 'success';
            
            var toastHtml = `
                <div class="cd-toast cd-toast--${type}" id="collection-toast">
                    <div class="cd-toast__icon">
                        <svg viewBox="0 0 16 16">
                            <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                        </svg>
                    </div>
                    <div class="cd-toast__content">
                        <p class="cd-toast__title">${message}</p>
                    </div>
                </div>
            `;
            
            // Remove existing toast
            $('.cd-toast').remove();
            
            // Add new toast
            $('body').append(toastHtml);
            var toast = $('#collection-toast');
            
            // Show toast
            setTimeout(function() {
                toast.addClass('cd-toast--visible');
            }, 10);
            
            // Auto hide after 3 seconds
            setTimeout(function() {
                toast.removeClass('cd-toast--visible');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 3000);
        }
        
        // Split button toggle functionality
        $(document).on('click', '.is-toggle', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var menuId = button.attr('aria-controls');
            var menu = $('#' + menuId);
            
            if (menu.is(':visible')) {
                menu.hide();
                button.attr('aria-expanded', 'false');
            } else {
                // Hide all other menus first
                $('.ks-menu').hide();
                $('.is-toggle').attr('aria-expanded', 'false');
                
                // Show this menu
                menu.show();
                button.attr('aria-expanded', 'true');
            }
        });
        
        // Close menus when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.ks-split-btn').length) {
                $('.ks-menu').hide();
                $('.is-toggle').attr('aria-expanded', 'false');
            }
        });
        
        // Prevent scroll anchoring on page load
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
        
        if (window.location.hash) {
            history.replaceState(null, null, window.location.pathname + window.location.search);
        }
        
        // Override any hash-based scrolling
        $(window).on('load', function() {
            setTimeout(function() {
                window.scrollTo(0, 0);
                document.documentElement.scrollTop = 0;
                document.body.scrollTop = 0;
            }, 100);
        });
        
        // Login button click handler (delegated event for dynamic content)
        $(document).on('click', '.login-button', function(e) {
            e.preventDefault();
            console.log('[DTR Lead Gen] Login button clicked');
            console.log('[DTR Lead Gen] User logged in status (PHP):', <?php echo is_user_logged_in() ? 'true' : 'false'; ?>);
            
            // First check if the login modal exists in DOM
            var loginModal = document.getElementById('login-modal-container');
            console.log('[DTR Lead Gen] Login modal element found:', !!loginModal);
            
            // Debug: Show all modal-related elements
            var allModals = document.querySelectorAll('[id*="modal"], [class*="modal"], [id*="login"], [class*="login"]');
            console.log('[DTR Lead Gen] All modal-related elements found:', Array.from(allModals).map(el => ({
                tag: el.tagName,
                id: el.id,
                classes: el.className,
                display: getComputedStyle(el).display
            })));
            
            if (loginModal) {
                // Check if openLoginModal function exists
                if (typeof window.openLoginModal === 'function') {
                    console.log('[DTR Lead Gen] openLoginModal function found, calling it');
                    window.openLoginModal();
                } else {
                    console.log('[DTR Lead Gen] openLoginModal function not found, showing modal directly');
                    // Direct modal show as fallback
                    loginModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            } else {
                console.log('[DTR Lead Gen] Login modal not found in DOM, checking for other modal selectors...');
                
                // Try alternative modal selectors with more options
                var altModal = document.querySelector('.modal-container') || 
                              document.querySelector('#login-modal') || 
                              document.querySelector('.login-modal') ||
                              document.querySelector('[class*="login"][class*="modal"]') ||
                              document.querySelector('[id*="login"][id*="modal"]');
                              
                if (altModal) {
                    console.log('[DTR Lead Gen] Alternative modal found:', altModal);
                    altModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                } else {
                    console.log('[DTR Lead Gen] No modal found anywhere, attempting to load modal dynamically...');
                    
                    // Try to dynamically load the login modal via AJAX
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'dtr_load_login_modal'
                        },
                        success: function(response) {
                            if (response.success && response.data.html) {
                                console.log('[DTR Lead Gen] Modal loaded dynamically');
                                $('body').append(response.data.html);
                                
                                // Try to open it
                                if (typeof window.openLoginModal === 'function') {
                                    window.openLoginModal();
                                } else {
                                    var dynamicModal = document.getElementById('login-modal-container');
                                    if (dynamicModal) {
                                        dynamicModal.style.display = 'flex';
                                        document.body.style.overflow = 'hidden';
                                    }
                                }
                            } else {
                                console.log('[DTR Lead Gen] Failed to load modal dynamically, redirecting to login page');
                                window.location.href = '<?php echo wp_login_url(get_permalink()); ?>';
                            }
                        },
                        error: function() {
                            console.log('[DTR Lead Gen] AJAX error loading modal, redirecting to login page');
                            window.location.href = '<?php echo wp_login_url(get_permalink()); ?>';
                        }
                    });
                }
            }
        });
        
        // Check if openLoginModal is available on page load
        console.log('[DTR Lead Gen] Checking for openLoginModal function:', typeof window.openLoginModal);
        if (typeof window.openLoginModal !== 'function') {
            console.log('[DTR Lead Gen] openLoginModal not available, available window functions:', Object.keys(window).filter(key => key.includes('Login') || key.includes('login') || key.includes('Modal') || key.includes('modal')));
        }
        
        console.log('[DTR Lead Gen] All event handlers bound successfully');
    });
    </script>
    
    <style>
    /* Overlay CSS Styles - Matching Webinar Form */
    .form-loader-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: linear-gradient(135deg, #871f80 0%, #4f074aff 100%) !important;
        display: none !important;
        justify-content: center !important;
        align-items: center !important;
        z-index: 999999 !important;
        backdrop-filter: blur(8px) !important;
        opacity: 0 !important;
        transition: opacity 0.5s ease-in !important;
        pointer-events: auto !important;
    }

    .form-loader-overlay.show {
        display: flex !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

    .form-loader-overlay.fade-out {
        opacity: 0 !important;
        transition: opacity 0.5s ease-out !important;
    }

    .progress-card {
        width: 320px;
        height: 320px;
        border-radius: 20px;
        border: none;
        display: flex;
        flex-direction: column;
        transform: scale(0.8);
        opacity: 0;
        transition: all 0.6s ease-out;
    }

    .form-loader-overlay.show .progress-card {
        transform: scale(1);
        opacity: 1;
    }

    .progress-body {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        padding-bottom: 0;
    }

    .circular-progress {
        position: relative;
        width: 144px;
        height: 144px;
    }

    .progress-svg {
        width: 144px;
        height: 144px;
        transform: rotate(-90deg);
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
    }

    .progress-track {
        fill: none;
        stroke: rgba(255, 255, 255, 0.1);
        stroke-width: 4;
    }

    .progress-indicator {
        fill: none;
        stroke: white;
        stroke-width: 4;
        stroke-linecap: round;
        stroke-dasharray: 283; /* 2π × 45 */
        stroke-dashoffset: 283;
        transition: stroke-dashoffset 0.5s ease;
    }

    .progress-value {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 24px;
        font-weight: 600;
        color: white;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .progress-footer {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 16px;
        padding-top: 0;
    }

    .progress-chip {
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        padding: 8px 16px;
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.9);
        font-size: 12px;
        font-weight: 600;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    
    .collection-success {
        background: #d4edda;
        color: #155724;
        padding: 10px 16px;
        border-radius: 4px;
        margin: 10px 0;
        border: 1px solid #c3e6cb;
        text-align: center;
        font-weight: 500;
    }
    
    .registration-success {
        background: #d4edda;
        color: #155724;
        padding: 30px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid #c3e6cb;
    }
    
    .registration-success h4 {
        margin: 0 0 10px 0;
        font-size: 1.3em;
    }
    
    .registration-success p {
        margin: 0;
        font-size: 0.95em;
    }
    
    .deregister-btn {
        background: none !important;
        border: none !important;
        color: #dc3545 !important;
        padding: 12px 16px !important;
        width: 100% !important;
        text-align: left !important;
        cursor: pointer !important;
        font-size: 14px !important;
    }
    
    .deregister-btn:hover {
        background: #f8f9fa !important;
    }
    
    /* Loading state for save to collection button */
    .btn-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.8;
    }
    
    .btn-loading::after {
        content: '';
        position: absolute;
        top: 50%;
        right: 10px;
        width: 16px;
        height: 16px;
        margin-top: -8px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* CodyHouse Modal Dialog Styles - Matching my-collection.php */
    .cd-dialog {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 999999;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
    }

    .cd-dialog--visible {
        display: flex !important;
        opacity: 1;
        visibility: visible;
    }

    .cd-dialog__container {
        background: white;
        border-radius: 8px;
        padding: 30px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        transform: scale(0.8);
        transition: transform 0.3s ease;
    }

    .cd-dialog--visible .cd-dialog__container {
        transform: scale(1);
    }

    .cd-dialog__content h2 {
        margin: 0 0 15px 0;
        font-size: 1.4em;
        color: #333;
    }

    .cd-dialog__content p {
        margin: 0 0 25px 0;
        color: #666;
        line-height: 1.5;
    }

    .cd-dialog__buttons {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
    }

    .cd-dialog__action {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: background-color 0.2s;
    }

    .cd-dialog__action--secondary {
        background: #f8f9fa;
        color: #666;
    }

    .cd-dialog__action--secondary:hover {
        background: #e9ecef;
    }

    .cd-dialog__action--primary {
        background: #dc3545;
        color: white;
    }

    .cd-dialog__action--primary:hover {
        background: #c82333;
    }

    /* Toast Notification Styles - Matching my-collection.php */
    .cd-toast {
        position: fixed;
        bottom: 35px;
        left: 35px;
        background: #28a745;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 9999;
        max-width: 350px;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .cd-toast--visible {
        opacity: 1;
        transform: translateY(0);
    }

    .cd-toast__icon {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
    }

    .cd-toast__icon svg {
        width: 100%;
        height: 100%;
        fill: currentColor;
    }

    .cd-toast__content {
        flex: 1;
    }

    .cd-toast__title {
        margin: 0;
        font-size: 14px;
        font-weight: 500;
    }
    
    </style>
    <?php
}