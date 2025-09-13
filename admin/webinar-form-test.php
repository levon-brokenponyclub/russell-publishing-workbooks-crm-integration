<?php
/**
 * Webinar Registration Test Page
 * 
 * Admin interface for testing webinar form submissions with dynamic webinar selection
 */

// Check if user is logged in and is admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied. You must be an admin to view this page.');
}

echo '<div class="wrap plugin-admin-content">';
echo '<h1>Webinar Registration Test</h1>';
echo '<h2>This page tests webinar registration form submissions with dynamic webinar selection and user data fetching.</h2>';

// Function to get available webinars
if (!function_exists('get_available_webinars_for_test')) {
function get_available_webinars_for_test() {
    $webinars = [];
    
    // Get posts with webinar_fields ACF field group
    $args = [
        'post_type' => ['webinars', 'post', 'any'], // Include webinars post type and others
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'webinar_fields_webinar_registration_form',
                'compare' => 'EXISTS'
            ],
            [
                'key' => '_webinar_fields_webinar_registration_form', // Meta key with ACF prefix
                'compare' => 'EXISTS'
            ]
        ]
    ];
    
    $posts = get_posts($args);
    
    foreach ($posts as $post) {
        $workbooks_reference = '';
        
        // Try to get workbooks reference from nested ACF structure first
        if (function_exists('get_field')) {
            $webinar_field_group = get_field('webinar_fields', $post->ID);
            if (is_array($webinar_field_group) && !empty($webinar_field_group['workbooks_reference'])) {
                $workbooks_reference = $webinar_field_group['workbooks_reference'];
            }
        }
        
        // Fallback to direct ACF field access if nested structure fails
        if (empty($workbooks_reference) && function_exists('get_field')) {
            $workbooks_reference = get_field('workbook_reference', $post->ID) 
                ?: get_field('workbooks_reference', $post->ID)
                ?: get_field('reference', $post->ID);
        }
        
        // Hardcoded test references for specific posts
        if (empty($workbooks_reference)) {
            if ($post->ID == 161189) {
                $workbooks_reference = '5832';
            } elseif ($post->ID == 161471) {
                $workbooks_reference = '5833';
            } elseif ($post->ID == 161472) {
                $workbooks_reference = '5834';
            }
        }

        $webinars[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'workbooks_reference' => $workbooks_reference,
            'post_type' => $post->post_type,
            'date' => $post->post_date
        ];
    }
    
    // Add our test post if not found
    $test_post_found = false;
    foreach ($webinars as $webinar) {
        if ($webinar['id'] == 161189) {
            $test_post_found = true;
            break;
        }
    }
    
    if (!$test_post_found) {
        $test_post = get_post(161189);
        if ($test_post) {
            $webinars[] = [
                'id' => 161189,
                'title' => $test_post->post_title . ' (Test Post)',
                'workbooks_reference' => '5832', // Hardcoded for testing
                'post_type' => $test_post->post_type,
                'date' => $test_post->post_date
            ];
        }
    }
    
    return $webinars;
}
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_submission']) && check_admin_referer('test_webinar_registration', '_wpnonce')) {
    echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0;">';
    echo '    <h3>🧪 Testing Webinar Registration</h3>';
    
    try {
        // Get selected webinar details
        $selected_post_id = intval($_POST['webinar_selection'] ?? 161189);
        $selected_post = get_post($selected_post_id);
        
        if (!$selected_post) {
            throw new Exception("Selected webinar post not found: $selected_post_id");
        }
        
        // Get workbooks reference for selected webinar using correct field structure
        $workbooks_reference = '';
        
        // Try nested ACF structure first: webinar_fields.workbooks_reference
        if (function_exists('get_field')) {
            $webinar_field_group = get_field('webinar_fields', $selected_post_id);
            if (is_array($webinar_field_group) && !empty($webinar_field_group['workbooks_reference'])) {
                $workbooks_reference = $webinar_field_group['workbooks_reference'];
            }
        }
        
        // Try alternative ACF field names if not found
        if (empty($workbooks_reference) && function_exists('get_field')) {
            $workbooks_reference = get_field('workbook_reference', $selected_post_id)
                ?: get_field('workbooks_reference', $selected_post_id)
                ?: get_field('reference', $selected_post_id);
        }
        
        // Hardcoded test references for specific posts
        if (empty($workbooks_reference)) {
            if ($selected_post_id == 161189) {
                $workbooks_reference = '5832';
            } elseif ($selected_post_id == 161471) {
                $workbooks_reference = '5833';
            } elseif ($selected_post_id == 161472) {
                $workbooks_reference = '5834';
            }
        }
        
        echo '<p><strong>Selected Webinar Details:</strong></p>';
        echo '<ul>';
        echo '<li><strong>Post ID:</strong> ' . esc_html($selected_post_id) . '</li>';
        echo '<li><strong>Title:</strong> ' . esc_html($selected_post->post_title) . '</li>';
        echo '<li><strong>Post Type:</strong> ' . esc_html($selected_post->post_type) . '</li>';
        echo '<li><strong>Workbooks Reference:</strong> ' . esc_html($workbooks_reference ?: 'Not found') . '</li>';
        echo '</ul>';
        
        if (empty($workbooks_reference)) {
            throw new Exception("No Workbooks reference found for selected webinar. Please check the ACF fields.");
        }
        
        // Get current user info to show what will be fetched dynamically
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_first_name = $current_user->user_firstname ?: $current_user->display_name;
        $user_last_name = $current_user->user_lastname;
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        
        echo '<p><strong>Current User Info (will be fetched dynamically):</strong></p>';
        echo '<ul>';
        echo '<li>Email: ' . esc_html($user_email) . '</li>';
        echo '<li>First Name: ' . esc_html($user_first_name) . '</li>';
        echo '<li>Last Name: ' . esc_html($user_last_name) . '</li>';
        echo '<li>Person ID: ' . esc_html($person_id) . '</li>';
        echo '</ul>';
        
        // Load the webinar registration handler directly
        if (!function_exists('dtr_handle_live_webinar_registration')) {
            require_once DTR_WORKBOOKS_INCLUDES_DIR . 'form-handler-live-webinar-registration.php';
        }
        
        // Prepare registration data in the format expected by our updated handler
        $registration_data = [
            'post_id' => $selected_post_id,
            'speaker_question' => sanitize_text_field($_POST['speaker_question'] ?? 'Test question from Form ID 2'),
            'cf_mailing_list_member_sponsor_1_optin' => isset($_POST['sponsor_optin']) ? 1 : 0
        ];
        
        echo '<p><strong>Registration Data for Handler:</strong></p>';
        echo '<pre>' . esc_html(print_r($registration_data, true)) . '</pre>';
        
        // Call our updated webinar registration handler directly
        echo '<p><strong>🔄 Processing via Updated Handler...</strong></p>';
        
        $result = dtr_handle_live_webinar_registration($registration_data);
        
        echo '<p><strong>Processing Result:</strong></p>';
        if ($result && !empty($result['success'])) {
            echo '<div style="color: green; font-weight: bold;">✅ SUCCESS: Registration completed successfully!</div>';
            echo '<ul>';
            echo '<li><strong>Ticket ID:</strong> ' . esc_html($result['ticket_id'] ?? 'N/A') . '</li>';
            echo '<li><strong>Person ID:</strong> ' . esc_html($result['person_id'] ?? 'N/A') . '</li>';
            echo '<li><strong>Event ID:</strong> ' . esc_html($result['event_id'] ?? 'N/A') . '</li>';
            echo '</ul>';
        } elseif ($result === false) {
            echo '<div style="color: red; font-weight: bold;">❌ FAILED: Registration failed</div>';
        } else {
            echo '<div style="color: orange; font-weight: bold;">⚠️ UNKNOWN: Unexpected result</div>';
            echo '<pre>' . esc_html(print_r($result, true)) . '</pre>';
        }
        
    } catch (Exception $e) {
        echo '<div style="color: red; font-weight: bold;">❌ EXCEPTION: ' . esc_html($e->getMessage()) . '</div>';
        echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
    }
    
    echo '</div>';
}

// Get available webinars for dropdown
$available_webinars = get_available_webinars_for_test();

// Show test form
?>

<form method="post" style="background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; margin: 20px 0;">
    <?php wp_nonce_field('test_webinar_registration', '_wpnonce'); ?>
    <h3>📋 Test Webinar Registration with Dynamic Selection</h3>
    <p>Select a webinar and test the registration process with dynamically fetched post ID and Workbooks reference.</p>
    
    <p>
        <label for="webinar_selection"><strong>Select Webinar:</strong></label><br>
        <select id="webinar_selection" name="webinar_selection" style="width: 100%; max-width: 600px;" onchange="updateWebinarInfo()">
            <option value="">-- Choose a Webinar --</option>
            <?php foreach ($available_webinars as $webinar): ?>
                <option value="<?php echo esc_attr($webinar['id']); ?>" 
                        data-title="<?php echo esc_attr($webinar['title']); ?>"
                        data-reference="<?php echo esc_attr($webinar['workbooks_reference']); ?>"
                        data-type="<?php echo esc_attr($webinar['post_type']); ?>"
                        <?php selected($_POST['webinar_selection'] ?? 161189, $webinar['id']); ?>>
                    <?php echo esc_html($webinar['title']) . ' (ID: ' . $webinar['id'] . ')'; ?>
                    <?php if ($webinar['workbooks_reference']): ?>
                        - Reference: <?php echo esc_html($webinar['workbooks_reference']); ?>
                    <?php else: ?>
                        - <em>No Reference</em>
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    
    <div id="webinar-info" style="background: #e7f3ff; padding: 10px; border: 1px solid #b8d4ff; margin: 10px 0; display: none;">
        <h4>📋 Selected Webinar Details:</h4>
        <ul>
            <li><strong>Post ID:</strong> <span id="info-post-id">-</span></li>
            <li><strong>Title:</strong> <span id="info-title">-</span></li>
            <li><strong>Post Type:</strong> <span id="info-type">-</span></li>
            <li><strong>Workbooks Reference:</strong> <span id="info-reference">-</span></li>
        </ul>
    </div>
    
    <p>
        <label for="speaker_question"><strong>Speaker Question:</strong></label><br>
        <textarea id="speaker_question" name="speaker_question" rows="3" cols="60" placeholder="Enter a question for the speaker..."><?php echo esc_textarea($_POST['speaker_question'] ?? 'Test question from dynamic webinar selection'); ?></textarea>
    </p>
    
    <p>
        <label>
            <input type="checkbox" name="sponsor_optin" value="1" <?php checked(isset($_POST['sponsor_optin'])); ?>>
            <strong>Sponsor Optin:</strong> I agree to receive sponsor information
        </label>
    </p>
    
    <p><em>Note: User details (email, first name, last name, person ID) will be fetched dynamically from the current logged-in user.</em></p>
    
    <p><button type="submit" name="test_submission" class="button button-primary">🧪 Test Dynamic Webinar Registration</button></p>
</form>

<script>
function updateWebinarInfo() {
    const select = document.getElementById('webinar_selection');
    const infoDiv = document.getElementById('webinar-info');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        document.getElementById('info-post-id').textContent = selectedOption.value;
        document.getElementById('info-title').textContent = selectedOption.dataset.title || '-';
        document.getElementById('info-type').textContent = selectedOption.dataset.type || '-';
        document.getElementById('info-reference').textContent = selectedOption.dataset.reference || 'Not found';
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateWebinarInfo();
});
</script>

<div style="background: #e3f2fd; border: 1px solid #90caf9; padding: 15px; margin: 20px 0;">
    <h3>📊 Available Webinars</h3>
    <p>Found <strong><?php echo count($available_webinars); ?></strong> webinars with registration forms:</p>
    
    <?php if (empty($available_webinars)): ?>
        <p><em>No webinars found. Make sure you have posts with webinar_fields ACF fields configured.</em></p>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Post ID</th>
                    <th>Title</th>
                    <th>Post Type</th>
                    <th>Workbooks Reference</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($available_webinars as $webinar): ?>
                    <tr>
                        <td><?php echo esc_html($webinar['id']); ?></td>
                        <td><?php echo esc_html($webinar['title']); ?></td>
                        <td><?php echo esc_html($webinar['post_type']); ?></td>
                        <td>
                            <?php if ($webinar['workbooks_reference']): ?>
                                <code><?php echo esc_html($webinar['workbooks_reference']); ?></code>
                            <?php else: ?>
                                <em style="color: #d63638;">Not found</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date('Y-m-d', strtotime($webinar['date']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <h3>📊 Debug Log Location</h3>
    <p>Check the debug log for detailed processing information:</p>
    <code><?php echo esc_html(DTR_WORKBOOKS_LOG_DIR . 'live-webinar-registration-debug.log'); ?></code>
    
    <p><a href="<?php echo admin_url('admin.php?page=dtr-workbooks'); ?>" class="button">← Back to DTR Workbooks Dashboard</a></p>
</div>

<?php
echo '</div>';
