<?php
/**
 * Lead Generation Test Page
 * 
 * Admin interface for testing lead generation forms with dynamic publication selection
 */

// Check if user is logged in and is admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied. You must be an admin to view this page.');
}

echo '<div class="wrap plugin-admin-content">';
echo '<h1>Lead Generation Test</h1>';
echo '<h2>This page tests lead generation form submissions with dynamic publication selection and user data fetching.</h2>';


// Function to get available publications
if (!function_exists('get_available_publications_for_test')) {
function get_available_publications_for_test() {
    $publications = [];
    
    // Get publications post type
    $args = [
        'post_type' => 'publications',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ];
    
    $posts = get_posts($args);
    
    foreach ($posts as $post) {
        // Try to get workbooks reference from various ACF field possibilities
        $workbooks_reference = '';
        
        if (function_exists('get_field')) {
            // First check if it's within the restricted_content_fields group (based on your ACF structure)
            $restricted_content_fields = get_field('restricted_content_fields', $post->ID);
            if (is_array($restricted_content_fields) && !empty($restricted_content_fields['workbooks_reference'])) {
                $workbooks_reference = $restricted_content_fields['workbooks_reference'];
            }
            
            // Fallback to direct field lookups if not found in group
            if (empty($workbooks_reference)) {
                $workbooks_reference = get_field('workbooks_reference', $post->ID) 
                    ?: get_field('workbook_reference', $post->ID)
                    ?: get_field('reference', $post->ID)
                    ?: get_field('publication_reference', $post->ID);
            }
        }
        
        $publications[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'workbooks_reference' => $workbooks_reference,
            'post_type' => $post->post_type,
            'date' => $post->post_date
        ];
    }
    
    // If no publications found, also check for other post types that might have publication-related fields
    if (empty($publications)) {
        $fallback_args = [
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'workbooks_reference',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'publication_reference',
                    'compare' => 'EXISTS'
                ]
            ]
        ];
        
        $fallback_posts = get_posts($fallback_args);
        
        foreach ($fallback_posts as $post) {
            $workbooks_reference = '';
            
            if (function_exists('get_field')) {
                // First check if it's within the restricted_content_fields group
                $restricted_content_fields = get_field('restricted_content_fields', $post->ID);
                if (is_array($restricted_content_fields) && !empty($restricted_content_fields['workbooks_reference'])) {
                    $workbooks_reference = $restricted_content_fields['workbooks_reference'];
                }
                
                // Fallback to direct field lookups if not found in group
                if (empty($workbooks_reference)) {
                    $workbooks_reference = get_field('workbooks_reference', $post->ID) 
                        ?: get_field('workbook_reference', $post->ID)
                        ?: get_field('reference', $post->ID)
                        ?: get_field('publication_reference', $post->ID);
                }
            }
            
            $publications[] = [
                'id' => $post->ID,
                'title' => $post->post_title . ' (Fallback)',
                'workbooks_reference' => $workbooks_reference,
                'post_type' => $post->post_type,
                'date' => $post->post_date
            ];
        }
    }
    
    return $publications;
}
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_workbooks_details']) && check_admin_referer('test_publication_registration', '_wpnonce')) {
    echo '<div style="background: #e7f3ff; border: 1px solid #b8d4ff; padding: 15px; margin: 20px 0;">';
    echo '<h3>🔍 Workbooks Details Only</h3>';
    
    try {
        // Get selected publication details
        $selected_post_id = intval($_POST['publication_selection'] ?? 0);
        $selected_post = get_post($selected_post_id);
        
        if (!$selected_post) {
            throw new Exception("Selected publication post not found: $selected_post_id");
        }
        
        // Get workbooks reference for selected publication
        $workbooks_reference = '';
        
        if (function_exists('get_field')) {
            // First check if it's within the restricted_content_fields group (based on your ACF structure)
            $restricted_content_fields = get_field('restricted_content_fields', $selected_post_id);
            if (is_array($restricted_content_fields) && !empty($restricted_content_fields['workbooks_reference'])) {
                $workbooks_reference = $restricted_content_fields['workbooks_reference'];
            }
            
            // Fallback to direct field lookups if not found in group
            if (empty($workbooks_reference)) {
                $workbooks_reference = get_field('workbooks_reference', $selected_post_id) 
                    ?: get_field('workbook_reference', $selected_post_id)
                    ?: get_field('reference', $selected_post_id)
                    ?: get_field('publication_reference', $selected_post_id);
            }
        }
        
        echo '<p><strong>Selected Publication:</strong> ' . esc_html($selected_post->post_title) . ' (ID: ' . esc_html($selected_post_id) . ')</p>';
        echo '<p><strong>Workbooks Reference:</strong> ' . esc_html($workbooks_reference ?: 'Not found') . '</p>';
        
        if (!empty($workbooks_reference)) {
            // Get Workbooks instance
            $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
            if ($workbooks) {
                $event_id = preg_replace('/\D+/', '', $workbooks_reference);
                echo '<p><strong>Extracted Event ID:</strong> ' . esc_html($event_id) . '</p>';
                
                // Based on your reference, let's try the correct format
                echo '<div style="background: #f0f8ff; padding: 10px; border: 1px solid #d1ecf1; margin: 10px 0;">';
                echo '<h4>🔍 Debug: Trying correct event reference formats</h4>';
                
                $reference_formats_to_try = [
                    $event_id, // Just the number (5834)
                    'EVENT-' . $event_id, // EVENT-5834 format
                    $workbooks_reference // Original reference (5834)
                ];
                
                echo '<p><strong>Will try these reference formats:</strong></p>';
                echo '<ul>';
                foreach ($reference_formats_to_try as $ref) {
                    echo '<li>' . esc_html($ref) . '</li>';
                }
                echo '</ul>';
                
                $endpoints_to_try = [
                    'crm/campaigns.api' => 'CRM Campaigns'
                ];
                
                $found_event = false;
                
                foreach ($endpoints_to_try as $endpoint => $description) {
                    if ($found_event) break; // Stop if we already found it
                    
                    echo '<p><strong>Searching in ' . esc_html($description) . ':</strong></p>';
                    
                    foreach ($reference_formats_to_try as $ref_format) {
                        try {
                            echo '<p style="margin-left: 20px;">Trying reference: <code>' . esc_html($ref_format) . '</code></p>';
                            
                            // Try searching by ID first
                            $result = $workbooks->get($endpoint, [
                                '_start' => 0, '_limit' => 5,
                                '_ff[]' => 'id', '_ft[]' => 'eq', '_fc[]' => $ref_format,
                                '_select_columns[]' => ['id', 'name', 'status', 'reference', 'campaign_reference']
                            ], true);
                            
                            if (!empty($result['data'][0])) {
                                $event_data = $result['data'][0];
                                echo '<div style="background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; margin: 10px 0;">';
                                echo '<h4>✅ Found Campaign by ID in ' . esc_html($description) . '</h4>';
                                echo '<ul>';
                                foreach ($event_data as $key => $value) {
                                    echo '<li><strong>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                                $found_event = true;
                                break;
                            }
                            
                            // If not found by ID, try by reference field
                            if (!$found_event) {
                                $result = $workbooks->get($endpoint, [
                                    '_start' => 0, '_limit' => 5,
                                    '_ff[]' => 'reference', '_ft[]' => 'eq', '_fc[]' => $ref_format,
                                    '_select_columns[]' => ['id', 'name', 'status', 'reference', 'campaign_reference']
                                ], true);
                                
                                if (!empty($result['data'][0])) {
                                    $event_data = $result['data'][0];
                                    echo '<div style="background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; margin: 10px 0;">';
                                    echo '<h4>✅ Found Campaign by Reference in ' . esc_html($description) . '</h4>';
                                    echo '<ul>';
                                    foreach ($event_data as $key => $value) {
                                        echo '<li><strong>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</li>';
                                    }
                                    echo '</ul>';
                                    echo '</div>';
                                    $found_event = true;
                                    break;
                                }
                            }
                            
                        } catch (Exception $e) {
                            echo '<p style="color: #721c24; margin-left: 20px;">Error with reference ' . esc_html($ref_format) . ': ' . esc_html($e->getMessage()) . '</p>';
                            // Continue to next reference format instead of breaking
                        }
                    }
                }
                
                // Let's also try to get a general list of campaigns to see what's available
                if (!$found_event) {
                    try {
                        echo '<h4>🔍 Let\'s see what campaigns exist</h4>';
                        $all_campaigns = $workbooks->get('crm/campaigns.api', [
                            '_start' => 0, '_limit' => 20,
                            '_select_columns[]' => ['id', 'name', 'reference', 'status']
                        ], true);
                        
                        if (!empty($all_campaigns['data'])) {
                            echo '<p>Found ' . count($all_campaigns['data']) . ' campaigns in system:</p>';
                            echo '<table style="border-collapse: collapse; width: 100%;">';
                            echo '<tr><th style="border: 1px solid #ddd; padding: 8px;">ID</th><th style="border: 1px solid #ddd; padding: 8px;">Name</th><th style="border: 1px solid #ddd; padding: 8px;">Reference</th><th style="border: 1px solid #ddd; padding: 8px;">Status</th></tr>';
                            foreach ($all_campaigns['data'] as $campaign) {
                                echo '<tr>';
                                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($campaign['id']) . '</td>';
                                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($campaign['name']) . '</td>';
                                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($campaign['reference'] ?? 'N/A') . '</td>';
                                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($campaign['status']) . '</td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                            
                            echo '<div style="background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; margin: 15px 0;">';
                            echo '<h4>🎯 Quick Fix Options</h4>';
                            echo '<p><strong>Option 1:</strong> Update the ACF field to use one of the existing campaign IDs above (like 85569 for the Active campaign)</p>';
                            echo '<p><strong>Option 2:</strong> Create a new campaign in Workbooks with ID 5834</p>';
                            echo '<p><strong>Option 3:</strong> Skip Workbooks integration for now and focus on other functionality</p>';
                            echo '<p><em>The lead generation system will work fine without Workbooks - it just won\'t sync the leads to the CRM.</em></p>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<p style="color: #721c24;">Could not fetch campaigns list: ' . esc_html($e->getMessage()) . '</p>';
                    }
                }
                
                if (!$found_event) {
                    echo '<div style="background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;">';
                    echo '<p><strong>❌ Campaign Not Found:</strong> Could not find campaign with any of the tried references.</p>';
                    echo '<p><strong>Suggestion:</strong> Check the table above to see what campaigns exist and verify the reference format.</p>';
                    echo '</div>';
                }
                
                echo '</div>';
                
            } else {
                echo '<div style="background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;">';
                echo '<p><strong>❌ Error:</strong> Workbooks instance not available</p>';
                echo '</div>';
            }
        }
        
    } catch (Exception $e) {
        echo '<div style="background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;">';
        echo '<p><strong>❌ Exception:</strong> ' . esc_html($e->getMessage()) . '</p>';
        echo '</div>';
    }
    
    echo '</div>';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_submission']) && check_admin_referer('test_publication_registration', '_wpnonce')) {
    echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0;">';
    echo '    <h3>🧪 Testing Publication Lead Generation</h3>';
    
    try {
        // Get selected publication details
        $selected_post_id = intval($_POST['publication_selection'] ?? 0);
        $selected_post = get_post($selected_post_id);
        
        if (!$selected_post) {
            throw new Exception("Selected publication post not found: $selected_post_id");
        }
        
        // Get workbooks reference for selected publication
        $workbooks_reference = '';
        
        if (function_exists('get_field')) {
            // First check if it's within the restricted_content_fields group (based on your ACF structure)
            $restricted_content_fields = get_field('restricted_content_fields', $selected_post_id);
            if (is_array($restricted_content_fields) && !empty($restricted_content_fields['workbooks_reference'])) {
                $workbooks_reference = $restricted_content_fields['workbooks_reference'];
            }
            
            // Fallback to direct field lookups if not found in group
            if (empty($workbooks_reference)) {
                $workbooks_reference = get_field('workbooks_reference', $selected_post_id) 
                    ?: get_field('workbook_reference', $selected_post_id)
                    ?: get_field('reference', $selected_post_id)
                    ?: get_field('publication_reference', $selected_post_id);
            }
        }
        
        echo '<p><strong>Selected Publication Details:</strong></p>';
        echo '<ul>';
        echo '<li><strong>Post ID:</strong> ' . esc_html($selected_post_id) . '</li>';
        echo '<li><strong>Title:</strong> ' . esc_html($selected_post->post_title) . '</li>';
        echo '<li><strong>Post Type:</strong> ' . esc_html($selected_post->post_type) . '</li>';
        echo '<li><strong>Workbooks Reference:</strong> ' . esc_html($workbooks_reference ?: 'Not found') . '</li>';
        echo '</ul>';
        
        // Add Workbooks API inspection
        if (!empty($workbooks_reference)) {
            echo '<h4>🔍 Workbooks API Inspection</h4>';
            echo '<p>Fetching event details from Workbooks API for reference: <code>' . esc_html($workbooks_reference) . '</code></p>';
            
            try {
                // Get Workbooks instance
                $workbooks = function_exists('get_workbooks_instance') ? get_workbooks_instance() : null;
                if ($workbooks) {
                    $event_id = preg_replace('/\D+/', '', $workbooks_reference);
                    echo '<p><strong>Extracted Event ID:</strong> ' . esc_html($event_id) . '</p>';
                    
                    // Try to get event details
                    try {
                        $event_result = $workbooks->assertGet('automation/events.api', [
                            '_start' => 0, '_limit' => 1,
                            '_ff[]' => 'id', '_ft[]' => 'eq', '_fc[]' => $event_id,
                            '_select_columns[]' => ['id', 'name', 'status', 'start_date', 'end_date', 'description']
                        ]);
                        
                        if (!empty($event_result['data'][0])) {
                            $event_data = $event_result['data'][0];
                            echo '<div style="background: #e7f3ff; padding: 10px; border: 1px solid #b8d4ff; margin: 10px 0;">';
                            echo '<h5>📋 Workbooks Event Details:</h5>';
                            echo '<ul>';
                            foreach ($event_data as $key => $value) {
                                echo '<li><strong>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</li>';
                            }
                            echo '</ul>';
                            echo '</div>';
                        } else {
                            echo '<div style="background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; margin: 10px 0;">';
                            echo '<p><strong>⚠️ Warning:</strong> No event found with ID ' . esc_html($event_id) . '</p>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div style="background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;">';
                        echo '<p><strong>❌ Error fetching event:</strong> ' . esc_html($e->getMessage()) . '</p>';
                        echo '</div>';
                    }
                    
                    // Also try to get lead template info
                    try {
                        $lead_result = $workbooks->assertGet('crm/leads.api', [
                            '_start' => 0, '_limit' => 5,
                            '_ff[]' => 'lead_id', '_ft[]' => 'eq', '_fc[]' => $event_id,
                            '_select_columns[]' => ['id', 'name', 'status', 'lead_id', 'person_id']
                        ]);
                        
                        echo '<div style="background: #f0f8ff; padding: 10px; border: 1px solid #d1ecf1; margin: 10px 0;">';
                        echo '<h5>📋 Existing Leads for this Event:</h5>';
                        if (!empty($lead_result['data'])) {
                            echo '<p>Found ' . count($lead_result['data']) . ' existing leads:</p>';
                            echo '<ul>';
                            foreach ($lead_result['data'] as $lead) {
                                echo '<li>ID: ' . esc_html($lead['id']) . ' - ' . esc_html($lead['name']) . ' (Status: ' . esc_html($lead['status']) . ')</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p>No existing leads found for this event.</p>';
                        }
                        echo '</div>';
                    } catch (Exception $e) {
                        echo '<div style="background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; margin: 10px 0;">';
                        echo '<p><strong>ℹ️ Lead search note:</strong> ' . esc_html($e->getMessage()) . '</p>';
                        echo '</div>';
                    }
                    
                } else {
                    echo '<div style="background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;">';
                    echo '<p><strong>❌ Error:</strong> Workbooks instance not available</p>';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div style="background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;">';
                echo '<p><strong>❌ Workbooks API Error:</strong> ' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        
        if (empty($workbooks_reference)) {
            throw new Exception("No Workbooks reference found for selected publication. Please check the ACF fields.");
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
        
        // Load the lead generation handler instead of webinar handler
        if (!function_exists('dtr_handle_lead_generation_registration')) {
            require_once DTR_WORKBOOKS_INCLUDES_DIR . 'form-handler-lead-generation-registration.php';
        }
        
        // Prepare lead generation data in the format expected by our handler
        $lead_data = [
            'post_id' => $selected_post_id,
            'email' => $user_email,
            'first_name' => $user_first_name,
            'last_name' => $user_last_name,
            'lead_question' => sanitize_text_field($_POST['publication_interest'] ?? 'General interest in publication'),
            'cf_mailing_list_member_sponsor_1_optin' => isset($_POST['marketing_optin']) ? 1 : 0
        ];
        
        echo '<p><strong>Lead Generation Data for Handler:</strong></p>';
        echo '<pre>' . esc_html(print_r($lead_data, true)) . '</pre>';
        
        // Call our lead generation handler directly
        echo '<p><strong>🔄 Processing via Lead Generation Handler...</strong></p>';
        
        $result = dtr_handle_lead_generation_registration($lead_data);
        
        echo '<p><strong>Processing Result:</strong></p>';
        if ($result && !empty($result['success'])) {
            echo '<div style="color: green; font-weight: bold;">✅ SUCCESS: Lead generation completed successfully!</div>';
            echo '<ul>';
            echo '<li><strong>Person ID:</strong> ' . esc_html($result['person_id'] ?? 'N/A') . '</li>';
            echo '<li><strong>Lead ID:</strong> ' . esc_html($result['lead_id'] ?? 'N/A') . '</li>';
            echo '<li><strong>Debug ID:</strong> ' . esc_html($result['debug_id'] ?? 'N/A') . '</li>';
            echo '</ul>';
        } elseif ($result === false) {
            echo '<div style="color: red; font-weight: bold;">❌ FAILED: Lead generation failed</div>';
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

// Get available publications for dropdown
$available_publications = get_available_publications_for_test();

// Show test form
?>

<form method="post" style="background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; margin: 20px 0;">
    <?php wp_nonce_field('test_publication_registration', '_wpnonce'); ?>
    <h3>📋 Test Publication Lead Generation with Dynamic Selection</h3>
    <p>Select a publication and test the lead generation process with dynamically fetched post ID and Workbooks reference.</p>
    
    <p>
        <label for="publication_selection"><strong>Select Publication:</strong></label><br>
        <select id="publication_selection" name="publication_selection" style="width: 100%; max-width: 600px;" onchange="updatePublicationInfo()">
            <option value="">-- Choose a Publication --</option>
            <?php foreach ($available_publications as $publication): ?>
                <option value="<?php echo esc_attr($publication['id']); ?>" 
                        data-title="<?php echo esc_attr($publication['title']); ?>"
                        data-reference="<?php echo esc_attr($publication['workbooks_reference']); ?>"
                        data-type="<?php echo esc_attr($publication['post_type']); ?>"
                        <?php selected($_POST['publication_selection'] ?? '', $publication['id']); ?>>
                    <?php echo esc_html($publication['title']) . ' (ID: ' . $publication['id'] . ')'; ?>
                    <?php if ($publication['workbooks_reference']): ?>
                        - Reference: <?php echo esc_html($publication['workbooks_reference']); ?>
                    <?php else: ?>
                        - <em>No Reference</em>
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    
    <div id="publication-info" style="background: #e7f3ff; padding: 10px; border: 1px solid #b8d4ff; margin: 10px 0; display: none;">
        <h4>📋 Selected Publication Details:</h4>
        <ul>
            <li><strong>Post ID:</strong> <span id="info-post-id">-</span></li>
            <li><strong>Title:</strong> <span id="info-title">-</span></li>
            <li><strong>Post Type:</strong> <span id="info-type">-</span></li>
            <li><strong>Workbooks Reference:</strong> <span id="info-reference">-</span></li>
        </ul>
    </div>
    
    <p>
        <label for="publication_interest"><strong>Interest/Comments:</strong></label><br>
        <textarea id="publication_interest" name="publication_interest" rows="3" cols="60" placeholder="Why are you interested in this publication?"><?php echo esc_textarea($_POST['publication_interest'] ?? 'General interest in this publication content'); ?></textarea>
    </p>
    
    <p>
        <label>
            <input type="checkbox" name="marketing_optin" value="1" <?php checked(isset($_POST['marketing_optin'])); ?>>
            <strong>Marketing Optin:</strong> I agree to receive marketing communications about publications
        </label>
    </p>
    
    <p><em>Note: User details (email, first name, last name, person ID) will be fetched dynamically from the current logged-in user.</em></p>
    
    <p>
        <button type="submit" name="fetch_workbooks_details" class="button button-secondary" style="margin-right: 10px;">🔍 Fetch Workbooks Details</button>
        <button type="submit" name="test_submission" class="button button-primary">🧪 Test Dynamic Publication Lead Generation</button>
    </p>
</form>

<script>
function updatePublicationInfo() {
    const select = document.getElementById('publication_selection');
    const infoDiv = document.getElementById('publication-info');
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
    updatePublicationInfo();
});
</script>

<div style="background: #e3f2fd; border: 1px solid #90caf9; padding: 15px; margin: 20px 0;">
    <h3>📊 Available Publications</h3>
    <p>Found <strong><?php echo count($available_publications); ?></strong> publications with potential lead generation capability:</p>
    
    <?php if (empty($available_publications)): ?>
        <p><em>No publications found. Make sure you have posts with the 'publications' post type or other posts with workbooks_reference ACF fields configured.</em></p>
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
                <?php foreach ($available_publications as $publication): ?>
                    <tr>
                        <td><?php echo esc_html($publication['id']); ?></td>
                        <td><?php echo esc_html($publication['title']); ?></td>
                        <td><?php echo esc_html($publication['post_type']); ?></td>
                        <td>
                            <?php if ($publication['workbooks_reference']): ?>
                                <code><?php echo esc_html($publication['workbooks_reference']); ?></code>
                            <?php else: ?>
                                <em style="color: #d63638;">Not found</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date('Y-m-d', strtotime($publication['date']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <h3>📊 Debug Log Location</h3>
    <p>Check the debug log for detailed processing information:</p>
    <code><?php echo esc_html(DTR_WORKBOOKS_LOG_DIR . 'lead-generation-debug.log'); ?></code>
    
    <p><a href="<?php echo admin_url('admin.php?page=dtr-workbooks'); ?>" class="button">← Back to DTR Workbooks Dashboard</a></p>
</div>

<?php
echo '</div>';
