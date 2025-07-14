<?php
if (!defined('ABSPATH')) exit;

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
?>

<h2>Gated Content Management</h2>
<p>Configure gated content settings for different post types. Select content and configure access requirements.</p>

<div class="gated-content-tabs">
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="#articles-tab" class="nav-tab nav-tab-active" data-tab="articles">Articles</a>
        <a href="#whitepapers-tab" class="nav-tab" data-tab="whitepapers">Whitepapers</a>
        <a href="#news-tab" class="nav-tab" data-tab="news">News</a>
        <a href="#events-tab" class="nav-tab" data-tab="events">Events</a>
    </nav>

    <!-- Articles Tab -->
    <div id="articles-tab" class="tab-content active">
        <h3>Articles Gated Content</h3>
        
        <form id="articles-gated-form" class="gated-content-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="articles-post-select">Select Article</label>
                    </th>
                    <td>
                        <select id="articles-post-select" name="post_id" class="post-select" data-post-type="articles">
                            <option value="">-- Select an Article --</option>
                            <?php
                            $articles = get_posts(array(
                                'post_type' => 'articles',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            foreach ($articles as $article) {
                                echo '<option value="' . esc_attr($article->ID) . '">' . esc_html($article->post_title) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Choose the article you want to gate</p>
                    </td>
                </tr>
            </table>

            <div id="articles-content-config" class="content-config" style="display: none;">
                <h4>Content Configuration</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="articles-post-title">Post Title</label>
                        </th>
                        <td>
                            <input type="text" id="articles-post-title" name="post_title" class="regular-text" readonly>
                            <p class="description">Current post title (read-only)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="articles-preview-text">Preview Text</label>
                        </th>
                        <td>
                            <textarea id="articles-preview-text" name="preview_text" rows="5" cols="50" class="large-text" placeholder="Enter the preview text that will be shown to non-subscribers..."></textarea>
                            <p class="description">This text will be displayed instead of the full content for users without access</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="articles-ninja-form">Access Request Form</label>
                        </th>
                        <td>
                            <select id="articles-ninja-form" name="ninja_form_id">
                                <option value="">-- Select a Form --</option>
                                <?php foreach ($ninja_forms as $form): ?>
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

    <!-- Whitepapers Tab -->
    <div id="whitepapers-tab" class="tab-content">
        <h3>Whitepapers Gated Content</h3>
        
        <form id="whitepapers-gated-form" class="gated-content-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="whitepapers-post-select">Select Whitepaper</label>
                    </th>
                    <td>
                        <select id="whitepapers-post-select" name="post_id" class="post-select" data-post-type="whitepapers">
                            <option value="">-- Select a Whitepaper --</option>
                            <?php
                            $whitepapers = get_posts(array(
                                'post_type' => 'whitepapers',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            foreach ($whitepapers as $whitepaper) {
                                echo '<option value="' . esc_attr($whitepaper->ID) . '">' . esc_html($whitepaper->post_title) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Choose the whitepaper you want to gate</p>
                    </td>
                </tr>
            </table>

            <div id="whitepapers-content-config" class="content-config" style="display: none;">
                <h4>Content Configuration</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="whitepapers-post-title">Post Title</label>
                        </th>
                        <td>
                            <input type="text" id="whitepapers-post-title" name="post_title" class="regular-text" readonly>
                            <p class="description">Current post title (read-only)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="whitepapers-preview-text">Preview Text</label>
                        </th>
                        <td>
                            <textarea id="whitepapers-preview-text" name="preview_text" rows="5" cols="50" class="large-text" placeholder="Enter the preview text that will be shown to non-subscribers..."></textarea>
                            <p class="description">This text will be displayed instead of the full content for users without access</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="whitepapers-ninja-form">Access Request Form</label>
                        </th>
                        <td>
                            <select id="whitepapers-ninja-form" name="ninja_form_id">
                                <option value="">-- Select a Form --</option>
                                <?php foreach ($ninja_forms as $form): ?>
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

    <!-- News Tab -->
    <div id="news-tab" class="tab-content">
        <h3>News Gated Content</h3>
        
        <form id="news-gated-form" class="gated-content-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="news-post-select">Select News Post</label>
                    </th>
                    <td>
                        <select id="news-post-select" name="post_id" class="post-select" data-post-type="news">
                            <option value="">-- Select a News Post --</option>
                            <?php
                            $news_posts = get_posts(array(
                                'post_type' => 'news',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            foreach ($news_posts as $news_post) {
                                echo '<option value="' . esc_attr($news_post->ID) . '">' . esc_html($news_post->post_title) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Choose the news post you want to gate</p>
                    </td>
                </tr>
            </table>

            <div id="news-content-config" class="content-config" style="display: none;">
                <h4>Content Configuration</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="news-post-title">Post Title</label>
                        </th>
                        <td>
                            <input type="text" id="news-post-title" name="post_title" class="regular-text" readonly>
                            <p class="description">Current post title (read-only)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="news-preview-text">Preview Text</label>
                        </th>
                        <td>
                            <textarea id="news-preview-text" name="preview_text" rows="5" cols="50" class="large-text" placeholder="Enter the preview text that will be shown to non-subscribers..."></textarea>
                            <p class="description">This text will be displayed instead of the full content for users without access</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="news-ninja-form">Access Request Form</label>
                        </th>
                        <td>
                            <select id="news-ninja-form" name="ninja_form_id">
                                <option value="">-- Select a Form --</option>
                                <?php foreach ($ninja_forms as $form): ?>
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

    <!-- Events Tab -->
    <div id="events-tab" class="tab-content">
        <h3>Events Gated Content</h3>
        
        <form id="events-gated-form" class="gated-content-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="events-post-select">Select Event</label>
                    </th>
                    <td>
                        <select id="events-post-select" name="post_id" class="post-select" data-post-type="events">
                            <option value="">-- Select an Event --</option>
                            <?php
                            $events = get_posts(array(
                                'post_type' => 'events',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            foreach ($events as $event) {
                                echo '<option value="' . esc_attr($event->ID) . '">' . esc_html($event->post_title) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Choose the event you want to gate</p>
                    </td>
                </tr>
            </table>

            <div id="events-content-config" class="content-config" style="display: none;">
                <h4>Content Configuration</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="events-post-title">Post Title</label>
                        </th>
                        <td>
                            <input type="text" id="events-post-title" name="post_title" class="regular-text" readonly>
                            <p class="description">Current post title (read-only)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="events-preview-text">Preview Text</label>
                        </th>
                        <td>
                            <textarea id="events-preview-text" name="preview_text" rows="5" cols="50" class="large-text" placeholder="Enter the preview text that will be shown to non-subscribers..."></textarea>
                            <p class="description">This text will be displayed instead of the full content for users without access</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="events-ninja-form">Access Request Form</label>
                        </th>
                        <td>
                            <select id="events-ninja-form" name="ninja_form_id">
                                <option value="">-- Select a Form --</option>
                                <?php foreach ($ninja_forms as $form): ?>
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

<style>
.gated-content-tabs .nav-tab-wrapper {
    border-bottom: 1px solid #ccd0d4;
    margin-bottom: 0px;
}

.gated-content-tabs .nav-tab {
    margin-left: 0;
    margin-right: 6px;
    border-radius: 4px 4px 0 0;
    border-bottom: 1px solid #ccd0d4;
    background: #f6f7f7;
}

.gated-content-tabs .nav-tab.nav-tab-active {
    background: #fff;
    border-bottom: 1px solid #fff;
    margin-bottom: -1px;
    position: relative;
}

.tab-content {
    display: none;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    border-radius: 0 4px 4px 4px;
}

.tab-content.active {
    display: block;
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

.gated-content-form .submit {
    border-top: 1px solid #ddd;
    padding-top: 15px;
    margin-top: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Ensure jQuery is loaded and working
    console.log('Gated Content: jQuery loaded and ready');
    
    // Tab switching functionality with improved handling
    $('.gated-content-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        console.log('Tab clicked:', $(this).data('tab'));
        
        // Remove active class from all tabs and content
        $('.gated-content-tabs .nav-tab').removeClass('nav-tab-active');
        $('.gated-content-tabs .tab-content').removeClass('active').hide();
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        var targetTab = $(this).data('tab') + '-tab';
        console.log('Showing tab:', targetTab);
        $('#' + targetTab).addClass('active').show();
    });

    // Initialize: make sure first tab is visible
    $('.gated-content-tabs .tab-content').hide();
    $('.gated-content-tabs .tab-content.active').show();

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
        
        var $form = $(this);
        var postId = $form.find('.post-select').val();
        var postType = $form.find('.post-select').data('post-type');
        
        if (!postId) {
            alert('Please select a post first.');
            return;
        }
        
        var formData = {
            action: 'save_gated_content_settings',
            nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
            post_id: postId,
            post_type: postType,
            preview_text: $form.find('[name="preview_text"]').val(),
            ninja_form_id: $form.find('[name="ninja_form_id"]').val()
        };
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                alert('Gated content settings saved successfully!');
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
                    $form.find('[name="preview_text"]').val('');
                    $form.find('[name="ninja_form_id"]').val('');
                    alert('Settings cleared successfully!');
                } else {
                    alert('Error clearing settings: ' + response.data);
                }
            });
        }
    });

    function loadGatedContentSettings(postId, postType) {
        $.post(ajaxurl, {
            action: 'load_gated_content_settings',
            nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
            post_id: postId
        }, function(response) {
            if (response.success && response.data) {
                $('#' + postType + '-preview-text').val(response.data.preview_text || '');
                $('#' + postType + '-ninja-form').val(response.data.ninja_form_id || '');
            }
        });
    }
});

// Fallback for environments where jQuery might not be available as $
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function() {
        // Additional fallback tab switching
        jQuery('.gated-content-tabs .nav-tab').click(function(e) {
            e.preventDefault();
            var tabId = jQuery(this).attr('data-tab') + '-tab';
            
            // Hide all tab content
            jQuery('.gated-content-tabs .tab-content').removeClass('active').hide();
            jQuery('.gated-content-tabs .nav-tab').removeClass('nav-tab-active');
            
            // Show selected tab
            jQuery('#' + tabId).addClass('active').show();
            jQuery(this).addClass('nav-tab-active');
        });
    });
}
</script>
