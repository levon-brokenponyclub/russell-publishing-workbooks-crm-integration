<?php
/**
 * Ninja Forms Lead Generation & Webinar Registration Hook
 * Catches form submissions and registers leads/webinars in Workbooks
 */

if (!defined('ABSPATH')) exit;

// Hook into Ninja Forms submission
add_action('ninja_forms_after_submission', 'dtr_ninja_forms_lead_generation_handler', 10, 1);

/**
 * Main Ninja Forms submission handler for lead generation
 */
function dtr_ninja_forms_lead_generation_handler($form_data) {
    try {
        dtr_lead_debug("=== NINJA FORMS SUBMISSION DETECTED ===");
        dtr_lead_debug("Form Data: " . print_r($form_data, true));
        
        // Check if this is a webinar form (Form ID 2) - if so, skip processing
        $form_id = null;
        if (isset($form_data['form_id'])) {
            $form_id = $form_data['form_id'];
        } elseif (isset($form_data['id'])) {
            $form_id = $form_data['id'];
        }
        
        dtr_lead_debug("Detected form ID: " . $form_id);
        
        // Handle both webinar forms (Form ID 2) and lead generation forms (Form ID 31)
        if ($form_id == 2 || $form_id === '2') {
            dtr_lead_debug("✅ Processing webinar form (ID 2)");
            dtr_handle_webinar_form_submission($form_data);
            return;
        }
        
        // Only process lead generation forms (Form ID 31)
        if ($form_id != 31 && $form_id !== '31') {
            dtr_lead_debug("ℹ️  Form ID $form_id not configured for lead generation processing");
            return;
        }
        
        dtr_lead_debug("✅ Processing lead generation form (ID 31)");
        
        // Get current post context
        $current_post_id = get_the_ID();
        if (!$current_post_id) {
            global $post;
            if ($post && isset($post->ID)) {
                $current_post_id = $post->ID;
            }
        }
        
        // Try to get post ID from $_POST data as fallback
        if (!$current_post_id && isset($_POST['post_id'])) {
            $current_post_id = intval($_POST['post_id']);
        }
        
        // Try to get post ID from $_SERVER['HTTP_REFERER'] as last resort
        if (!$current_post_id && isset($_SERVER['HTTP_REFERER'])) {
            $referer_post_id = url_to_postid($_SERVER['HTTP_REFERER']);
            if ($referer_post_id) {
                $current_post_id = $referer_post_id;
            }
        }
        
        if (!$current_post_id) {
            dtr_lead_debug("❌ ERROR: No post context found for form submission");
            return;
        }
        
        dtr_lead_debug("✅ Post Context Found: Post ID $current_post_id");
        
        // Get post details
        $post = get_post($current_post_id);
        if (!$post) {
            dtr_lead_debug("❌ ERROR: Could not get post data for ID $current_post_id");
            return;
        }
        
        dtr_lead_debug("✅ Post Details: '{$post->post_title}' (Type: {$post->post_type})");
        
        // Extract form field data
        $form_fields = array();
        $email = '';
        $first_name = '';
        $last_name = '';
        $company = '';
        $interest_reason = '';
        $speaker_question = '';
        $sponsor_optin = false;
        $marketing_optin = false;
        
        // First, check if we have the new Ninja Forms structure with fields_by_key
        if (isset($form_data['fields_by_key']) && is_array($form_data['fields_by_key'])) {
            dtr_lead_debug("✅ Using fields_by_key structure for field extraction");
            foreach ($form_data['fields_by_key'] as $key => $field_data) {
                $key = strtolower($key);
                // Get the actual submitted value, not the default
                $value = '';
                if (isset($field_data['value'])) {
                    $value = $field_data['value'];
                } elseif (isset($field_data['settings']['value'])) {
                    $value = $field_data['settings']['value'];
                }
                
                $form_fields[$key] = $value;
                dtr_lead_debug("  Field '$key' = '$value'");
                
                // Map specific field names based on the logged-in user context
                if ($key === 'email_address') {
                    if (empty($value) || $value === '{field:email_address}' || strpos($value, '{') !== false) {
                        $current_user = wp_get_current_user();
                        if ($current_user && $current_user->user_email) {
                            $email = $current_user->user_email;
                            dtr_lead_debug("  📧 Using logged-in user email: $email");
                        } else {
                            $email = $value;
                        }
                    } else {
                        $email = $value;
                    }
                } elseif (strpos($key, 'first') !== false && strpos($key, 'name') !== false) {
                    $first_name = $value;
                } elseif (strpos($key, 'last') !== false && strpos($key, 'name') !== false) {
                    $last_name = $value;
                } elseif (strpos($key, 'company') !== false || strpos($key, 'organization') !== false) {
                    $company = $value;
                } elseif (strpos($key, 'interest') !== false || strpos($key, 'reason') !== false) {
                    $interest_reason = $value;
                } elseif (strpos($key, 'speaker') !== false && strpos($key, 'question') !== false) {
                    $speaker_question = $value;
                } elseif (strpos($key, 'sponsor') !== false && strpos($key, 'optin') !== false) {
                    $sponsor_optin = !empty($value);
                } elseif (strpos($key, 'marketing') !== false && strpos($key, 'optin') !== false) {
                    $marketing_optin = !empty($value);
                }
            }
        } elseif (isset($form_data['fields']) && is_array($form_data['fields'])) {
            dtr_lead_debug("✅ Using legacy fields array structure for field extraction");
            foreach ($form_data['fields'] as $field) {
                if (isset($field['key']) && isset($field['value'])) {
                    $key = strtolower($field['key']);
                    $value = $field['value'];
                    $form_fields[$key] = $value;
                    dtr_lead_debug("  Field '$key' = '$value'");
                    
                    // Map common field names
                    if (strpos($key, 'email') !== false) {
                        $email = $value;
                    } elseif (strpos($key, 'first') !== false && strpos($key, 'name') !== false) {
                        $first_name = $value;
                    } elseif (strpos($key, 'last') !== false && strpos($key, 'name') !== false) {
                        $last_name = $value;
                    } elseif (strpos($key, 'company') !== false || strpos($key, 'organization') !== false) {
                        $company = $value;
                    } elseif (strpos($key, 'interest') !== false || strpos($key, 'reason') !== false) {
                        $interest_reason = $value;
                    } elseif (strpos($key, 'speaker') !== false && strpos($key, 'question') !== false) {
                        $speaker_question = $value;
                    } elseif (strpos($key, 'sponsor') !== false && strpos($key, 'optin') !== false) {
                        $sponsor_optin = !empty($value);
                    } elseif (strpos($key, 'marketing') !== false && strpos($key, 'optin') !== false) {
                        $marketing_optin = !empty($value);
                    }
                }
            }
        }
        
        if (!$email) {
            dtr_lead_debug("❌ No email found in form fields, trying current user fallback...");
            
            // Fallback: Try to get the current user's email if no email was found
            $current_user = wp_get_current_user();
            if ($current_user && $current_user->user_email) {
                $email = $current_user->user_email;
                dtr_lead_debug("✅ Using logged-in user email as fallback: $email");
            } else {
                dtr_lead_debug("❌ ERROR: No email address found in form submission and no logged-in user");
                return;
            }
        }
        
        dtr_lead_debug("✅ Form Data Extracted:");
        dtr_lead_debug("  - Email: $email");
        dtr_lead_debug("  - Name: $first_name $last_name");
        dtr_lead_debug("  - Company: $company");
        dtr_lead_debug("  - Interest Reason: $interest_reason");
        dtr_lead_debug("  - Speaker Question: $speaker_question");
        dtr_lead_debug("  - Sponsor Opt-in: " . ($sponsor_optin ? 'Yes' : 'No'));
        dtr_lead_debug("  - Marketing Opt-in: " . ($marketing_optin ? 'Yes' : 'No'));
        
        // Get Workbooks event reference and campaign reference from ACF fields
        $workbooks_reference = '';
        $campaign_reference = '';
        
        // Check if this post has the "Gated Content" field group and if restrict_post is enabled
        $restrict_post = get_field('restrict_post', $current_post_id);
        dtr_lead_debug("Restrict post setting: " . ($restrict_post ? 'true' : 'false'));
        
        if ($restrict_post) {
            // Access the nested restricted_content_fields group
            $restricted_content_fields = get_field('restricted_content_fields', $current_post_id);
            dtr_lead_debug('Restricted content fields: ' . print_r($restricted_content_fields, true));
            
            if (is_array($restricted_content_fields)) {
                // Extract reference and campaign_reference from the nested group
                if (isset($restricted_content_fields['reference'])) {
                    $workbooks_reference = $restricted_content_fields['reference'];
                    dtr_lead_debug("Found workbooks reference '$workbooks_reference' in restricted_content_fields.reference");
                }
                
                if (isset($restricted_content_fields['campaign_reference'])) {
                    $campaign_reference = $restricted_content_fields['campaign_reference'];
                    dtr_lead_debug("Found campaign reference '$campaign_reference' in restricted_content_fields.campaign_reference");
                }
            }
        } else {
            // Fallback: Check for legacy field structure (direct fields)
            $workbooks_ref_fields = [
                'workbooks_event_reference',
                'workbooks_reference', 
                'event_reference',
                'reference'
            ];
            
            foreach ($workbooks_ref_fields as $field_name) {
                $value = get_field($field_name, $current_post_id) ?: get_post_meta($current_post_id, $field_name, true);
                if ($value) {
                    $workbooks_reference = $value;
                    dtr_lead_debug("Found workbooks reference '$value' in field '$field_name'");
                    break;
                }
            }
            
            // Try common field names for Campaign reference
            $campaign_ref_fields = [
                'campaign_reference',
                'campaign_ref',
                'workbooks_campaign_reference'
            ];
            
            foreach ($campaign_ref_fields as $field_name) {
                $value = get_field($field_name, $current_post_id) ?: get_post_meta($current_post_id, $field_name, true);
                if ($value) {
                    $campaign_reference = $value;
                    dtr_lead_debug("Found campaign reference '$value' in field '$field_name'");
                    break;
                }
            }
        }
        
        if (!$workbooks_reference) {
            dtr_lead_debug("❌ ERROR: No Workbooks event reference found for post ID $current_post_id");
            return;
        }
        
        dtr_lead_debug("✅ Workbooks Event Reference: $workbooks_reference");
        dtr_lead_debug("✅ Campaign Reference: $campaign_reference");
        
        // Extract numeric event ID from reference (e.g. EVENT-2893 -> 2893)
        $event_id = null;
        if (preg_match('/(\d+)$/', $workbooks_reference, $matches)) {
            $event_id = $matches[1];
            dtr_lead_debug("✅ Extracted event numeric ID: $event_id from $workbooks_reference");
        } else {
            dtr_lead_debug("❌ ERROR: Could not extract event_id from reference $workbooks_reference");
            return;
        }
        
        // Register the lead in Workbooks
        $registration_result = dtr_register_workbooks_event_lead(
            $event_id,
            $email,
            $first_name,
            $last_name,
            $company,
            $interest_reason,
            $speaker_question,
            $sponsor_optin,
            $marketing_optin,
            $current_post_id,
            $post->post_title,
            $campaign_reference
        );
        
        if ($registration_result) {
            dtr_lead_debug("🥳 FUCK YEAH - EVENT REGISTRATION SUCCESSFUL - CELEBRATE");
            dtr_lead_debug("Registration result: " . print_r($registration_result, true));
        } else {
            dtr_lead_debug("❌ Lead generation registration failed");
        }
        
    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception during lead registration: " . $e->getMessage());
        dtr_lead_debug("Exception details: " . print_r($e, true));
    }
}

/**
 * Register a lead in a Workbooks event (not campaign)
 * Creates both the person record, the event ticket, and the sales lead (even if ticket exists)
 */
function dtr_register_workbooks_event_lead($event_id, $email, $first_name = '', $last_name = '', $company = '', $interest_reason = '', $speaker_question = '', $sponsor_optin = false, $marketing_optin = false, $post_id = null, $post_title = '', $campaign_reference = '') {
    try {
        // Step 1: Check if person already exists or create them
        $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
        if (!$workbooks) {
            dtr_lead_debug('❌ ERROR: Workbooks instance not available');
            return false;
        }
        
        dtr_lead_debug("🔍 Checking if person exists with email: $email");
        $person_search_result = $workbooks->assertGet('crm/people.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'main_location[email]',
            '_ft[]' => 'eq',
            '_fc[]' => $email,
            '_select_columns[]' => ['id', 'object_ref', 'person_first_name', 'person_last_name', 'main_location[email]']
        ]);
        
        $person_id = null;
        $person_object_ref = null;
        
        if (!empty($person_search_result['data'][0])) {
            $person_data = $person_search_result['data'][0];
            $person_id = $person_data['id'];
            $person_object_ref = $person_data['object_ref'];
            dtr_lead_debug("✅ Found existing person: ID $person_id, Object Ref: PERS-$person_object_ref");
        } else {
            dtr_lead_debug("👤 Creating new person in Workbooks");
            
            $person_payload = [
                'person_first_name' => $first_name,
                'person_last_name' => $last_name,
                'main_location[email]' => $email,
                'cf_person_dtr_subscriber_type' => 'Lead',
                'cf_person_dtr_web_member' => 1,
                'lead_source_type' => 'Lead Generation Form',
                'cf_person_is_person_active_or_inactive' => 'Active',
                'cf_person_data_source_detail' => 'DTR Lead Gen - ' . $post_title,
                'created_through_reference' => 'leadgen_' . $post_id . '_' . time()
            ];
            
            if ($company) {
                $person_payload['cf_person_claimed_employer'] = $company;
            }
            
            if ($marketing_optin) {
                $person_payload['cf_person_dtr_news'] = 1;
                $person_payload['cf_person_dtr_events'] = 1;
            }
            
            $person_result = $workbooks->assertCreate('crm/people', [$person_payload]);
            
            if (!empty($person_result['data'][0]['id'])) {
                $person_id = $person_result['data'][0]['id'];
                $person_object_ref = $person_result['data'][0]['object_ref'] ?? '';
                dtr_lead_debug("✅ Created new person: ID $person_id, Object Ref: PERS-$person_object_ref");
            } else {
                dtr_lead_debug("❌ ERROR: Failed to create person in Workbooks");
                dtr_lead_debug("Person creation result: " . print_r($person_result, true));
                return false;
            }
        }
        
        // Always create a new event ticket
        dtr_lead_debug("🎫 Creating event ticket for event $event_id");
        $ticket_payload = [[
            'event_id' => $event_id,
            'person_id' => $person_id,
            'name' => $first_name . ' ' . $last_name,
            'status' => 'Registered'
        ]];
        if ($interest_reason) {
            $ticket_payload[0]['cf_event_ticket_interest_reason'] = $interest_reason;
        }
        if ($speaker_question) {
            $ticket_payload[0]['cf_event_ticket_speaker_questions'] = $speaker_question;
        }
        if ($sponsor_optin) {
            $ticket_payload[0]['cf_event_ticket_sponsor_optin'] = 1;
        }
        if ($campaign_reference) {
            $ticket_payload[0]['cf_event_ticket_campaign_ref'] = $campaign_reference;
        }
        $ticket_result = $workbooks->create('event/tickets.api', $ticket_payload);

        if (!empty($ticket_result['affected_objects'][0]['id'])) {
            $ticket_id = $ticket_result['affected_objects'][0]['id'];
            dtr_lead_debug("✅ Created event ticket: ID $ticket_id");

            // Always create a new sales lead
            dtr_lead_debug(" 🎉 Creating event lead generation for event $event_id");
            $lead_payload = [[
                'assigned_to' => 1,
                'person_lead_party[name]' => $first_name . ' ' . $last_name,
                'person_lead_party[person_first_name]' => $first_name,
                'person_lead_party[person_last_name]' => $last_name,
                'person_lead_party[email]' => $email,
                'org_lead_party[name]' => $company,
                'cf_lead_data_source_detail' => 'DTR-LEADGEN-' . $event_id
            ]];
            $lead_result = $workbooks->assertCreate('crm/sales_leads.api', $lead_payload);

            $lead_id = '';
            if (!empty($lead_result['affected_objects'][0]['id'])) {
                $lead_id = $lead_result['affected_objects'][0]['id'];
                dtr_lead_debug("✅  Created lead for person $first_name $last_name $email");
            } else {
                dtr_lead_debug("❌ ERROR: Failed to create sales lead for $first_name $last_name $email");
                dtr_lead_debug("Lead creation result: " . print_r($lead_result, true));
            }

            dtr_lead_debug("🥳 FUCK YEAH - EVENT REGISTRATION SUCCESSFUL - CELEBRATE");

            return [
                'success' => true,
                'message' => 'Lead and ticket created for event',
                'person_id' => $person_id,
                'person_object_ref' => $person_object_ref,
                'ticket_id' => $ticket_id,
                'event_id' => $event_id,
                'lead_id' => $lead_id,
                'existing_registration' => false
            ];
        } else {
            dtr_lead_debug("❌ ERROR: Failed to create event ticket");
            dtr_lead_debug("Ticket creation result: " . print_r($ticket_result, true));
            return false;
        }
        
    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception in event lead registration: " . $e->getMessage());
        dtr_lead_debug("Exception details: " . print_r($e, true));
        return false;
    }
}

/**
 * Debug logging function for lead generation
 */
function dtr_lead_debug($message) {
    // Log to WordPress debug.log if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[DTR Lead Gen] $message");
    }
    
    // Also log to our custom debug file in plugin logs directory
    $debug_log_file = defined('WORKBOOKS_NF_PATH') ? WORKBOOKS_NF_PATH . 'logs/gated-post-submissions-debug.log' : __DIR__ . '/gated-post-submissions-debug.log';
    
    // Ensure the logs directory exists
    $logs_dir = dirname($debug_log_file);
    if (!file_exists($logs_dir)) {
        wp_mkdir_p($logs_dir);
    }
    
    $log_entry = "[" . date('Y-m-d H:i:s') . "] $message\n";
    error_log($log_entry, 3, $debug_log_file);
}

/**
 * Handle webinar form submissions (Form ID 2)
 */
function dtr_handle_webinar_form_submission($form_data) {
    dtr_lead_debug("=== PROCESSING WEBINAR FORM SUBMISSION ===");
    
    // Get participant email from current user (webinar requires login)
    $current_user = wp_get_current_user();
    if (!$current_user || !$current_user->user_email) {
        dtr_lead_debug("❌ ERROR: No current user or user email - webinar registration requires login");
        return;
    }
    
    $participant_email = $current_user->user_email;
    dtr_lead_debug("✅ Using current user email: $participant_email");
    
    // Extract form fields
    $webinar_title = '';
    $post_id = '';
    $speaker_question = '';
    $sponsor_optin = '';
    
    if (isset($form_data['fields']) && is_array($form_data['fields'])) {
        foreach ($form_data['fields'] as $field) {
            if (isset($field['key']) && isset($field['value'])) {
                $key = $field['key'];
                $value = $field['value'];
                
                dtr_lead_debug("Field $key: $value");
                
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
    
    // Validate required data
    if (empty($webinar_title) || empty($post_id) || empty($participant_email)) {
        dtr_lead_debug("❌ ERROR: Missing required webinar data");
        dtr_lead_debug("  - Webinar Title: " . ($webinar_title ?: 'MISSING'));
        dtr_lead_debug("  - Post ID: " . ($post_id ?: 'MISSING'));
        dtr_lead_debug("  - Email: " . ($participant_email ?: 'MISSING'));
        return;
    }
    
    dtr_lead_debug("✅ All required webinar data present");
    
    // Call the core webinar registration function
    dtr_call_webinar_registration($post_id, $participant_email, $speaker_question, $sponsor_optin);
}

/**
 * Call the core webinar registration function
 */
function dtr_call_webinar_registration($post_id, $participant_email, $speaker_question, $sponsor_optin) {
    dtr_lead_debug("=== CALLING CORE WEBINAR REGISTRATION ===");
    
    // Check if the core registration function exists
    if (!function_exists('dtr_register_workbooks_webinar')) {
        dtr_lead_debug("❌ ERROR: Core webinar registration function not found");
        
        // Include the ajax-handlers file if it's not loaded
        $ajax_handlers_path = WP_CONTENT_DIR . '/plugins/dtr-workbooks-crm-integration/includes/ajax-handlers.php';
        if (file_exists($ajax_handlers_path)) {
            include_once $ajax_handlers_path;
            dtr_lead_debug("✅ Loaded ajax-handlers.php");
        } else {
            dtr_lead_debug("❌ ERROR: ajax-handlers.php not found");
            return;
        }
    }
    
    // Prepare data for the core registration function
    $registration_data = array(
        'webinar_post_id' => $post_id,
        'participant_email' => $participant_email,
        'speaker_question' => $speaker_question,
        'privacy_consent' => $sponsor_optin
    );
    
    dtr_lead_debug("✅ Prepared webinar registration data:");
    dtr_lead_debug("  - webinar_post_id: " . $registration_data['webinar_post_id']);
    dtr_lead_debug("  - participant_email: " . $registration_data['participant_email']);
    dtr_lead_debug("  - speaker_question: " . $registration_data['speaker_question']);
    dtr_lead_debug("  - privacy_consent: " . $registration_data['privacy_consent']);
    
    // Call the core registration function directly
    try {
        dtr_lead_debug("🚀 Calling dtr_register_workbooks_webinar()...");
        
        $result = dtr_register_workbooks_webinar($registration_data);
        
        dtr_lead_debug("✅ Core webinar registration completed");
        
        if ($result !== null) {
            dtr_lead_debug("✅ Registration result: " . print_r($result, true));
        } else {
            dtr_lead_debug("ℹ️  Registration completed without return value");
        }
        
        dtr_lead_debug("🎉 Webinar registration successful via Ninja Forms hook!");
        
    } catch (Exception $e) {
        dtr_lead_debug("❌ Exception during webinar registration: " . $e->getMessage());
    } catch (Error $e) {
        dtr_lead_debug("❌ Error during webinar registration: " . $e->getMessage());
    }
}

// Log that this hook is loaded
dtr_lead_debug("🔄 Ninja Forms Lead Generation Hook loaded and ready");
?>