<?php
/**
 * Clean Ninja Forms webinar integration that works exactly like the successful webinar form
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug logging function
function dtr_simple_debug($message) {
    $debug_log_file = WP_CONTENT_DIR . '/plugins/dtr-workbooks-crm-integration/simple-webinar-debug.log';
    error_log("[" . date('Y-m-d H:i:s') . "] $message\n", 3, $debug_log_file);
}

// Hook into Ninja Forms submission with early priority to avoid conflicts
add_action('ninja_forms_submit_data', 'dtr_ninja_forms_webinar_hook', 5);

function dtr_ninja_forms_webinar_hook($form_data) {
    dtr_simple_debug("=== NINJA FORMS HOOK ===");
    
    // Handle different form types
    if (!isset($form_data['id'])) {
        dtr_simple_debug("No form ID found");
        return $form_data;
    }
    
    $form_id = $form_data['id'];
    dtr_simple_debug("Processing form ID: " . $form_id);
    
    // Route to appropriate handler
    if ($form_id === '2_1') {
        dtr_simple_debug("✅ Detected webinar form submission!");
        dtr_handle_webinar_form($form_data);
    } elseif ($form_id === '31') {
        dtr_simple_debug("✅ Detected lead generation form submission!");
        dtr_handle_lead_generation_form($form_data);
    } else {
        dtr_simple_debug("Form not handled. Form ID: " . $form_id);
    }
    
    return $form_data;
}

function dtr_handle_webinar_form($form_data) {
    
    // Extract form fields by their keys (like the working form)
    $webinar_title = '';
    $post_id = '';
    $speaker_question = '';
    $sponsor_optin = '';
    
    if (isset($form_data['fields']) && is_array($form_data['fields'])) {
        foreach ($form_data['fields'] as $field_id => $field_data) {
            if (isset($field_data['key'])) {
                $key = $field_data['key'];
                $value = isset($field_data['value']) ? $field_data['value'] : '';
                
                dtr_simple_debug("Field $key: $value");
                
                switch ($key) {
                    case 'webinar_title':
                        $webinar_title = $value;
                        break;
                    case 'post_id':
                        $post_id = $value;
                        break;
                    case 'speaker_question':
                        $speaker_question = $value;
                        break;
                    case 'sponsor_optin':
                        $sponsor_optin = $value;
                        break;
                }
            }
        }
    }
    
    // Get participant email exactly like the working form does
    $current_user = wp_get_current_user();
    if (!$current_user || !$current_user->user_email) {
        dtr_simple_debug("❌ ERROR: No current user or user email - webinar registration requires login");
        return;
    }
    
    $participant_email = $current_user->user_email;
    dtr_simple_debug("✅ Using current user email: $participant_email");
    
    // Validate required data
    if (empty($webinar_title) || empty($post_id) || empty($participant_email)) {
        dtr_simple_debug("❌ ERROR: Missing required data");
        dtr_simple_debug("  - Webinar Title: " . ($webinar_title ?: 'MISSING'));
        dtr_simple_debug("  - Post ID: " . ($post_id ?: 'MISSING'));
        dtr_simple_debug("  - Email: " . ($participant_email ?: 'MISSING'));
        return;
    }
    
    dtr_simple_debug("✅ All required data present");
    
    // Call the working webinar registration function directly
    dtr_call_workbooks_webinar_registration($post_id, $participant_email, $speaker_question, $sponsor_optin);
}

function dtr_call_workbooks_webinar_registration($post_id, $participant_email, $speaker_question, $sponsor_optin) {
    dtr_simple_debug("=== CALLING CORE WEBINAR REGISTRATION ===");
    
    // Check if the core registration function exists
    if (!function_exists('dtr_register_workbooks_webinar')) {
        dtr_simple_debug("❌ ERROR: Core webinar registration function not found");
        
        // Include the ajax-handlers file if it's not loaded
        $ajax_handlers_path = WP_CONTENT_DIR . '/plugins/dtr-workbooks-crm-integration/includes/ajax-handlers.php';
        if (file_exists($ajax_handlers_path)) {
            include_once $ajax_handlers_path;
            dtr_simple_debug("✅ Loaded ajax-handlers.php");
        } else {
            dtr_simple_debug("❌ ERROR: ajax-handlers.php not found");
            return;
        }
    }
    
    // Prepare data for the core registration function (no AJAX needed)
    $registration_data = array(
        'webinar_post_id' => $post_id,
        'participant_email' => $participant_email,
        'speaker_question' => $speaker_question,
        'privacy_consent' => $sponsor_optin
    );
    
    dtr_simple_debug("✅ Prepared registration data:");
    dtr_simple_debug("  - webinar_post_id: " . $registration_data['webinar_post_id']);
    dtr_simple_debug("  - participant_email: " . $registration_data['participant_email']);
    dtr_simple_debug("  - speaker_question: " . $registration_data['speaker_question']);
    dtr_simple_debug("  - privacy_consent: " . $registration_data['privacy_consent']);
    
    // Call the core registration function directly (bypasses AJAX)
    try {
        dtr_simple_debug("🚀 Calling dtr_register_workbooks_webinar()...");
        
        // Call the core registration function
        $result = dtr_register_workbooks_webinar($registration_data);
        
        dtr_simple_debug("✅ Core registration function completed");
        
        if ($result !== null) {
            dtr_simple_debug("✅ Registration result: " . print_r($result, true));
        } else {
            dtr_simple_debug("ℹ️  Registration completed without return value");
        }
        
        dtr_simple_debug("🎉 Webinar registration successful via Ninja Forms hook!");
        
    } catch (Exception $e) {
        dtr_simple_debug("❌ Exception during registration: " . $e->getMessage());
        dtr_simple_debug("Exception details: " . print_r($e, true));
    } catch (Error $e) {
        dtr_simple_debug("❌ Error during registration: " . $e->getMessage());
        dtr_simple_debug("Error details: " . print_r($e, true));
    }
}

function dtr_handle_lead_generation_form($form_data) {
    dtr_simple_debug("=== PROCESSING LEAD GENERATION FORM ===");
    
    // Extract form fields by their field IDs (based on HTML structure)
    $post_title = '';
    $post_id = '';
    $first_name = '';
    $last_name = '';
    $email_address = '';
    $acf_questions = '';
    $privacy_consent = '';
    
    if (isset($form_data['fields']) && is_array($form_data['fields'])) {
        foreach ($form_data['fields'] as $field_id => $field_data) {
            $value = isset($field_data['value']) ? $field_data['value'] : '';
            
            // Map field IDs to their purposes
            switch ($field_id) {
                case '374': // Post Title
                    $post_title = $value;
                    dtr_simple_debug("Post Title (374): $value");
                    break;
                case '375': // Post ID  
                    $post_id = $value;
                    dtr_simple_debug("Post ID (375): $value");
                    break;
                case '376': // First Name
                    $first_name = $value;
                    dtr_simple_debug("First Name (376): $value");
                    break;
                case '377': // Last Name
                    $last_name = $value;
                    dtr_simple_debug("Last Name (377): $value");
                    break;
                case '378': // Email Address
                    $email_address = $value;
                    dtr_simple_debug("Email Address (378): $value");
                    break;
                case '383': // ACF Questions
                    $acf_questions = $value;
                    dtr_simple_debug("ACF Questions (383): $value");
                    break;
                case '380': // Privacy Consent Checkbox
                    $privacy_consent = $value;
                    dtr_simple_debug("Privacy Consent (380): $value");
                    break;
                default:
                    dtr_simple_debug("Unknown field ID $field_id: $value");
                    break;
            }
        }
    }
    
    // Check for ACF question fields (dynamic fields from JavaScript)
    $acf_data = [];
    if (isset($_POST)) {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'acf_question_') === 0) {
                $acf_data[$key] = $value;
                dtr_simple_debug("ACF Question $key: $value");
            }
        }
    }
    
    // Validate required data
    if (empty($post_title) || empty($post_id) || empty($email_address) || empty($privacy_consent)) {
        dtr_simple_debug("❌ ERROR: Missing required data for lead generation");
        dtr_simple_debug("  - Post Title: " . ($post_title ?: 'MISSING'));
        dtr_simple_debug("  - Post ID: " . ($post_id ?: 'MISSING'));
        dtr_simple_debug("  - Email: " . ($email_address ?: 'MISSING'));
        dtr_simple_debug("  - Privacy Consent: " . ($privacy_consent ?: 'MISSING'));
        return;
    }
    
    dtr_simple_debug("✅ All required lead generation data present");
    
    // Call the lead generation registration function
    dtr_call_workbooks_lead_registration($post_id, $post_title, $first_name, $last_name, $email_address, $acf_data, $privacy_consent);
}

function dtr_call_workbooks_lead_registration($post_id, $post_title, $first_name, $last_name, $email_address, $acf_data, $privacy_consent) {
    dtr_simple_debug("=== CALLING WORKBOOKS LEAD REGISTRATION ===");
    
    // Get campaign reference from ACF fields (like webinar registration does)
    $campaign_reference = '';
    $campaign_id = '';
    
    // Try to get restricted content fields (for gated content)
    $restricted_content_fields = get_field('restricted_content_fields', $post_id);
    if ($restricted_content_fields && is_array($restricted_content_fields)) {
        if (isset($restricted_content_fields['campaign_reference'])) {
            $campaign_reference = $restricted_content_fields['campaign_reference'];
        }
        if (isset($restricted_content_fields['reference'])) {
            $campaign_id = $restricted_content_fields['reference'];
        }
        dtr_simple_debug("✅ Found restricted_content_fields ACF data");
        dtr_simple_debug("  - Campaign Reference: " . $campaign_reference);
        dtr_simple_debug("  - Campaign ID: " . $campaign_id);
    } else {
        // Fallback: try direct campaign_reference field
        $campaign_reference = get_field('campaign_reference', $post_id);
        dtr_simple_debug("📝 Using direct campaign_reference field: " . $campaign_reference);
    }
    
    // Prepare data for lead registration
    $registration_data = array(
        'post_id' => $post_id,
        'post_title' => $post_title,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email_address' => $email_address,
        'acf_questions' => $acf_data,
        'privacy_consent' => $privacy_consent,
        'campaign_reference' => $campaign_reference,
        'campaign_id' => $campaign_id,
        'registration_type' => 'lead_generation'
    );
    
    dtr_simple_debug("✅ Prepared lead registration data:");
    dtr_simple_debug("  - Post ID: " . $registration_data['post_id']);
    dtr_simple_debug("  - Post Title: " . $registration_data['post_title']);
    dtr_simple_debug("  - First Name: " . $registration_data['first_name']);
    dtr_simple_debug("  - Last Name: " . $registration_data['last_name']);
    dtr_simple_debug("  - Email: " . $registration_data['email_address']);
    dtr_simple_debug("  - Campaign Reference: " . $registration_data['campaign_reference']);
    dtr_simple_debug("  - Campaign ID: " . $registration_data['campaign_id']);
    dtr_simple_debug("  - ACF Questions: " . print_r($registration_data['acf_questions'], true));
    dtr_simple_debug("  - Privacy Consent: " . $registration_data['privacy_consent']);
    
    // Check if the core registration function exists
    if (!function_exists('dtr_register_workbooks_lead')) {
        dtr_simple_debug("❌ ERROR: Core lead registration function not found");
        
        // Include the ajax-handlers file if it's not loaded
        $ajax_handlers_path = WP_CONTENT_DIR . '/plugins/dtr-workbooks-crm-integration/includes/ajax-handlers.php';
        if (file_exists($ajax_handlers_path)) {
            include_once $ajax_handlers_path;
            dtr_simple_debug("✅ Loaded ajax-handlers.php");
        } else {
            dtr_simple_debug("❌ ERROR: ajax-handlers.php not found");
            return;
        }
    }
    
    // Call the core lead registration function directly (bypasses AJAX)
    try {
        dtr_simple_debug("🚀 Calling dtr_register_workbooks_lead()...");
        
        // Call the core registration function
        $result = dtr_register_workbooks_lead($registration_data);
        
        dtr_simple_debug("✅ Core lead registration function completed");
        
        if ($result !== null) {
            dtr_simple_debug("✅ Lead registration result: " . print_r($result, true));
        } else {
            dtr_simple_debug("ℹ️  Lead registration completed without return value");
        }
        
        dtr_simple_debug("🎉 Lead generation registration successful via Ninja Forms hook!");
        
    } catch (Exception $e) {
        dtr_simple_debug("❌ Exception during lead registration: " . $e->getMessage());
        dtr_simple_debug("Exception details: " . print_r($e, true));
    } catch (Error $e) {
        dtr_simple_debug("❌ Error during lead registration: " . $e->getMessage());
        dtr_simple_debug("Error details: " . print_r($e, true));
    }
}

// Load this when plugin loads
dtr_simple_debug("🔄 Ninja Forms Hook loaded and ready (Webinar + Lead Generation)");
