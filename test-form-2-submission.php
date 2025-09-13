<?php
/**
 * Test Form ID 2 Webinar Registration Submission
 * 
 * This script simulates a Ninja Forms Form ID 2 submission to test our webinar registration flow
 * Access via: https://dtr-final-api-push.local/wp-content/plugins/dtr-workbooks-crm-integration/test-form-2-submission.php
 */

// Load WordPress
$wp_load_path = dirname(__FILE__, 4) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('WordPress not found. Expected at: ' . $wp_load_path);
}
require_once($wp_load_path);

// Only allow logged-in users
if (!is_user_logged_in()) {
    wp_die('You must be logged in to test form submissions.');
}

echo "<h1>Form ID 2 Webinar Registration Test</h1>";
echo "<p><strong>Current User:</strong> " . wp_get_current_user()->display_name . " (" . wp_get_current_user()->user_email . ")</p>";

// Test post ID (the webinar we're testing with)
$test_post_id = 161472; // Using the second webinar from the logs
$webinar_title = get_the_title($test_post_id);
echo "<p><strong>Testing Webinar:</strong> $webinar_title (ID: $test_post_id)</p>";

if (isset($_POST['test_submit'])) {
    echo "<h2>Simulating Form ID 2 Submission...</h2>";
    
    // Simulate the form data that would come from Ninja Forms
    $form_data = [
        'fields' => [
            'question_for_speaker' => [
                'value' => $_POST['speaker_question'] ?? 'Test question from manual form submission'
            ],
            'cf_mailing_list_member_sponsor_1_optin' => [
                'value' => isset($_POST['sponsor_optin']) ? 1 : 0
            ]
        ],
        'form_id' => 2
    ];
    
    echo "<h3>Form Data Being Sent:</h3>";
    echo "<pre>" . print_r($form_data, true) . "</pre>";
    
    // Set up the environment to simulate the webinar page context
    $_SERVER['HTTP_REFERER'] = "https://dtr-final-api-push.local/webinars/" . get_post_field('post_name', $test_post_id) . "/";
    
    // Load the processor
    if (file_exists(dirname(__FILE__) . '/includes/form-submission-processors-ninjaform-hooks.php')) {
        echo "<p><strong>Loading form processor...</strong></p>";
        require_once(dirname(__FILE__) . '/includes/form-submission-processors-ninjaform-hooks.php');
        
        // Trigger the form submission handler directly
        if (function_exists('dtr_dispatch_ninja_forms_submission')) {
            echo "<p><strong>Calling form dispatcher...</strong></p>";
            dtr_dispatch_ninja_forms_submission($form_data);
            echo "<p><strong>Form submission complete! Check the debug logs.</strong></p>";
        } else {
            echo "<p><strong>ERROR:</strong> Form dispatcher function not found.</p>";
        }
    } else {
        echo "<p><strong>ERROR:</strong> Form processor file not found.</p>";
    }
    
    echo "<hr>";
    echo "<p><a href='?'>← Back to form</a></p>";
    
} else {
    // Show the test form
    ?>
    <form method="post">
        <h2>Test Form Submission</h2>
        
        <p>
            <label for="speaker_question"><strong>Question for Speaker:</strong></label><br>
            <textarea name="speaker_question" id="speaker_question" rows="3" cols="50">Test question from manual form submission - <?= date('Y-m-d H:i:s') ?></textarea>
        </p>
        
        <p>
            <label>
                <input type="checkbox" name="sponsor_optin" value="1" checked> 
                Sponsor Opt-in
            </label>
        </p>
        
        <p>
            <input type="submit" name="test_submit" value="Submit Test Form" style="background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer;">
        </p>
    </form>
    
    <hr>
    <h2>Current Registration Status</h2>
    <?php
    // Check if user is already registered for this webinar
    if (function_exists('is_user_registered_for_event')) {
        $is_registered = is_user_registered_for_event(get_current_user_id(), $test_post_id);
        echo "<p><strong>Registered for this webinar:</strong> " . ($is_registered ? "YES" : "NO") . "</p>";
    } else {
        echo "<p>Registration status function not available.</p>";
    }
    
    // Show recent log entries
    $log_file = dirname(__FILE__) . '/logs/live-webinar-registration-debug.log';
    if (file_exists($log_file)) {
        echo "<h2>Recent Debug Log Entries (Last 10 lines)</h2>";
        $log_lines = file($log_file);
        $recent_lines = array_slice($log_lines, -10);
        echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 300px; overflow-y: auto;'>";
        echo htmlspecialchars(implode('', $recent_lines));
        echo "</pre>";
    }
    ?>
<?php
}
?>
