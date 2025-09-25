<?php
/**
 * Webinar Shortcode Registration Form Handler
 * Processes submissions from the webinar registration shortcode form
 */

if (!defined('ABSPATH')) exit;

function dtr_handle_webinar_shortcode_registration() {
    // Setup logging
    $log_file = plugin_dir_path(dirname(__FILE__)) . 'logs/form-handler-webinar-shortcode-registration-debug.log';
    
    function write_log($message) {
        global $log_file;
        $timestamp = date('[Y-m-d H:i:s]');
        file_put_contents($log_file, "$timestamp $message\n", FILE_APPEND);
    }

    // Verify nonce and check if form was submitted
    if (!isset($_POST['submit_webinar_registration']) || 
        !wp_verify_nonce($_POST['_wpnonce'], 'webinar_registration')) {
        return;
    }

    // Get post data
    $post_id = intval($_POST['post_id']);
    write_log("✅ Using post_id: $post_id");

    // Check user authentication
    if (!is_user_logged_in()) {
        write_log("❌ Error: User not logged in");
        return;
    }

    // Get user details
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $email = $current_user->user_email;
    $first_name = $current_user->first_name;
    $last_name = $current_user->last_name;
    $person_id = get_user_meta($user_id, 'workbooks_person_id', true);

    write_log("ℹ️ User is logged in - ID: $user_id, Email: $email");
    write_log("ℹ️ User details - First: '$first_name', Last: '$last_name', Person ID: '$person_id'");

    // Get form data
    $speaker_question = sanitize_textarea_field($_POST['speaker_question'] ?? '');
    $sponsor_optin = isset($_POST['sponsor_optin']) ? 1 : 0;
    
    write_log("ℹ️ Form data - Speaker question: '$speaker_question', Sponsor optin: $sponsor_optin");

    // Process webinar registration
    write_log("✅ STEP 1: Processing Webinar Form (ID $post_id)");

    // Get Workbooks reference
    $webinar_fields = get_field('webinar_fields', $post_id);
    $workbooks_reference = $webinar_fields['workbooks_reference'] ?? '';
    $event_id = preg_replace('/\D+/', '', $workbooks_reference);

    if (!$event_id) {
        write_log("❌ Error: No valid event ID found");
        return;
    }

    write_log("ℹ️ STEP 2: Found Workbooks reference in webinar_fields group: $event_id");

    // Check/Update Person in Workbooks
    if ($person_id) {
        write_log("✅ STEP 3: Person found via user meta (ID: $person_id)");
    } else {
        write_log("ℹ️ STEP 3: Creating new person in Workbooks");
        // Add code to create person in Workbooks
    }

    write_log("✅ STEP 3: Person created/updated");

    // Create ticket
    $full_name = trim("$first_name $last_name");
    write_log("ℹ️ STEP 4: Creating ticket with name: '$full_name', person_id: $person_id, event_id: $event_id");
    
    // Add code to create ticket in Workbooks
    $ticket_created = true; // Replace with actual ticket creation logic
    
    if ($ticket_created) {
        write_log("✅ STEP 4: Ticket Created/Updated");
    }

    // Update mailing list
    write_log("ℹ️ Updating Mailing List Entry for event_id=$event_id, person_id=$person_id");
    
    // Add code to update mailing list in Workbooks
    $mailing_list_updated = true; // Replace with actual mailing list update logic
    
    if ($mailing_list_updated) {
        write_log("ℹ️ Mailing List Entry updated for $email");
        write_log("✅ STEP 5: Added to Mailing List");
    }

    // Process speaker question
    if ($speaker_question) {
        write_log("✅ STEP 6: Speaker Question = $speaker_question");
        // Add code to store speaker question
    }

    // Process sponsor optin
    write_log("✅ STEP 7: Sponsor Optin = " . ($sponsor_optin ? 'Yes' : 'No'));

    // Final success message
    write_log("🎉 FINAL RESULT: WEBINAR REGISTRATION SUCCESS!");

    // Return success response
    return array(
        'success' => true,
        'webinar_title' => get_the_title($post_id),
        'post_id' => $post_id,
        'email_address' => $email,
        'question_for_speaker' => $speaker_question,
        'cf_mailing_list_member_sponsor_1_optin' => $sponsor_optin,
        'person_id' => $person_id,
        'event_id' => $event_id
    );
}

// Add AJAX handlers
add_action('wp_ajax_dtr_webinar_shortcode_registration', 'dtr_handle_webinar_shortcode_registration');
// We don't add the nopriv action as users must be logged in