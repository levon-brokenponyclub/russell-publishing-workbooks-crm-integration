<?php
if (!defined('ABSPATH')) exit;
$webinars = get_posts([
    'post_type'      => 'webinars',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);
$current_user_email = esc_attr(wp_get_current_user()->user_email);
?>
<h2>Webinar Registration Endpoint</h2>
<form id="webinar-registration-form" method="post">
    <p>
        <label for="workbooks_event_ref">Or Enter Workbooks Event ID or Reference:</label><br>
        <input type="text" id="workbooks_event_ref" name="workbooks_event_ref" class="regular-text" placeholder="Event ID or Reference" />
        <button type="button" id="fetch-event-btn" class="button">Fetch Event Details</button>
    </p>
    <div id="event-fetch-response" style="margin-bottom: 15px; color: #444;"></div>
    <p>
        <label for="webinar_post_id">Select Webinar:</label><br>
        <select id="webinar_post_id" name="webinar_post_id" required>
            <option value="">-- Select a Webinar --</option>
            <?php foreach ($webinars as $webinar): ?>
                <option value="<?php echo esc_attr($webinar->ID); ?>">
                    <?php echo esc_html($webinar->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <div id="acf-info" style="margin-bottom: 15px; display:none;">
        <strong>Workbooks Webinar Reference:</strong> <span id="webinar_ref"></span><br>
        <strong>Campaign Reference:</strong> <span id="campaign_ref"></span>
    </div>
    <p>
        <label for="participant_email">Participant Email:</label><br>
        <input type="email" id="participant_email" name="participant_email" class="regular-text" required value="<?php echo $current_user_email; ?>" readonly>
    </p>
    <p>
        <label for="speaker_question">Speaker Question (optional):</label><br>
        <textarea id="speaker_question" name="speaker_question" rows="4" cols="50"></textarea>
    </p>
    <p>
        <label>
            <input type="checkbox" name="cf_mailing_list_member_sponsor_1_optin" id="cf_mailing_list_member_sponsor_1_optin" value="1">
            I agree to receive sponsor information (opt-in)
        </label>
    </p>
    <p><button type="submit" class="button button-primary">Submit Registration</button></p>
</form>
<div id="webinar-response" style="margin-top: 20px;"></div>