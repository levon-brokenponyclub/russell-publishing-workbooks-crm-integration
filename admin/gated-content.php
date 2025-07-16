<?php
if (!defined('ABSPATH')) exit;

// Get counts for each post type
$post_types = ['articles', 'whitepapers', 'news', 'events'];
$content_stats = [];

foreach ($post_types as $post_type) {
    $total_posts = wp_count_posts($post_type);
    $total_published = isset($total_posts->publish) ? $total_posts->publish : 0;
    
    // Count gated posts (posts with gated content meta)
    $gated_posts = get_posts(array(
        'post_type' => $post_type,
        'meta_query' => array(
            array(
                'key' => 'gated_content_settings',
                'compare' => 'EXISTS'
            )
        ),
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    $content_stats[$post_type] = array(
        'total' => $total_published,
        'gated' => count($gated_posts),
        'open' => $total_published - count($gated_posts)
    );
}
?>

<h2>Gated Content Management Overview</h2>
<p>Manage access control for your content. Configure which content requires user authentication and specific interests.</p>

<div class="gated-content-overview">
    <div class="stats-grid">
        <?php foreach ($post_types as $post_type): ?>
            <div class="stats-card">
                <h3><?php echo ucfirst($post_type); ?></h3>
                <div class="stats-numbers">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $content_stats[$post_type]['total']; ?></span>
                        <span class="stat-label">Total Published</span>
                    </div>
                    <div class="stat-item gated">
                        <span class="stat-number"><?php echo $content_stats[$post_type]['gated']; ?></span>
                        <span class="stat-label">Gated</span>
                    </div>
                    <div class="stat-item open">
                        <span class="stat-number"><?php echo $content_stats[$post_type]['open']; ?></span>
                        <span class="stat-label">Open Access</span>
                    </div>
                </div>
                <div class="stats-actions">
                    <a href="#" class="button button-secondary manage-type-btn" data-type="<?php echo $post_type; ?>">
                        Manage <?php echo ucfirst($post_type); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <button type="button" class="button button-primary" id="view-all-gated">View All Gated Content</button>
            <button type="button" class="button button-secondary" id="access-settings">Global Access Settings</button>
            <button type="button" class="button button-secondary" id="export-settings">Export Gated Settings</button>
        </div>
    </div>

    <div class="recent-activity" id="recent-gated-activity">
        <h3>Recently Configured Gated Content</h3>
        <div class="activity-list">
            <?php
            // Get recently modified gated content
            $recent_gated = get_posts(array(
                'post_type' => $post_types,
                'meta_query' => array(
                    array(
                        'key' => 'gated_content_settings',
                        'compare' => 'EXISTS'
                    )
                ),
                'posts_per_page' => 10,
                'orderby' => 'modified',
                'order' => 'DESC'
            ));

            if (!empty($recent_gated)):
                foreach ($recent_gated as $post):
                    $gated_settings = get_post_meta($post->ID, 'gated_content_settings', true);
                    $required_toi = isset($gated_settings['required_toi']) ? $gated_settings['required_toi'] : [];
                    $required_aoi = isset($gated_settings['required_aoi']) ? $gated_settings['required_aoi'] : [];
            ?>
                <div class="activity-item">
                    <div class="activity-content">
                        <strong><?php echo esc_html($post->post_title); ?></strong>
                        <span class="post-type-badge"><?php echo ucfirst($post->post_type); ?></span>
                        <div class="activity-meta">
                            <span>Modified: <?php echo get_the_modified_date('M j, Y', $post); ?></span>
                            <?php if (!empty($required_toi)): ?>
                                <span class="requirements">TOI: <?php echo count($required_toi); ?> required</span>
                            <?php endif; ?>
                            <?php if (!empty($required_aoi)): ?>
                                <span class="requirements">AOI: <?php echo count($required_aoi); ?> required</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="activity-actions">
                        <a href="#" class="button button-small edit-gated-btn" 
                           data-type="<?php echo $post->post_type; ?>" 
                           data-id="<?php echo $post->ID; ?>">Edit</a>
                    </div>
                </div>
            <?php 
                endforeach;
            else:
            ?>
                <p class="no-activity">No gated content configured yet. Use the buttons above to start managing access control.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Content Management Sections - Unified for All Content Types -->
<div class="gated-content-unified-management" style="margin-top: 30px;">
    <h3>Manage All Content Types</h3>
    <p>Configure gated content settings for Articles, Whitepapers, News, and Events. All content types use the same access control system based on Topics of Interest (TOI) and Areas of Interest (AOI).</p>
    
    <div class="content-type-tabs" style="margin-top: 20px;">
        <div class="nav-tab-wrapper" style="border-bottom: 1px solid #ccd0d4; margin-bottom: 20px;">
            <?php foreach ($post_types as $index => $post_type): ?>
                <a href="#" class="nav-tab <?php echo $index === 0 ? 'nav-tab-active' : ''; ?>" data-content-type="<?php echo $post_type; ?>" id="tab-<?php echo $post_type; ?>">
                    <?php echo ucfirst($post_type); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php foreach ($post_types as $index => $post_type): ?>
            <div class="content-type-panel <?php echo $index === 0 ? 'active' : ''; ?>" id="panel-<?php echo $post_type; ?>">
                <div class="gated-content-wrapper">
                    <h4><?php echo ucfirst($post_type); ?> Gated Content Management</h4>
                    
                    <form id="<?php echo $post_type; ?>-gated-form" class="gated-content-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo $post_type; ?>-search">Search <?php echo ucfirst($post_type); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="<?php echo $post_type; ?>-search" class="regular-text content-search" placeholder="Type to search <?php echo $post_type; ?>..." data-post-type="<?php echo $post_type; ?>">
                                    <p class="description">Start typing to filter <?php echo $post_type; ?> by title</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo $post_type; ?>-post-select">Select <?php echo ucfirst(substr($post_type, 0, -1)); ?></label>
                                </th>
                                <td>
                                    <select id="<?php echo $post_type; ?>-post-select" name="post_id" class="post-select" data-post-type="<?php echo $post_type; ?>">
                                        <option value="">-- Select <?php echo ucfirst(substr($post_type, 0, -1)); ?> --</option>
                                        <?php
                                        $posts = get_posts(array(
                                            'post_type' => $post_type,
                                            'posts_per_page' => -1,
                                            'post_status' => 'publish',
                                            'orderby' => 'title',
                                            'order' => 'ASC'
                                        ));
                                        foreach ($posts as $post) {
                                            echo '<option value="' . esc_attr($post->ID) . '" data-title="' . esc_attr(strtolower($post->post_title)) . '">' . esc_html($post->post_title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <p class="description">Choose the <?php echo substr($post_type, 0, -1); ?> you want to gate</p>
                                    <p class="search-results" style="display: none; color: #666; font-style: italic;">Showing filtered results</p>
                                </td>
                            </tr>
                        </table>

                        <div id="<?php echo $post_type; ?>-content-config" class="content-config" style="display: none;">
                            <h4>Content Configuration</h4>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo $post_type; ?>-post-title">Post Title</label>
                                    </th>
                                    <td>
                                        <input type="text" id="<?php echo $post_type; ?>-post-title" name="post_title" class="regular-text" readonly>
                                        <p class="description">Current post title (read-only)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo $post_type; ?>-preview-text">Preview Text</label>
                                    </th>
                                    <td>
                                        <textarea id="<?php echo $post_type; ?>-preview-text" name="preview_text" rows="5" cols="50" class="large-text" placeholder="Enter the preview text that will be shown to non-subscribers..."></textarea>
                                        <p class="description">This text will be displayed instead of the full content for users without access</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label>Required Topics of Interest (TOI)</label>
                                    </th>
                                    <td>
                                        <fieldset class="access-control-section">
                                            <legend>Topics of Interest</legend>
                                            <p class="description">Users must have selected at least one of these topics to access the full content:</p>
                                            <div class="access-control-checkboxes">
                                                <?php
                                                $toi_options = function_exists('dtr_get_all_toi_options') ? dtr_get_all_toi_options() : [];
                                                if (empty($toi_options)) {
                                                    // Fallback TOI options if function doesn't exist
                                                    $toi_options = [
                                                        'cf_person_business' => 'Business',
                                                        'cf_person_diseases' => 'Diseases',
                                                        'cf_person_drugs_therapies' => 'Drugs & Therapies',
                                                        'cf_person_genomics_3774' => 'Genomics',
                                                        'cf_person_research_development' => 'Research & Development',
                                                        'cf_person_technology' => 'Technology',
                                                        'cf_person_tools_techniques' => 'Tools & Techniques'
                                                    ];
                                                }
                                                foreach ($toi_options as $toi_key => $toi_label) {
                                                    echo '<label>';
                                                    echo '<input type="checkbox" name="required_toi[]" value="' . esc_attr($toi_key) . '" class="' . $post_type . '-toi-checkbox">';
                                                    echo '<span>' . esc_html($toi_label) . '</span>';
                                                    echo '</label>';
                                                }
                                                ?>
                                            </div>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label>Required Areas of Interest (AOI)</label>
                                    </th>
                                    <td>
                                        <fieldset class="access-control-section">
                                            <legend>Areas of Interest</legend>
                                            <p class="description">Users must have selected at least one of these areas to access the full content:</p>
                                            <div class="access-control-checkboxes">
                                                <?php
                                                $aoi_options = function_exists('dtr_get_aoi_field_names') ? dtr_get_aoi_field_names() : [];
                                                if (empty($aoi_options)) {
                                                    // Fallback AOI options if function doesn't exist
                                                    $aoi_options = [
                                                        'cf_person_dtr_news' => 'DTR News',
                                                        'cf_person_dtr_events' => 'DTR Events',
                                                        'cf_person_dtr_third_party' => 'DTR Third Party',
                                                        'cf_person_dtr_webinar' => 'DTR Webinar'
                                                    ];
                                                }
                                                foreach ($aoi_options as $aoi_key => $aoi_label) {
                                                    echo '<label>';
                                                    echo '<input type="checkbox" name="required_aoi[]" value="' . esc_attr($aoi_key) . '" class="' . $post_type . '-aoi-checkbox">';
                                                    echo '<span>' . esc_html($aoi_label) . '</span>';
                                                    echo '</label>';
                                                }
                                                ?>
                                            </div>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo $post_type; ?>-ninja-form">Access Request Form</label>
                                    </th>
                                    <td>
                                        <select id="<?php echo $post_type; ?>-ninja-form" name="ninja_form_id">
                                            <option value="">-- Select a Form --</option>
                                            <?php 
                                            // Get all Ninja Forms for the dropdown
                                            $ninja_forms = array();
                                            if (function_exists('ninja_forms_get_all_forms')) {
                                                $ninja_forms = ninja_forms_get_all_forms();
                                            } else {
                                                // Fallback method to get Ninja Forms
                                                $forms = get_posts(array(
                                                    'post_type' => 'nf_sub',
                                                    'posts_per_page' => -1,
                                                    'post_status' => 'publish'
                                                ));
                                                foreach ($forms as $form) {
                                                    $ninja_forms[] = array('id' => $form->ID, 'title' => $form->post_title);
                                                }
                                            }
                                            
                                            // If still no forms found, create a default entry
                                            if (empty($ninja_forms)) {
                                                $ninja_forms = array(
                                                    array('id' => 24, 'title' => 'Default Registration Form')
                                                );
                                            }
                                            
                                            foreach ($ninja_forms as $form): ?>
                                                <option value="<?php echo esc_attr($form['id']); ?>">
                                                    <?php echo esc_html($form['title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Form that will be displayed for users to request access</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary">Save Gated Content Settings</button>
                                <button type="button" class="button button-secondary clear-settings">Clear Settings</button>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
/* Gated Content Overview Styles */
.gated-content-overview {
    margin: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stats-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s ease;
}

.stats-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.stats-card h3 {
    margin: 0 0 15px 0;
    color: #23282d;
    font-size: 18px;
    font-weight: 600;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 8px;
}

.stats-numbers {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-number {
    display: block;
    font-size: 28px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-item .stat-number {
    color: #666;
}

.stat-item.gated .stat-number {
    color: #d63638;
}

.stat-item.open .stat-number {
    color: #00a32a;
}

.stat-label {
    display: block;
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.stats-actions {
    text-align: center;
}

.stats-actions .button {
    width: 100%;
    text-align: center;
}

.quick-actions {
    background: #f8f9fa;
    border: 1px solid #e1e4e7;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
}

.quick-actions h3 {
    margin: 0 0 15px 0;
    color: #23282d;
    font-size: 16px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons .button {
    flex: 1;
    min-width: 200px;
    text-align: center;
}

.recent-activity {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 30px;
}

.recent-activity h3 {
    margin: 0 0 15px 0;
    color: #23282d;
    font-size: 16px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-content {
    flex: 1;
}

.activity-content strong {
    display: block;
    margin-bottom: 5px;
    color: #23282d;
}

.post-type-badge {
    display: inline-block;
    background: #0073aa;
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 500;
    margin-left: 10px;
}

.activity-meta {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.activity-meta span {
    margin-right: 15px;
}

.requirements {
    background: #f0f6fc;
    padding: 2px 6px;
    border-radius: 3px;
    border: 1px solid #0073aa;
    color: #0073aa;
    font-weight: 500;
}

.activity-actions {
    flex-shrink: 0;
}

.no-activity {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}

/* Content Type Tabs */
.content-type-tabs .nav-tab-wrapper {
    background: #f1f1f1;
    padding: 0;
    margin-bottom: 0;
}

.content-type-tabs .nav-tab {
    background: #e0e0e0;
    border: 1px solid #ccd0d4;
    margin-right: 5px;
    padding: 10px 20px;
    border-radius: 4px 4px 0 0;
    transition: all 0.2s ease;
}

.content-type-tabs .nav-tab.nav-tab-active,
.content-type-tabs .nav-tab:hover {
    background: #fff;
    border-bottom: 1px solid #fff;
    color: #0073aa;
}

.content-type-panel {
    display: none;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
}

.content-type-panel.active {
    display: block;
}

.gated-content-wrapper {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
}

.content-config {
    background: #f9f9f9;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: 20px;
}

.content-config h4 {
    margin-top: 0;
    color: #23282d;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.post-select {
    min-width: 300px;
}

.content-search {
    min-width: 300px;
}

.search-results {
    margin-top: 5px;
    font-size: 0.9em;
}

.no-results {
    color: #dc3232;
    font-weight: bold;
}

.gated-content-form .submit {
    border-top: 1px solid #ddd;
    padding-top: 15px;
    margin-top: 20px;
}

/* AOI/TOI Access Control Styling */
fieldset {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background: #f9f9f9;
}

fieldset legend {
    font-weight: 600;
    padding: 0 10px;
    color: #23282d;
}

.access-control-section {
    margin: 15px 0;
}

.access-control-section .description {
    margin-bottom: 10px;
    font-style: italic;
    color: #666;
}

.access-control-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.access-control-checkboxes label {
    display: flex;
    align-items: center;
    padding: 5px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    margin-bottom: 5px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.access-control-checkboxes label:hover {
    background: #f0f8ff;
    border-color: #0073aa;
}

.access-control-checkboxes input[type="checkbox"] {
    margin-right: 8px;
}

.access-control-checkboxes input[type="checkbox"]:checked + span {
    font-weight: 600;
    color: #0073aa;
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('Gated Content: jQuery loaded and ready');
    console.log('AJAX URL:', ajaxurl);
    
    // Store original options for search functionality for each content type
    var originalOptions = {};
    $('.post-select').each(function() {
        var postType = $(this).data('post-type');
        originalOptions[postType] = $(this).find('option').clone();
    });
    
    // Content type tab switching
    $('.content-type-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var contentType = $(this).data('content-type');
        
        // Update tab states
        $('.content-type-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Update panel visibility
        $('.content-type-panel').removeClass('active');
        $('#panel-' + contentType).addClass('active');
        
        console.log('Switched to content type:', contentType);
    });
    
    // Search functionality for each post type
    $('.content-search').on('input', function() {
        var searchTerm = $(this).val().toLowerCase().trim();
        var postType = $(this).data('post-type');
        var $select = $('#' + postType + '-post-select');
        var $resultsText = $select.siblings('.search-results');
        
        console.log('Searching for:', searchTerm, 'in', postType);
        
        // Clear current selection
        $select.val('');
        $('#' + postType + '-content-config').slideUp();
        
        if (searchTerm === '') {
            // Show all options if search is empty
            $select.empty().append(originalOptions[postType].clone());
            $resultsText.hide();
        } else {
            // Filter options based on search term
            var filteredOptions = originalOptions[postType].filter(function() {
                var title = $(this).data('title') || '';
                return title.includes(searchTerm) || $(this).val() === '';
            });
            
            $select.empty().append(filteredOptions.clone());
            
            // Show results count
            var resultCount = filteredOptions.length - 1; // Subtract 1 for the default option
            if (resultCount > 0) {
                $resultsText.text('Showing ' + resultCount + ' filtered result(s)').removeClass('no-results').show();
            } else {
                $resultsText.text('No ' + postType + ' found matching "' + searchTerm + '"').addClass('no-results').show();
            }
        }
    });
    
    // Clear search when a post is selected
    $('.post-select').on('change', function() {
        var postType = $(this).data('post-type');
        var searchInput = $('#' + postType + '-search');
        
        console.log('Post selected:', $(this).val(), 'Type:', postType);
        
        if ($(this).val()) {
            searchInput.val('');
            searchInput.siblings('.search-results').hide();
            // Restore all options
            $(this).empty().append(originalOptions[postType].clone());
            $(this).val($(this).find('option:selected').val());
        }
    });
    
    // Post selection functionality
    $('.post-select').on('change', function() {
        var postId = $(this).val();
        var postType = $(this).data('post-type');
        var $config = $('#' + postType + '-content-config');
        var $titleField = $('#' + postType + '-post-title');
        
        console.log('Post selected:', postId, 'Type:', postType);
        
        if (postId) {
            // Get the selected post title
            var postTitle = $(this).find('option:selected').text();
            $titleField.val(postTitle);
            
            // Load existing settings if any
            loadGatedContentSettings(postId, postType);
            
            // Show configuration section
            $config.slideDown();
        } else {
            // Hide configuration section
            $config.slideUp();
        }
    });

    // Form submission
    $('.gated-content-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        
        var $form = $(this);
        var postId = $form.find('.post-select').val();
        var postType = $form.find('.post-select').data('post-type');
        
        if (!postId) {
            alert('Please select a post first.');
            return;
        }
        
        console.log('Submitting for post:', postId, 'type:', postType);
        
        // Collect selected TOI and AOI values
        var selectedToi = [];
        $form.find('.' + postType + '-toi-checkbox:checked').each(function() {
            selectedToi.push($(this).val());
        });
        
        var selectedAoi = [];
        $form.find('.' + postType + '-aoi-checkbox:checked').each(function() {
            selectedAoi.push($(this).val());
        });
        
        console.log('Selected TOI:', selectedToi);
        console.log('Selected AOI:', selectedAoi);
        
        var formData = {
            action: 'save_gated_content_settings',
            nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
            post_id: postId,
            post_type: postType,
            preview_text: $form.find('[name="preview_text"]').val(),
            ninja_form_id: $form.find('[name="ninja_form_id"]').val(),
            required_toi: selectedToi,
            required_aoi: selectedAoi
        };
        
        console.log('Sending form data:', formData);
        
        $.post(ajaxurl, formData, function(response) {
            console.log('Response received:', response);
            if (response.success) {
                alert('Gated content settings saved successfully!');
            } else {
                alert('Error saving settings: ' + (response.data || 'Unknown error'));
                console.error('Save error:', response);
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX request failed:', status, error);
            alert('Network error occurred. Please try again.');
        });
    });

    // Clear settings functionality
    $('.clear-settings').on('click', function() {
        if (confirm('Are you sure you want to clear all settings for this content?')) {
            var $form = $(this).closest('.gated-content-form');
            var postId = $form.find('.post-select').val();
            var postType = $form.find('.post-select').data('post-type');
            
            if (!postId) {
                alert('Please select a post first.');
                return;
            }
            
            var formData = {
                action: 'clear_gated_content_settings',
                nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
                post_id: postId
            };
            
            $.post(ajaxurl, formData, function(response) {
                if (response.success) {
                    // Clear form fields
                    $form.find('[name="preview_text"]').val('');
                    $form.find('[name="ninja_form_id"]').val('');
                    // Clear AOI/TOI checkboxes for this specific post type
                    $form.find('.' + postType + '-toi-checkbox, .' + postType + '-aoi-checkbox').prop('checked', false);
                    alert('Settings cleared successfully!');
                } else {
                    alert('Error clearing settings: ' + (response.data || 'Unknown error'));
                    console.error('Clear error:', response);
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                alert('Network error occurred. Please try again.');
            });
        }
    });

    function loadGatedContentSettings(postId, postType) {
        console.log('Loading settings for post:', postId, 'type:', postType);
        
        $.post(ajaxurl, {
            action: 'load_gated_content_settings',
            nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
            post_id: postId
        }, function(response) {
            console.log('Settings loaded:', response);
            
            if (response.success && response.data) {
                var data = response.data;
                $('#' + postType + '-preview-text').val(data.preview_text || '');
                $('#' + postType + '-ninja-form').val(data.ninja_form_id || '');
                
                // Clear all checkboxes for this post type first
                $('.' + postType + '-toi-checkbox, .' + postType + '-aoi-checkbox').prop('checked', false);
                
                // Set TOI checkboxes
                if (data.required_toi && Array.isArray(data.required_toi)) {
                    data.required_toi.forEach(function(toi) {
                        $('.' + postType + '-toi-checkbox[value="' + toi + '"]').prop('checked', true);
                    });
                }
                
                // Set AOI checkboxes
                if (data.required_aoi && Array.isArray(data.required_aoi)) {
                    data.required_aoi.forEach(function(aoi) {
                        $('.' + postType + '-aoi-checkbox[value="' + aoi + '"]').prop('checked', true);
                    });
                }
            } else {
                console.log('No existing settings found or error occurred');
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        });
    }
    
    // Add click handlers for manage buttons
    $('.manage-type-btn').on('click', function(e) {
        e.preventDefault();
        var postType = $(this).data('type');
        console.log('Manage button clicked for:', postType);
        
        // Switch to the appropriate tab
        $('.content-type-tabs .nav-tab').removeClass('nav-tab-active');
        $('#tab-' + postType).addClass('nav-tab-active');
        
        $('.content-type-panel').removeClass('active');
        $('#panel-' + postType).addClass('active');
        
        // Scroll to the content
        $('html, body').animate({
            scrollTop: $('.content-type-tabs').offset().top - 100
        }, 500);
    });
    
    // Add click handlers for edit buttons in recent activity
    $('.edit-gated-btn').on('click', function(e) {
        e.preventDefault();
        var postType = $(this).data('type');
        var postId = $(this).data('id');
        
        console.log('Edit button clicked for:', postType, 'ID:', postId);
        
        // Switch to the appropriate tab
        $('.content-type-tabs .nav-tab').removeClass('nav-tab-active');
        $('#tab-' + postType).addClass('nav-tab-active');
        
        $('.content-type-panel').removeClass('active');
        $('#panel-' + postType).addClass('active');
        
        // Set the post in the dropdown
        $('#' + postType + '-post-select').val(postId).trigger('change');
        
        // Scroll to the content
        $('html, body').animate({
            scrollTop: $('.content-type-tabs').offset().top - 100
        }, 500);
    });
});
</script>
