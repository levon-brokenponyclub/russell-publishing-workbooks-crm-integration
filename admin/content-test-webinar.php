<?php
if (!defined('ABSPATH')) exit;

// Handle direct form submission (bypass admin-post)
if (isset($_POST['direct_submit']) && current_user_can('manage_options')) {
    echo '<div style="background: #fff; padding: 20px; margin: 10px 0; border: 1px solid #ccc;">';
    echo '<h3>Direct Handler Test Results:</h3>';
    
    // Include the webinar handler
    $handler_file = plugin_dir_path(__FILE__) . 'form-handler-admin-webinar-registration.php';
    echo '<p><strong>Handler file path:</strong> ' . esc_html($handler_file) . '</p>';
    
    if (file_exists($handler_file)) {
        echo '<p style="color: green;">✅ Handler file exists</p>';
        require_once $handler_file;
        
        if (function_exists('dtr_handle_admin_webinar_registration')) {
            echo '<p style="color: green;">✅ Handler function exists</p>';
        } else {
            echo '<p style="color: red;">❌ Handler function does NOT exist after including file</p>';
        }
        
        if (function_exists('dtr_webinar_debug')) {
            echo '<p style="color: green;">✅ Debug function exists</p>';
            // Test the debug function
            dtr_webinar_debug("TEST: Debug function is working");
        } else {
            echo '<p style="color: red;">❌ Debug function does NOT exist</p>';
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
        
        if (function_exists('dtr_handle_admin_webinar_registration')) {
            echo '<p style="color: green;">✅ Handler function exists</p>';
            
            try {
                // Test debug function first
                if (function_exists('dtr_webinar_debug')) {
                    echo '<p style="color: green;">✅ Debug function exists</p>';
                    dtr_webinar_debug("TEST: Debug function is working from direct test");
                    echo '<p>✅ Debug function test called</p>';
                } else {
                    echo '<p style="color: red;">❌ Debug function does NOT exist</p>';
                }
                
                // Log the input data for debugging
                $admin_log_file = plugin_dir_path(__FILE__) . 'admin-webinar-debug.log';
                error_log("=== DIRECT TEST START ===\n" . print_r($registration_data, true) . "=== CALLING HANDLER ===\n", 3, $admin_log_file);
                
                // Enable error reporting to catch any issues
                $old_error_reporting = error_reporting(E_ALL);
                $old_display_errors = ini_get('display_errors');
                ini_set('display_errors', 1);
                
                echo '<p>🔄 About to call handler function...</p>';
                $result = dtr_handle_admin_webinar_registration($registration_data);
                echo '<p>✅ Handler function completed</p>';
                
                // Restore error reporting
                error_reporting($old_error_reporting);
                ini_set('display_errors', $old_display_errors);
                
                // Log the result
                error_log("=== HANDLER RESULT ===\n" . print_r($result, true) . "=== DIRECT TEST END ===\n", 3, $admin_log_file);
                
                echo '<p><strong>Handler returned:</strong> <pre>' . esc_html(print_r($result, true)) . '</pre></p>';
                
                if (!empty($result['success'])) {
                    echo '<p style="color: green;"><strong>✅ Registration Successful!</strong></p>';
                    echo '<p>Ticket ID: ' . esc_html($result['ticket_id'] ?? 'N/A') . '</p>';
                    echo '<p>Person ID: ' . esc_html($result['person_id'] ?? 'N/A') . '</p>';
                    echo '<p>Event ID: ' . esc_html($result['event_id'] ?? 'N/A') . '</p>';
                } else {
                    echo '<p style="color: red;"><strong>❌ Registration Failed</strong></p>';
                    echo '<p>Result: ' . esc_html(print_r($result, true)) . '</p>';
                    echo '<p>Check admin-webinar-debug.log for details.</p>';
                }
            } catch (Exception $e) {
                echo '<p style="color: red;"><strong>❌ Exception:</strong> ' . esc_html($e->getMessage()) . '</p>';
                echo '<p style="color: red;"><strong>File:</strong> ' . esc_html($e->getFile()) . ' <strong>Line:</strong> ' . esc_html($e->getLine()) . '</p>';
                echo '<pre style="background: #f0f0f0; padding: 10px; font-size: 11px;">' . esc_html($e->getTraceAsString()) . '</pre>';
                error_log("=== DIRECT TEST EXCEPTION ===\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n=== END EXCEPTION ===\n", 3, plugin_dir_path(__FILE__) . 'admin-webinar-debug.log');
            } catch (Error $e) {
                echo '<p style="color: red;"><strong>❌ PHP Fatal Error:</strong> ' . esc_html($e->getMessage()) . '</p>';
                echo '<p style="color: red;"><strong>File:</strong> ' . esc_html($e->getFile()) . ' <strong>Line:</strong> ' . esc_html($e->getLine()) . '</p>';
                echo '<pre style="background: #f0f0f0; padding: 10px; font-size: 11px;">' . esc_html($e->getTraceAsString()) . '</pre>';
                error_log("=== DIRECT TEST PHP ERROR ===\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n=== END ERROR ===\n", 3, plugin_dir_path(__FILE__) . 'admin-webinar-debug.log');
            }
        } else {
            echo '<p style="color: orange;">⚠️ Handler function not available</p>';
        }
    } else {
        echo '<p style="color: red;">❌ Handler file not found: ' . esc_html($handler_file) . '</p>';
    }
    echo '</div>';
}

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

<!-- Direct Submit Form (No AJAX, No admin-post) -->
<div style="background: #f0f0f1; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <h3>🚀 Direct Test (Recommended)</h3>
    <p>This submits directly to this page and calls the handler function immediately.</p>
    <form method="post">
        <input type="hidden" name="post_id" value="161189">
        <input type="hidden" name="event_id" value="5832">
        <input type="hidden" name="first_name" value="Levon">
        <input type="hidden" name="last_name" value="Gravett">
        <input type="hidden" name="person_id" value="684710">
        
        <p>
            <label for="direct_email">Email:</label><br>
            <input type="email" id="direct_email" name="email" class="regular-text" required value="<?php echo $current_user_email; ?>">
        </p>
        <p>
            <label for="direct_question">Speaker Question:</label><br>
            <textarea id="direct_question" name="speaker_question" rows="3" cols="50" placeholder="Optional question for the speaker"></textarea>
        </p>
        <p>
            <label>
                <input type="checkbox" name="cf_mailing_list_member_sponsor_1_optin" value="1">
                I agree to receive sponsor information
            </label>
        </p>
        <p>
            <button type="submit" name="direct_submit" class="button button-primary">🎯 Direct Test Submit</button>
        </p>
    </form>
</div>

<!-- AJAX Form (Original) -->
<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <h3>⚡ AJAX Test (admin-post.php)</h3>
    <p>This uses AJAX and admin-post.php (may have 500 errors).</p>
    <form id="webinar-registration-form" method="post">
        <!-- Hardcoded hidden fields for test -->
    <input type="hidden" name="post_id" value="161189">
    <input type="hidden" name="event_id" value="5832">
        <p>
            <label for="participant_email">Participant Email:</label><br>
            <input type="email" id="email" name="email" class="regular-text" required value="<?php echo $current_user_email; ?>" readonly>
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
    <input type="hidden" name="first_name" value="Levon">
    <input type="hidden" name="last_name" value="Gravett">
    <input type="hidden" name="person_id" value="684710">
        <p><button type="submit" class="button button-secondary">⚡ AJAX Submit</button></p>
    </form>
</div>
<div id="webinar-response" style="margin-top: 20px;"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('webinar-registration-form');
    if (form) {
        // Helper to log fields
        function logFields() {
            var fieldMap = {
                'post_id': 'Post ID',
                'email': 'Email Address',
                'speaker_question': 'Speaker Question',
                'cf_mailing_list_member_sponsor_1_optin': 'Sponsor Optin',
                'first_name': 'First Name',
                'last_name': 'Last Name',
                'event_id': 'Event ID'
            };
            var debugLines = [];
            console.group('Webinar Registration Form Submission');
            Object.keys(fieldMap).forEach(function(key) {
                var el = form.elements[key];
                var value = '';
                if (el) {
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        value = el.checked ? el.value : '';
                    } else {
                        value = el.value;
                    }
                }
                console.log(fieldMap[key] + ' (' + key + '):', value);
                debugLines.push(fieldMap[key] + ' (' + key + '): ' + value);
            });
            console.groupEnd();
            return debugLines;
        }

        // Log before submission
        form.addEventListener('submit', function(e) {
            logFields();
            // Bright green bold 20px console message
            console.log('%cSUBMIT YOU FUCKER....', 'color: #fff; background: #00d900; font-weight: bold; font-size: 20px; padding: 4px 12px; border-radius: 4px;');
            // Red bold 18px console message for duplicate ticket testing
            console.log('%cTesting: Duplicate Tickets On - Remove for Production', 'color: #fff; background: #d90000; font-weight: bold; font-size: 18px; padding: 4px 12px; border-radius: 4px;');
        });

        // Intercept submit to handle AJAX and alerts
        form.onsubmit = function(e) {
            e.preventDefault();
            var debugLines = logFields();
            // Send debug info to PHP for logging
            fetch(window.location.pathname + '?admin_webinar_debug=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ debug: debugLines.join('\n') })
            });

            // Submit the form via AJAX to admin-post.php
            var formData = new FormData(form);
            
            // Add action for our webinar handler
            formData.append('action', 'dtr_admin_test_webinar');
            
            fetch('/wp-admin/admin-post.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) { return response.text(); })
            .then(function(html) {
                // Check for success/fail in returned HTML
                if (html.match(/Registration Successful/i)) {
                    alert('SUCCESS');
                } else {
                    alert('BOOOOHOOOO');
                }
                document.getElementById('webinar-response').innerHTML = html;
            })
            .catch(function() {
                alert('BOOOOHOOOO');
            });
            return false;
        };
    }
});
</script>

<?php
// PHP: If debug info is sent, log it to admin-webinar-debug.log
if (isset($_GET['admin_webinar_debug']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!empty($data['debug'])) {
        $log_file = __DIR__ . '/admin-webinar-debug.log';
        $entry = '[' . date('Y-m-d H:i:s') . "] Admin Test Form Debug: " . $data['debug'] . "\n";
        file_put_contents($log_file, $entry, FILE_APPEND);
    }
    exit;
}