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
        <p><button type="submit" class="button button-primary">Submit Registration</button></p>
    </form>
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
            fetch('admin-post.php?action=dtr_test_webinar_registration', {
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