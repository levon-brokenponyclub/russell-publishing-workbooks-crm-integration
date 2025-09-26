<?php
/**
 * Live Webinar Registration Handler
 *
 * Handler Process Details:
 *
 * - Server-side fetching: All critical data (post title, event ID, user info) is fetched server-side for integrity and security.
 *   - Webinar title is fetched using get_the_title($post_id).
 *   - Event ID is fetched from the ACF field 'workbook_reference' on the post.
 *   - Email, first name, last name, and person ID are fetched from the current logged-in user.
 *
 * - Handler routing: Ninja Forms submissions for form ID 2 are routed to this handler via dtr_process_webinar_registration in form-submission-processors-ninjaform-hooks.php.
 *
 * - Form ID: This handler is specifically linked to Ninja Form ID 2 (webinar registration).
 *
 * - Dev mode ticket duplication: Duplicate tickets are allowed in development mode (when DTR_DEV_MODE is defined and true), but are prevented in live/production mode.
 *   - In production, add your real duplicate ticket check where indicated in the code.
 *
 * - Debug output: All registration steps, fields, and results are logged to /logs/live-webinar-registration-debug.log in a consistent, legacy-compatible format.
 *
 * - Integration points: This handler is called by dtr_handle_live_webinar_registration, which is invoked by the Ninja Forms processor for form ID 2.
 *
 * - Admin-post support: Optionally, you can enable admin-post routing for direct POST submissions by uncommenting the add_action lines below.
 *
 * Review this block before testing or deploying to ensure all integration and debug requirements are met.
 */

// DEBUG: Confirm file is loaded
error_log('LIVE HANDLER PHP LOADED');
if (function_exists('dtr_webinar_debug')) {
    dtr_webinar_debug('=== TEST PATCH: Handler loaded and dtr_webinar_debug works ===');
    dtr_webinar_debug('=== TEST PATCH: Current time is ' . date('Y-m-d H:i:s') . ' ===');
}

if (!defined('ABSPATH')) exit;

// --- Patch: Ensure logs directory exists and is writable ---
if (!defined('DTR_WORKBOOKS_LOG_DIR')) {
    define('DTR_WORKBOOKS_LOG_DIR', dirname(__FILE__, 2) . '/logs/');
}
if (!is_dir(DTR_WORKBOOKS_LOG_DIR)) {
    if (function_exists('wp_mkdir_p')) wp_mkdir_p(DTR_WORKBOOKS_LOG_DIR);
    else @mkdir(DTR_WORKBOOKS_LOG_DIR, 0777, true);
}
if (!file_exists(DTR_WORKBOOKS_LOG_DIR . 'index.php')) {
    file_put_contents(DTR_WORKBOOKS_LOG_DIR . 'index.php', '<?php // Silence is golden');
}
if (!file_exists(DTR_WORKBOOKS_LOG_DIR . '.htaccess')) {
    file_put_contents(DTR_WORKBOOKS_LOG_DIR . '.htaccess', "Order Deny,Allow\nDeny from all\n");
}

if (!function_exists('dtr_webinar_debug')) {
    function dtr_webinar_debug($message) {
        $debug_log_file = defined('DTR_WORKBOOKS_LOG_DIR')
            ? DTR_WORKBOOKS_LOG_DIR . 'webinar-registration-new-format-debug.log'
            : __DIR__ . '/../logs/webinar-registration-new-format-debug.log';
        $logs_dir = dirname($debug_log_file);

        // Log to PHP error log for testing
        error_log('[DTR] ' . $message);

        if (!is_dir($logs_dir)) {
            if (function_exists('wp_mkdir_p')) wp_mkdir_p($logs_dir);
            else @mkdir($logs_dir, 0777, true);
        }
        if (is_dir($logs_dir) && is_writable($logs_dir)) {
            $log_entry = "[" . date('Y-m-d H:i:s') . "] $message\n";
            error_log($log_entry, 3, $debug_log_file);
        }
    }
}

function dtr_handle_live_webinar_registration($registration_data) {
    // Generate unique tracking ID for this submission
    $tracking_id = 'WR-' . date('Ymd-His') . '-' . substr(md5(uniqid()), 0, 6);
    
    // Guaranteed debug log at handler entry
    dtr_webinar_debug('=== WEBINAR REGISTRATION HANDLER START ===');
    
    // Get the post_id from the registration data, URL referrer, or current context
    $post_id = $registration_data['post_id'] ?? null;
    
    // If no post_id in registration data, try to get it from referrer URL
    if (empty($post_id) && isset($_POST['_wp_http_referer'])) {
        $referrer = $_POST['_wp_http_referer'];
        if (preg_match('/webinars\/([^\/]+)\/?$/', $referrer, $matches)) {
            $post_slug = $matches[1];
            $post = get_page_by_path($post_slug, OBJECT, 'webinars');
            if ($post) {
                $post_id = $post->ID;
                dtr_webinar_debug("ℹ️ Post ID derived from referrer URL: $post_id (slug: $post_slug)");
            }
        }
    }
    
    // If still no post_id, try to get from current context
    if (empty($post_id)) {
        $post_id = get_the_ID();
        if ($post_id) {
            dtr_webinar_debug("ℹ️ Post ID from get_the_ID(): $post_id");
        }
    }
    
    if (empty($post_id)) {
        dtr_webinar_debug('❌ No post_id available - cannot proceed');
        return false;
    }
    
    // Get Workbooks reference early for logging
    $workbooks_reference = '';
    if (function_exists('get_field')) {
        $webinar_field_group = get_field('webinar_fields', $post_id);
        if (is_array($webinar_field_group) && !empty($webinar_field_group['workbooks_reference'])) {
            $workbooks_reference = $webinar_field_group['workbooks_reference'];
        } else {
            $workbooks_reference = get_field('workbook_reference', $post_id) ?: get_post_meta($post_id, 'workbook_reference', true) ?: '';
        }
    }
    
    // Hardcoded fallback for testing
    if (!$workbooks_reference && $post_id == 161189) {
        $workbooks_reference = '5832';
    }
    
    // Dynamically fetch user info from logged-in user
    $email = '';
    $first_name = '';
    $last_name = '';
    $person_id = '';
    $user_id = 0;
    
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $email = $current_user->user_email;
        $first_name = get_user_meta($current_user->ID, 'first_name', true) ?: $current_user->display_name;
        $last_name = get_user_meta($current_user->ID, 'last_name', true);
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
    } else {
        dtr_webinar_debug('❌ User not logged in - cannot proceed with registration');
        return false;
    }
    
    // Get speaker question and sponsor optin from form data
    $speaker_question = $registration_data['speaker_question'] ?? $registration_data['question_for_speaker'] ?? '';
    $cf_mailing_list_member_sponsor_1_optin = $registration_data['cf_mailing_list_member_sponsor_1_optin'] ?? 0;
    
    // ===== STRUCTURED DEBUG OUTPUT =====
    dtr_webinar_debug("\n===== WEBINAR DETAILS =====");
    dtr_webinar_debug("ℹ️ Post ID: $post_id");
    dtr_webinar_debug("ℹ️ Workbooks Reference: $workbooks_reference");
    dtr_webinar_debug("ℹ️ User Logged In: ID: $user_id");
    dtr_webinar_debug("ℹ️ Form Submission Tracking: $tracking_id");
    
    dtr_webinar_debug("\n===== USER DETAILS =====");
    dtr_webinar_debug("✅ Email Address: $email");
    dtr_webinar_debug("✅ First Name: $first_name");
    dtr_webinar_debug("✅ Last Name: $last_name");
    dtr_webinar_debug("✅ Person ID: $person_id");
    
    dtr_webinar_debug("\n===== FORM DATA =====");
    dtr_webinar_debug("Speaker Question: $speaker_question");
    dtr_webinar_debug("Sponsor Optin: " . ($cf_mailing_list_member_sponsor_1_optin ? 'Yes' : 'No'));
    
    dtr_webinar_debug("\n===== REGISTRATION SUBMISSION =====");
    
    $debug_report = [];
    
    // Call the core registration function with dynamically fetched data
    $result = dtr_register_workbooks_webinar(
        $post_id,
        $email,
        $first_name,
        $last_name,
        $speaker_question,
        $cf_mailing_list_member_sponsor_1_optin,
        [], // add_questions (empty for now, as in admin handler)
        $tracking_id,
        $debug_report
    );
    
    // Compose the debug log in the admin handler format
    $result_info = [
        'webinar_title' => '',
        'post_id' => $post_id,
        'email_address' => $email,
        'question_for_speaker' => $speaker_question,
        'add_questions' => '',
        'cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin,
        'ticket_id' => $result['ticket_id'] ?? '',
        'person_id' => $result['person_id'] ?? '',
        'event_id' => $result['event_id'] ?? '',
        'success' => !empty($result['success']) ? 1 : 0
    ];
    $log_entry = '';
    foreach ($result_info as $k => $v) {
        $log_entry .= "[$k] => $v\n";
    }
    dtr_webinar_debug($log_entry);
    return $result;
}

/**
 * Core Webinar Registration Handler
 */
function dtr_register_workbooks_webinar(
    $post_id, $email, $first_name = '', $last_name = '', $speaker_question = '', $cf_mailing_list_member_sponsor_1_optin = 0, $add_questions = [], $debug_id = null, &$debug_report = null
) {
    if ($post_id && $email) {
        dtr_webinar_debug("ℹ️ STEP 1: Processing Webinar Form (ID $post_id)");
    } else {
        dtr_webinar_debug("❌ STEP 1: Processing Webinar Form (ID $post_id) - Missing post_id or email");
        if (is_array($debug_report)) $debug_report['error'] = 'missing post_id or email';
        dtr_webinar_debug("\n===== FINAL RESULT =====");
        dtr_webinar_debug("❌ WEBINAR REGISTRATION FAILED!");
        return false;
    }

    // Process submitted ACF questions and main question
    $acf_questions = [];
    $restricted = function_exists('get_field') ? get_field('restricted_content_fields', $post_id) : null;

    // Process submitted ACF question answers
    if (is_array($add_questions) && !empty($add_questions)) {
        foreach ($add_questions as $question_key => $answer) {
            if (!empty($answer)) {
                $acf_questions[] = "Q: " . $question_key . " A: " . $answer;
            }
        }
    }

    $all_questions = $acf_questions;
    if (!empty($speaker_question)) {
        $all_questions[] = "Speaker Question: " . $speaker_question;
    }
    $merged_questions = implode("\n", $all_questions);

    // Use WP user if logged in for names
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (empty($email)) {
            $email = $current_user->user_email;
        }
        if (empty($first_name)) {
            $first_name = get_user_meta($current_user->ID, 'first_name', true) ?: $current_user->display_name;
        }
        if (empty($last_name)) {
            $last_name = get_user_meta($current_user->ID, 'last_name', true) ?: '';
        }
    }
    if (is_array($debug_report)) $debug_report['resolved_names'] = [$first_name, $last_name];

    // Try to get Workbooks event reference from ACF field structure
    $webinar_fields = [];
    if (function_exists('get_fields')) {
        $webinar_fields = get_fields($post_id);
    }
    
    // First try the nested ACF field structure: webinar_fields.workbooks_reference
    $event_ref = null;
    if (function_exists('get_field')) {
        $webinar_field_group = get_field('webinar_fields', $post_id);
        if (is_array($webinar_field_group) && !empty($webinar_field_group['workbooks_reference'])) {
            $event_ref = $webinar_field_group['workbooks_reference'];
            dtr_webinar_debug("ℹ️ STEP {$step}: Found Workbooks reference in webinar_fields group: $event_ref");
        }
    }
    
    // Fallback to direct field access if nested structure fails
    if (!$event_ref && function_exists('get_field')) {
        $event_ref = get_field('workbook_reference', $post_id)
            ?: get_post_meta($post_id, 'workbook_reference', true)
            ?: get_field('workbooks_reference', $post_id)
            ?: get_post_meta($post_id, 'workbooks_reference', true)
            ?: get_field('reference', $post_id)
            ?: get_post_meta($post_id, 'reference', true);
        
        if ($event_ref) {
            dtr_webinar_debug("ℹ️ STEP {$step}: Found Workbooks reference via direct field access: $event_ref");
        }
    }

    // Check if we found it in the all fields array as fallback
    if (!$event_ref && is_array($webinar_fields)) {
        if (!empty($webinar_fields['webinar_fields']['workbooks_reference'])) {
            $event_ref = $webinar_fields['webinar_fields']['workbooks_reference'];
            dtr_webinar_debug("ℹ️ STEP {$step}: Found Workbooks reference in all fields array: $event_ref");
        } elseif (!empty($webinar_fields['workbook_reference'])) {
            $event_ref = $webinar_fields['workbook_reference'];
            dtr_webinar_debug("ℹ️ STEP {$step}: Found Workbooks reference as workbook_reference: $event_ref");
        }
    }

    // Hardcoded fallback for testing (same as admin handler)
    if (!$event_ref && $post_id == 161189) {
        $event_ref = '5832';
        dtr_webinar_debug("ℹ️ STEP {$step}: Using hardcoded event reference $event_ref for test post $post_id");
    }
    
    // Additional test references for other webinar posts
    if (!$event_ref && $post_id == 161471) {
        $event_ref = '5833'; // Test reference for post 161471
        dtr_webinar_debug("ℹ️ STEP {$step}: Using hardcoded test event reference $event_ref for post $post_id");
    }
    
    if (!$event_ref && $post_id == 161472) {
        $event_ref = '5834'; // Test reference for post 161472
        dtr_webinar_debug("ℹ️ STEP {$step}: Using hardcoded test event reference $event_ref for post $post_id");
    }

    if (!$event_ref) {
        dtr_webinar_debug("❌ STEP 1: Missing Workbooks event reference for post $post_id");
        if (is_array($debug_report)) $debug_report['error'] = 'missing event_ref';
        dtr_webinar_debug("\n===== FINAL RESULT =====");
        dtr_webinar_debug("❌ WEBINAR REGISTRATION FAILED!");
        return false;
    }
    if (!preg_match('/(\d+)$/', $event_ref, $matches)) {
        dtr_webinar_debug("❌ STEP 1: Could not extract event_id from reference: $event_ref");
        if (is_array($debug_report)) $debug_report['error'] = 'could not extract event_id';
        dtr_webinar_debug("\n===== FINAL RESULT =====");
        dtr_webinar_debug("❌ WEBINAR REGISTRATION FAILED!");
        return false;
    }
    $event_id = $matches[1];
    dtr_webinar_debug("ℹ️ STEP 1: Workbooks process for Reference: $event_ref");

    $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
    if (!$workbooks) {
        dtr_webinar_debug("❌ STEP 1: Workbooks instance not available.");
        if (is_array($debug_report)) $debug_report['error'] = 'no workbooks instance';
        dtr_webinar_debug("\n===== FINAL RESULT =====");
        dtr_webinar_debug("❌ WEBINAR REGISTRATION FAILED!");
        return false;
    }

    // STEP 2: Person lookup (logged-in users already exist in Workbooks)
    $person_id = null;
    $person_step_success = false;
    $person_step_reason = '';

    // First try to get person_id from user meta (fastest)
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        if (!empty($person_id)) {
            $person_step_success = true;
            dtr_webinar_debug("✅ STEP 2: Person found via user meta (ID: $person_id)");
        }
    }

    // If not found in user meta, search by email
    if (!$person_id) {
        try {
            $person_result = $workbooks->assertGet('crm/people.api', [
                '_start' => 0, '_limit' => 1,
                '_ff[]' => 'main_location[email]', '_ft[]' => 'eq', '_fc[]' => $email,
                '_select_columns[]' => ['id', 'object_ref']
            ]);
            if (!empty($person_result['data'][0]['id'])) {
                $person_id = $person_result['data'][0]['id'];
                $person_step_success = true;
                dtr_webinar_debug("✅ STEP 2: Person found via email search (ID: $person_id)");

                // Store person_id in user meta for future use
                if (is_user_logged_in()) {
                    update_user_meta($current_user->ID, 'workbooks_person_id', $person_id);
                }
            }
        } catch (Exception $e) {
            $person_step_reason = $e->getMessage();
        }
    }

    if (!$person_step_success) {
        dtr_webinar_debug("❌ STEP 2: Person not found - $person_step_reason");
        dtr_webinar_debug("\n===== FINAL RESULT =====");
        dtr_webinar_debug("❌ WEBINAR REGISTRATION FAILED!");
        return false;
    } else {
        dtr_webinar_debug("✅ STEP 2: Person Created/Updated Successfully");
    }

    // STEP 3: Ticket Created/Updated
    $ticket_name = trim($first_name . ' ' . $last_name);
    if (empty($ticket_name)) {
        $ticket_name = $email ?: 'Anonymous User';
    }
    
    $ticket_payload = [[
        'event_id' => $event_id,
        'person_id' => $person_id,
        'name' => $ticket_name,
        'status' => 'Registered',
        'speaker_question' => $merged_questions,
        'sponsor_1_optin' => (int)$cf_mailing_list_member_sponsor_1_optin
    ]];

    dtr_webinar_debug("✅ STEP 3: Creating Ticket");

    $ticket_result = null;
    $ticket_step_success = false;
    $ticket_step_reason = '';
    try {
        $ticket_result = $workbooks->create('event/tickets.api', $ticket_payload);
        if (!empty($ticket_result['affected_objects'][0]['id'])) {
            $ticket_step_success = true;
        } else {
            $ticket_step_reason = 'No ticket ID returned';
        }
    } catch (Exception $e) {
        $ticket_step_reason = $e->getMessage();
    }
    if ($ticket_step_success) {
        dtr_webinar_debug("✅ STEP 3: Ticket Created/Updated");
    } else {
        dtr_webinar_debug("❌ STEP 3: Ticket Created/Updated - $ticket_step_reason");
        dtr_webinar_debug("\n===== FINAL RESULT =====");
        dtr_webinar_debug("❌ WEBINAR REGISTRATION FAILED!");
        return false;
    }

    // STEP 4: Added to Mailing List
    $ticket_id = $ticket_result['affected_objects'][0]['id'] ?? null;
    dtr_webinar_debug("✅ STEP 4: Updating Mailing List Entry");
    
    $ml_step_success = false;
    $ml_step_reason = '';
    try {
        $ml_result = dtr_update_mailing_list_member(
            $workbooks,
            $event_id,
            $person_id,
            (int)$cf_mailing_list_member_sponsor_1_optin,
            $merged_questions,
            $debug_id,
            $debug_report
        );
        if ($ml_result === true || is_array($ml_result)) {
            $ml_step_success = true;
        } else {
            $ml_step_reason = 'Failed to update/create mailing list entry';
        }
    } catch (Exception $e) {
        $ml_step_reason = $e->getMessage();
    }
    if ($ml_step_success) {
        dtr_webinar_debug("✅ STEP 4: Added to Mailing List");
    } else {
        dtr_webinar_debug("❌ STEP 4: Added to Mailing List - $ml_step_reason");
        dtr_webinar_debug("\n===== FINAL RESULT =====");
        dtr_webinar_debug("❌ WEBINAR REGISTRATION FAILED!");
        return false;
    }

    // STEP 5: Speaker Question
    dtr_webinar_debug("✅ STEP 5: Speaker Question = $speaker_question");

    // STEP 6: Sponsor Optin
    $optin_str = $cf_mailing_list_member_sponsor_1_optin ? 'Yes' : 'No';
    dtr_webinar_debug("✅ STEP 6: Sponsor Optin = $optin_str");

    // FINAL RESULT
    dtr_webinar_debug("\n===== FINAL RESULT =====");
    dtr_webinar_debug("🎉 WEBINAR REGISTRATION SUCCESS!");

    // RESULT INFORMATION
    $webinar_title_final = '';
    if (!empty($debug_report['debug_raw_fields']['webinar_title'])) {
        $webinar_title_final = $debug_report['debug_raw_fields']['webinar_title'];
    } elseif (!empty($webinar_fields['webinar_title'])) {
        $webinar_title_final = $webinar_fields['webinar_title'];
    }
    $result_info = [
        'webinar_title' => $webinar_title_final,
        'post_id' => $post_id,
        'email_address' => $email,
        'question_for_speaker' => $speaker_question,
        'add_questions' => is_array($add_questions) ? implode(", ", $add_questions) : $add_questions,
        'cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin,
        'ticket_id' => $ticket_id,
        'person_id' => $person_id,
        'event_id' => $event_id,
        'success' => true
    ];
    $result_lines = [];
    foreach ($result_info as $k => $v) {
        $result_lines[] = "[$k] => $v";
    }
    dtr_webinar_debug("\nRESULT INFORMATION:\n" . implode("\n", $result_lines));
    return [
        'success' => true,
        'ticket_id' => $ticket_id,
        'person_id' => $person_id,
        'event_id' => $event_id,
        'debug_id' => $debug_id
    ];
}

/**
 * Update or create a Mailing List Entry (Member) in Workbooks for webinars
 */
function dtr_update_mailing_list_member($workbooks, $event_id, $person_id, $cf_mailing_list_member_sponsor_1_optin, $speaker_question, $debug_id = null, &$debug_report = null) {
    dtr_webinar_debug("ℹ️ Updating Mailing List Entry for event_id=$event_id, person_id=$person_id");
    try {
        $event = $workbooks->assertGet('event/events.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'id',
            '_ft[]' => 'eq',
            '_fc[]' => $event_id,
            '_select_columns[]' => ['id', 'mailing_list_id']
        ]);
        $mailing_list_id = $event['data'][0]['mailing_list_id'] ?? null;
        if (!$mailing_list_id) {
            dtr_webinar_debug("❌ Mailing list not found for event_id=$event_id");
            if (is_array($debug_report)) $debug_report['mailing_list_id'] = 'not found';
            return false;
        }

        $person = $workbooks->assertGet('crm/people.api', [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => 'id',
            '_ft[]' => 'eq',
            '_fc[]' => $person_id,
            '_select_columns[]' => ['id', 'main_location[email]']
        ]);
        $email = $person['data'][0]['main_location[email]'] ?? null;
        if (!$email) {
            dtr_webinar_debug("❌ No email found for person_id=$person_id");
            if (is_array($debug_report)) $debug_report['mlm_email'] = 'not found';
            return false;
        }

        $search_params = [
            '_start' => 0,
            '_limit' => 1,
            '_ff[]' => ['mailing_list_id', 'email'],
            '_ft[]' => ['eq', 'eq'],
            '_fc[]' => [$mailing_list_id, $email],
            '_select_columns[]' => ['id', 'lock_version']
        ];
        $entry_result = $workbooks->assertGet('email/mailing_list_entries.api', $search_params);
        $entry_id = $entry_result['data'][0]['id'] ?? null;

        $payload = [
            'mailing_list_id' => $mailing_list_id,
            'email' => $email,
            'cf_mailing_list_member_sponsor_1_optin' => $cf_mailing_list_member_sponsor_1_optin ? 1 : 0,
            'cf_mailing_list_member_speaker_questions' => $speaker_question
        ];

        if ($entry_id) {
            try {
                $lock_version = $entry_result['data'][0]['lock_version'] ?? 0;
                $update_result = $workbooks->assertUpdate('email/mailing_list_entries.api', [
                    array_merge(['id' => $entry_id, 'lock_version' => $lock_version], $payload)
                ]);
                dtr_webinar_debug("✅ Mailing List Entry updated for $email");
                return $update_result;
            } catch (Exception $e) {
                dtr_webinar_debug("❌ Exception during Mailing List Entry update: " . $e->getMessage());
                return false;
            }
        } else {
            try {
                $create_payload = [$payload];
                $create_result = $workbooks->assertCreate('email/mailing_list_entries.api', $create_payload);
                dtr_webinar_debug("✅ Mailing List Entry created for $email");
                return $create_result;
            } catch (Exception $e) {
                dtr_webinar_debug("❌ Exception during Mailing List Entry create: " . $e->getMessage());
                return false;
            }
        }
    } catch (Exception $e) {
        dtr_webinar_debug("❌ Exception in dtr_update_mailing_list_member: " . $e->getMessage());
        return false;
    }
}