<?php
if (!defined('ABSPATH')) exit;

// Get the current post type from the calling function
$post_type = isset($current_post_type) ? $current_post_type : 'articles';

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

// Get posts for current post type
$posts = get_posts(array(
    'post_type' => $post_type,
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'orderby' => 'title',
    'order' => 'ASC'
));

// Get post type labels
$post_type_object = get_post_type_object($post_type);
$post_type_label = $post_type_object ? $post_type_object->labels->name : ucfirst($post_type);
$post_type_singular = $post_type_object ? $post_type_object->labels->singular_name : ucfirst($post_type);
?>

<style>
.gated-content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
    padding-bottom: 15px;
    margin-bottom: 15px;
}

.gated-content-header h3 {
    margin: 0;
}

.search-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-controls input {
    min-width: 300px;
}

.search-results-count {
    font-size: 12px;
    color: #666;
    font-style: italic;
    margin-left: 10px;
}

.current-gated-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.gated-content-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.gated-content-table th,
.gated-content-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.gated-content-table th {
    background: #f9f9f9;
    font-weight: 600;
}

.gated-content-table .actions {
    width: 100px;
}

.gated-content-table .actions .button {
    margin-right: 5px;
}

.gated-content-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
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

.gated-settings {
    background: #f0f8ff;
    border: 1px solid #bee5eb;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.workbooks-fields td {
    vertical-align: top;
}

.workbooks-fields label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.workbooks-fields input {
    width: 100%;
    max-width: 200px;
}

.workbooks-fields .description {
    font-size: 12px;
    color: #666;
    margin: 5px 0 0 0;
}

.ninja-form-note {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
}

.post-select {
    min-width: 300px;
}

.post-selection-container {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.post-search-input {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 14px;
}

.post-search-input:focus {
    border-color: #0073aa;
    box-shadow: 0 0 0 1px #0073aa;
    outline: none;
}

.post-select-count {
    font-size: 11px;
    color: #666;
    margin-top: 5px;
    font-style: italic;
}

.gated-content-form .submit {
    border-top: 1px solid #ddd;
    padding-top: 15px;
    margin-top: 20px;
}

.loading {
    color: #666;
    font-style: italic;
}

.no-gated-content {
    color: #666;
    font-style: italic;
    text-align: center;
    padding: 20px;
}
</style>

<!-- Current Gated Content List -->
<div class="current-gated-content">
    <div class="gated-content-header">
        <h3>Current Gated <?php echo esc_html($post_type_label); ?></h3>
        <div class="search-controls">
            <input type="text" id="gated-content-search" placeholder="Search <?php echo strtolower($post_type_label); ?> by title or reference..." class="regular-text">
            <button type="button" id="clear-search" class="button">Clear</button>
            <span class="search-results-count" id="search-results-count"></span>
        </div>
    </div>
    <div id="gated-content-list">
        <p class="loading">Loading current gated <?php echo strtolower($post_type_label); ?>...</p>
    </div>
</div>

<!-- Single Content Type Form -->
<div class="gated-content-form-container">
    <h3>Manage <?php echo esc_html($post_type_singular); ?> Gated Content</h3>
    
    <form id="<?php echo $post_type; ?>-gated-form" class="gated-content-form">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="<?php echo $post_type; ?>-post-select">Select <?php echo esc_html($post_type_singular); ?></label>
                </th>
                <td>
                    <div class="post-selection-container">
                        <input type="text" id="<?php echo $post_type; ?>-post-search" placeholder="Search <?php echo strtolower($post_type_label); ?>..." class="regular-text post-search-input" style="margin-bottom: 10px; width: 100%;">
                        <select id="<?php echo $post_type; ?>-post-select" name="post_id" class="post-select" data-post-type="<?php echo $post_type; ?>">
                            <option value="">-- Select a <?php echo esc_html($post_type_singular); ?> --</option>
                            <?php
                            foreach ($posts as $post_item) {
                                echo '<option value="' . esc_attr($post_item->ID) . '">' . esc_html($post_item->post_title) . '</option>';
                            }
                            ?>
                        </select>
                        <div class="post-select-count" id="<?php echo $post_type; ?>-count">Showing <?php echo count($posts); ?> <?php echo strtolower($post_type_label); ?></div>
                    </div>
                    <p class="description">Search and choose the <?php echo strtolower($post_type_singular); ?> you want to gate</p>
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
                        <label for="<?php echo $post_type; ?>-gate-content">Gate Content</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="<?php echo $post_type; ?>-gate-content" name="gate_content" value="1">
                            Enable gated content for this <?php echo strtolower($post_type_singular); ?>
                        </label>
                        <p class="description">Check this to enable gated content functionality</p>
                    </td>
                </tr>
            </table>

            <div id="<?php echo $post_type; ?>-gated-settings" class="gated-settings" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $post_type; ?>-preview-text">Preview Content</label>
                        </th>
                        <td>
                            <?php
                            $editor_settings = array(
                                'textarea_name' => 'preview_text',
                                'textarea_rows' => 10,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => array(
                                    'plugins' => 'lists,link,image,paste,textcolor,wordpress,wplink,wptextpattern',
                                    'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,undo,redo',
                                    'toolbar2' => 'formatselect,forecolor,backcolor,|,alignleft,aligncenter,alignright,alignjustify,|,image,media,|,removeformat,code'
                                ),
                                'quicktags' => true
                            );
                            wp_editor('', $post_type . '-preview-text', $editor_settings);
                            ?>
                            <p class="description">Rich content that will be displayed to users without access. Use the editor tools to format text, add images, videos, and other media.</p>
                        </td>
                    </tr>
                </table>

                <h4>Additional Preview Elements</h4>
                <table class="form-table preview-elements">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $post_type; ?>-preview-image">Featured Image</label>
                        </th>
                        <td>
                            <div class="preview-image-container">
                                <input type="hidden" id="<?php echo $post_type; ?>-preview-image" name="preview_image" value="">
                                <div class="preview-image-preview" style="margin-bottom: 10px; min-height: 50px; border: 2px dashed #ddd; padding: 20px; text-align: center; background: #f9f9f9;">
                                    <span class="no-image-text">No image selected</span>
                                    <img class="preview-image-display" style="display: none; max-width: 200px; height: auto;">
                                </div>
                                <button type="button" class="button select-preview-image">Select Image</button>
                                <button type="button" class="button remove-preview-image" style="display: none;">Remove Image</button>
                            </div>
                            <p class="description">Optional featured image for the preview content</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $post_type; ?>-preview-video">Video Embed</label>
                        </th>
                        <td>
                            <input type="url" id="<?php echo $post_type; ?>-preview-video" name="preview_video" class="regular-text" placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/..." style="width: 100%;">
                            <p class="description">YouTube, Vimeo, or other video URL. Will be automatically embedded in the preview.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $post_type; ?>-preview-gallery">Image Gallery</label>
                        </th>
                        <td>
                            <div class="preview-gallery-container">
                                <input type="hidden" id="<?php echo $post_type; ?>-preview-gallery" name="preview_gallery" value="">
                                <div class="preview-gallery-preview" style="margin-bottom: 10px; min-height: 80px; border: 2px dashed #ddd; padding: 20px; background: #f9f9f9;">
                                    <div class="gallery-images" style="display: flex; flex-wrap: wrap; gap: 10px;">
                                        <span class="no-gallery-text">No images selected for gallery</span>
                                    </div>
                                </div>
                                <button type="button" class="button select-preview-gallery">Select Gallery Images</button>
                                <button type="button" class="button clear-preview-gallery" style="display: none;">Clear Gallery</button>
                            </div>
                            <p class="description">Select multiple images to create a gallery in the preview content</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $post_type; ?>-preview-button">Call-to-Action Button</label>
                        </th>
                        <td>
                            <table class="form-table" style="margin: 0;">
                                <tr>
                                    <td style="width: 50%; padding-right: 10px;">
                                        <label for="<?php echo $post_type; ?>-preview-button-text">Button Text</label>
                                        <input type="text" id="<?php echo $post_type; ?>-preview-button-text" name="preview_button_text" class="regular-text" placeholder="e.g., Request Access, Learn More">
                                    </td>
                                    <td style="width: 50%; padding-left: 10px;">
                                        <label for="<?php echo $post_type; ?>-preview-button-url">Button URL</label>
                                        <input type="url" id="<?php echo $post_type; ?>-preview-button-url" name="preview_button_url" class="regular-text" placeholder="https://example.com/contact">
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <label for="<?php echo $post_type; ?>-preview-button-style">Button Style</label>
                                        <select id="<?php echo $post_type; ?>-preview-button-style" name="preview_button_style">
                                            <option value="primary">Primary (Blue)</option>
                                            <option value="secondary">Secondary (Gray)</option>
                                            <option value="success">Success (Green)</option>
                                            <option value="warning">Warning (Orange)</option>
                                            <option value="danger">Danger (Red)</option>
                                            <option value="link">Link Style</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <p class="description">Add a call-to-action button to encourage user engagement</p>
                        </td>
                    </tr>
                </table>

                <h4>Workbooks Integration</h4>
                <table class="form-table workbooks-fields">
                    <tr>
                        <td style="width: 50%; padding-right: 10px;">
                            <label for="<?php echo $post_type; ?>-workbooks-ref">Workbooks Reference</label>
                            <input type="text" id="<?php echo $post_type; ?>-workbooks-ref" name="workbooks_reference" class="regular-text" placeholder="Enter reference ID">
                            <p class="description">Workbooks reference identifier</p>
                        </td>
                        <td style="width: 50%; padding-left: 10px;">
                            <label for="<?php echo $post_type; ?>-campaign-ref">Campaign Reference</label>
                            <div style="display: flex; align-items: center;">
                                <span style="margin-right: 5px;">CAMP-</span>
                                <input type="text" id="<?php echo $post_type; ?>-campaign-ref" name="campaign_reference" class="regular-text" placeholder="Enter campaign ID">
                            </div>
                            <p class="description">Campaign reference (CAMP- prefix will be added automatically)</p>
                        </td>
                    </tr>
                </table>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $post_type; ?>-redirect-url">Redirect URL</label>
                        </th>
                        <td>
                            <input type="url" id="<?php echo $post_type; ?>-redirect-url" name="redirect_url" class="regular-text" placeholder="https://example.com/redirect-page" style="width: 100%;">
                            <p class="description">Optional redirect URL after successful form submission (leave blank to stay on current page)</p>
                        </td>
                    </tr>
                </table>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $post_type; ?>-ninja-form">Access Request Form</label>
                        </th>
                        <td>
                            <select id="<?php echo $post_type; ?>-ninja-form" name="ninja_form_id">
                                <option value="24" selected>Ninja Form ID 24 (Default Gated Content Form)</option>
                            </select>
                            <p class="description">Form that will be displayed for users to request access</p>
                            <p class="ninja-form-note">
                                <strong>Note:</strong> Using Ninja Form ID 24 as the default gated content form. Forms can be managed and configured with repeater fields in the 
                                <a href="<?php echo admin_url('admin.php?page=ninja-forms'); ?>" target="_blank">Ninja Forms settings</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Save Gated Content Settings</button>
                <button type="button" class="button button-secondary clear-settings">Clear Settings</button>
            </p>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Store current post type for this page
    var currentPostType = '<?php echo $post_type; ?>';
    var allGatedContent = []; // Store all content for filtering

    console.log('Gated Content Single Page: jQuery loaded for ' + currentPostType);
    
    // Load current gated content on page load
    loadCurrentGatedContent();
    
    // Post search functionality
    $('.post-search-input').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        var $select = $(this).siblings('.post-select');
        var $options = $select.find('option');
        var $countElement = $(this).siblings('.post-select-count');
        var postType = $select.data('post-type');
        
        if (!searchTerm) {
            // Show all options
            $options.show();
            var totalCount = $options.length - 1; // Subtract 1 for the default option
            updatePostSelectCount($countElement, totalCount, totalCount, postType);
            return;
        }
        
        // Hide all options except the default
        $options.hide();
        $options.first().show(); // Show default option
        
        var visibleCount = 0;
        var totalCount = $options.length - 1; // Subtract 1 for the default option
        
        // Show matching options
        $options.each(function() {
            var optionText = $(this).text().toLowerCase();
            if (optionText.includes(searchTerm) && $(this).val() !== '') {
                $(this).show();
                visibleCount++;
            }
        });
        
        updatePostSelectCount($countElement, visibleCount, totalCount, postType);
        
        // Auto-select if only one result
        if (visibleCount === 1) {
            var $visibleOption = $options.filter(':visible').not(':first');
            if ($visibleOption.length === 1) {
                $select.val($visibleOption.val()).trigger('change');
            }
        }
    });
    
    // Main search functionality
    $('#gated-content-search').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        filterGatedContent(searchTerm);
    });
    
    $('#clear-search').on('click', function() {
        $('#gated-content-search').val('');
        displayGatedContent(allGatedContent);
        updateSearchResultsCount(allGatedContent.length, allGatedContent.length);
    });
    
    // Gate content checkbox functionality
    $('input[name="gate_content"]').on('change', function() {
        var $gatedSettings = $('#' + currentPostType + '-gated-settings');
        
        if ($(this).is(':checked')) {
            $gatedSettings.slideDown();
        } else {
            $gatedSettings.slideUp();
            // Clear form fields when disabling gated content
            $gatedSettings.find('input, textarea, select').val('');
        }
    });

    // Post selection functionality
    $('.post-select').on('change', function() {
        var postId = $(this).val();
        var $config = $('#' + currentPostType + '-content-config');
        var $titleField = $('#' + currentPostType + '-post-title');
        var $gateCheckbox = $('#' + currentPostType + '-gate-content');
        var $gatedSettings = $('#' + currentPostType + '-gated-settings');
        
        console.log('Post selected:', postId, 'Type:', currentPostType);
        
        if (postId) {
            // Get the selected post title
            var postTitle = $(this).find('option:selected').text();
            $titleField.val(postTitle);
            
            // Load existing settings if any
            loadGatedContentSettings(postId, currentPostType);
            
            // Show configuration section
            $config.slideDown();
        } else {
            // Hide configuration section and reset form
            $config.slideUp();
            $gateCheckbox.prop('checked', false);
            $gatedSettings.hide();
        }
    });

    // Form submission
    $('.gated-content-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var postId = $form.find('.post-select').val();
        var gateContent = $form.find('[name="gate_content"]').is(':checked');
        
        if (!postId) {
            alert('Please select a post first.');
            return;
        }
        
        var formData = {
            action: 'save_gated_content_settings',
            nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
            post_id: postId,
            post_type: currentPostType,
            gate_content: gateContent ? 1 : 0,
            preview_text: $form.find('[name="preview_text"]').val(),
            workbooks_reference: $form.find('[name="workbooks_reference"]').val(),
            campaign_reference: $form.find('[name="campaign_reference"]').val(),
            redirect_url: $form.find('[name="redirect_url"]').val(),
            ninja_form_id: $form.find('[name="ninja_form_id"]').val()
        };
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                alert('Gated content settings saved successfully!');
                // Reload the current gated content list
                loadCurrentGatedContent();
            } else {
                alert('Error saving settings: ' + response.data);
            }
        });
    });

    // Clear settings functionality
    $('.clear-settings').on('click', function() {
        if (confirm('Are you sure you want to clear all settings for this content?')) {
            var $form = $(this).closest('.gated-content-form');
            var postId = $form.find('.post-select').val();
            
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
                    $form.find('[name="gate_content"]').prop('checked', false);
                    $form.find('[name="preview_text"]').val('');
                    $form.find('[name="workbooks_reference"]').val('');
                    $form.find('[name="campaign_reference"]').val('');
                    $form.find('[name="redirect_url"]').val('');
                    $form.find('[name="ninja_form_id"]').val('24');
                    $('#' + currentPostType + '-gated-settings').slideUp();
                    alert('Settings cleared successfully!');
                    // Reload the current gated content list
                    loadCurrentGatedContent();
                } else {
                    alert('Error clearing settings: ' + response.data);
                }
            });
        }
    });

    // Remove gated content functionality
    $(document).on('click', '.remove-gated-content', function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        var postTitle = $(this).data('post-title');
        
        if (confirm('Are you sure you want to remove gated content from "' + postTitle + '"?')) {
            var formData = {
                action: 'clear_gated_content_settings',
                nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
                post_id: postId
            };
            
            $.post(ajaxurl, formData, function(response) {
                if (response.success) {
                    alert('Gated content removed successfully!');
                    loadCurrentGatedContent();
                } else {
                    alert('Error removing gated content: ' + response.data);
                }
            });
        }
    });

    function updatePostSelectCount($element, visible, total, postType) {
        var typeLabel = getPostTypeLabel(postType);
        if (visible === total) {
            $element.text('Showing ' + total + ' ' + typeLabel);
        } else {
            $element.text('Showing ' + visible + ' of ' + total + ' ' + typeLabel);
        }
    }
    
    function getPostTypeLabel(postType) {
        switch(postType) {
            case 'articles': return 'articles';
            case 'whitepapers': return 'whitepapers';
            case 'news': return 'news posts';
            case 'events': return 'events';
            default: return 'items';
        }
    }

    function filterGatedContent(searchTerm) {
        if (!searchTerm) {
            displayGatedContent(allGatedContent);
            updateSearchResultsCount(allGatedContent.length, allGatedContent.length);
            return;
        }
        
        var filteredContent = allGatedContent.filter(function(item) {
            return item.title.toLowerCase().includes(searchTerm) ||
                   (item.workbooks_reference && item.workbooks_reference.toLowerCase().includes(searchTerm)) ||
                   (item.campaign_reference && item.campaign_reference.toLowerCase().includes(searchTerm)) ||
                   (item.ninja_form_title && item.ninja_form_title.toLowerCase().includes(searchTerm));
        });
        
        displayGatedContent(filteredContent);
        updateSearchResultsCount(filteredContent.length, allGatedContent.length);
    }
    
    function updateSearchResultsCount(visible, total) {
        var $countElement = $('#search-results-count');
        var postTypeLabel = getPostTypeLabel(currentPostType);
        
        if (visible === total) {
            if (total === 0) {
                $countElement.text('No gated ' + postTypeLabel + ' found');
            } else if (total === 1) {
                $countElement.text('1 gated ' + postTypeLabel.slice(0, -1)); // Remove 's' for singular
            } else {
                $countElement.text(total + ' gated ' + postTypeLabel);
            }
        } else {
            if (visible === 0) {
                $countElement.text('No matches found (0 of ' + total + ')');
            } else if (visible === 1) {
                $countElement.text('1 match (of ' + total + ')');
            } else {
                $countElement.text(visible + ' matches (of ' + total + ')');
            }
        }
    }

    function displayGatedContent(content) {
        var html = '';
        
        if (content && content.length > 0) {
            html += '<table class="gated-content-table">';
            html += '<thead><tr>';
            html += '<th>Title</th>';
            html += '<th>Workbooks Ref</th>';
            html += '<th>Campaign Ref</th>';
            html += '<th>Form</th>';
            html += '<th class="actions">Actions</th>';
            html += '</tr></thead><tbody>';
            
            content.forEach(function(item) {
                html += '<tr>';
                html += '<td><strong>' + item.title + '</strong></td>';
                html += '<td>' + (item.workbooks_reference || '-') + '</td>';
                html += '<td>' + (item.campaign_reference ? 'CAMP-' + item.campaign_reference : '-') + '</td>';
                html += '<td>' + (item.ninja_form_title || '-') + '</td>';
                html += '<td class="actions">';
                html += '<a href="' + item.edit_url + '" class="button button-small">Edit</a>';
                html += '<button type="button" class="button button-small remove-gated-content" data-post-id="' + item.post_id + '" data-post-title="' + item.title + '">Remove</button>';
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
        } else if ($('#gated-content-search').val()) {
            html = '<p class="no-gated-content">No ' + getPostTypeLabel(currentPostType) + ' found matching your search criteria.</p>';
        } else {
            html = '<p class="no-gated-content">No gated ' + getPostTypeLabel(currentPostType) + ' configured yet.</p>';
        }
        
        $('#gated-content-list').html(html);
    }

    function loadCurrentGatedContent() {
        $.post(ajaxurl, {
            action: 'load_current_gated_articles',
            nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
            post_type: currentPostType
        }, function(response) {
            if (response.success) {
                allGatedContent = response.data || [];
                displayGatedContent(allGatedContent);
                updateSearchResultsCount(allGatedContent.length, allGatedContent.length);
            } else {
                $('#gated-content-list').html('<p class="loading">Error loading gated content.</p>');
                updateSearchResultsCount(0, 0);
            }
        });
    }

    function loadGatedContentSettings(postId, postType) {
        $.post(ajaxurl, {
            action: 'load_gated_content_settings',
            nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
            post_id: postId
        }, function(response) {
            if (response.success && response.data) {
                var data = response.data;
                var $gateCheckbox = $('#' + postType + '-gate-content');
                var $gatedSettings = $('#' + postType + '-gated-settings');
                
                // Set gate content checkbox
                $gateCheckbox.prop('checked', data.gate_content == '1');
                
                // Show/hide gated settings based on checkbox
                if (data.gate_content == '1') {
                    $gatedSettings.show();
                } else {
                    $gatedSettings.hide();
                }
                
                // Populate form fields
                $('#' + postType + '-preview-text').val(data.preview_text || '');
                $('#' + postType + '-workbooks-ref').val(data.workbooks_reference || '');
                $('#' + postType + '-campaign-ref').val(data.campaign_reference || '');
                $('#' + postType + '-redirect-url').val(data.redirect_url || '');
                $('#' + postType + '-ninja-form').val(data.ninja_form_id || '24');
            }
        });
    }
});
</script>
