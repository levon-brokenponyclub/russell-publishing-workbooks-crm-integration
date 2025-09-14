<?php
/**
 * Ninja Forms Loader Admin Page
 * 
 * @package DTR/WorkbooksIntegration
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

// Handle form submission
if (isset($_POST['submit_loader_settings'])) {
    check_admin_referer('dtr_loader_settings', 'loader_nonce');
    
    $loader_options = [
        'enabled' => isset($_POST['loader_enabled']) ? 1 : 0,
        'logo_color' => sanitize_text_field($_POST['logo_color'] ?? ''),
        'logo_white' => sanitize_text_field($_POST['logo_white'] ?? ''),
        'background_color' => sanitize_text_field($_POST['background_color'] ?? 'rgba(255,255,255,0.8)'),
        'progress_color' => sanitize_text_field($_POST['progress_color'] ?? '#871f80'),
        'text_color' => sanitize_text_field($_POST['text_color'] ?? '#871f80'),
        'submitting_text' => sanitize_text_field($_POST['submitting_text'] ?? 'Submitting...'),
        'success_text' => sanitize_text_field($_POST['success_text'] ?? 'Submission Successful'),
        'logo_size' => sanitize_text_field($_POST['logo_size'] ?? '7rem'),
        'animation_speed' => sanitize_text_field($_POST['animation_speed'] ?? '0.3s'),
    ];
    
    update_option('dtr_ninja_forms_loader', $loader_options);
    echo '<div class="notice notice-success"><p>Loader settings saved successfully!</p></div>';
}

// Get current settings
$loader_options = get_option('dtr_ninja_forms_loader', [
    'enabled' => 1,
    'logo_color' => get_template_directory_uri() . '/img/logos/DTR_Logo-02.svg',
    'logo_white' => get_template_directory_uri() . '/img/logos/DTR_Logo-01.svg',
    'background_color' => 'rgba(255,255,255,0.8)',
    'progress_color' => '#871f80',
    'text_color' => '#871f80',
    'submitting_text' => 'Submitting...',
    'success_text' => 'Submission Successful',
    'logo_size' => '7rem',
    'animation_speed' => '0.3s',
]);
?>

<div class="wrap">
    <h2><?php _e('Ninja Forms Loader Settings', 'dtr-workbooks'); ?></h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('dtr_loader_settings', 'loader_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="loader_enabled"><?php _e('Enable Loader', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="loader_enabled" name="loader_enabled" value="1" <?php checked($loader_options['enabled'], 1); ?> />
                    <p class="description"><?php _e('Enable the form submission loader for all Ninja Forms.', 'dtr-workbooks'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="logo_color"><?php _e('Colored Logo URL', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="url" id="logo_color" name="logo_color" value="<?php echo esc_attr($loader_options['logo_color']); ?>" class="regular-text" />
                    <button type="button" class="button" id="upload_logo_color"><?php _e('Upload Logo', 'dtr-workbooks'); ?></button>
                    <p class="description"><?php _e('URL to the colored version of your logo (shown at start).', 'dtr-workbooks'); ?></p>
                    <div id="logo_color_preview" style="margin-top: 10px;">
                        <?php if (!empty($loader_options['logo_color'])): ?>
                            <img src="<?php echo esc_url($loader_options['logo_color']); ?>" style="max-width: 100px; height: auto;" />
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="logo_white"><?php _e('White Logo URL', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="url" id="logo_white" name="logo_white" value="<?php echo esc_attr($loader_options['logo_white']); ?>" class="regular-text" />
                    <button type="button" class="button" id="upload_logo_white"><?php _e('Upload Logo', 'dtr-workbooks'); ?></button>
                    <p class="description"><?php _e('URL to the white version of your logo (shown when progress reaches 50%).', 'dtr-workbooks'); ?></p>
                    <div id="logo_white_preview" style="margin-top: 10px;">
                        <?php if (!empty($loader_options['logo_white'])): ?>
                            <img src="<?php echo esc_url($loader_options['logo_white']); ?>" style="max-width: 100px; height: auto; background: #333; padding: 10px;" />
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="background_color"><?php _e('Background Color', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="text" id="background_color" name="background_color" value="<?php echo esc_attr($loader_options['background_color']); ?>" class="regular-text color-picker" />
                    <p class="description"><?php _e('Background color of the loader overlay (supports rgba values).', 'dtr-workbooks'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="progress_color"><?php _e('Progress Bar Color', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="text" id="progress_color" name="progress_color" value="<?php echo esc_attr($loader_options['progress_color']); ?>" class="regular-text color-picker" />
                    <p class="description"><?php _e('Color of the progress bar.', 'dtr-workbooks'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="text_color"><?php _e('Text Color', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="text" id="text_color" name="text_color" value="<?php echo esc_attr($loader_options['text_color']); ?>" class="regular-text color-picker" />
                    <p class="description"><?php _e('Color of the loading text messages.', 'dtr-workbooks'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="submitting_text"><?php _e('Submitting Text', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="text" id="submitting_text" name="submitting_text" value="<?php echo esc_attr($loader_options['submitting_text']); ?>" class="regular-text" />
                    <p class="description"><?php _e('Text shown while the form is being submitted.', 'dtr-workbooks'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="success_text"><?php _e('Success Text', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="text" id="success_text" name="success_text" value="<?php echo esc_attr($loader_options['success_text']); ?>" class="regular-text" />
                    <p class="description"><?php _e('Text shown when the form submission is successful.', 'dtr-workbooks'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="logo_size"><?php _e('Logo Size', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="text" id="logo_size" name="logo_size" value="<?php echo esc_attr($loader_options['logo_size']); ?>" class="regular-text" />
                    <p class="description"><?php _e('Maximum width of the logo (e.g., 7rem, 100px, etc.).', 'dtr-workbooks'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="animation_speed"><?php _e('Animation Speed', 'dtr-workbooks'); ?></label>
                </th>
                <td>
                    <input type="text" id="animation_speed" name="animation_speed" value="<?php echo esc_attr($loader_options['animation_speed']); ?>" class="regular-text" />
                    <p class="description"><?php _e('Speed of the fade-in animation (e.g., 0.3s, 500ms, etc.).', 'dtr-workbooks'); ?></p>
                </td>
            </tr>
        </table>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
            <h3><?php _e('Preview', 'dtr-workbooks'); ?></h3>
            <button type="button" id="test_loader" class="button button-secondary"><?php _e('Test Loader', 'dtr-workbooks'); ?></button>
            <p class="description"><?php _e('Click to preview how the loader will look with current settings.', 'dtr-workbooks'); ?></p>
        </div>
        
        <?php submit_button(__('Save Loader Settings', 'dtr-workbooks'), 'primary', 'submit_loader_settings'); ?>
    </form>
</div>

<!-- Color Picker and Media Uploader Scripts -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize color pickers
    if (typeof wp !== 'undefined' && wp.colorPicker) {
        $('.color-picker').wpColorPicker();
    }
    
    // Media uploader for logos
    var logoUploader;
    
    function setupUploader(buttonId, inputId, previewId) {
        $('#' + buttonId).click(function(e) {
            e.preventDefault();
            
            if (logoUploader) {
                logoUploader.open();
                return;
            }
            
            logoUploader = wp.media({
                title: 'Choose Logo',
                button: {
                    text: 'Choose Logo'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            logoUploader.on('select', function() {
                var attachment = logoUploader.state().get('selection').first().toJSON();
                $('#' + inputId).val(attachment.url);
                $('#' + previewId).html('<img src="' + attachment.url + '" style="max-width: 100px; height: auto;' + 
                    (previewId.includes('white') ? ' background: #333; padding: 10px;' : '') + '" />');
            });
            
            logoUploader.open();
        });
    }
    
    setupUploader('upload_logo_color', 'logo_color', 'logo_color_preview');
    setupUploader('upload_logo_white', 'logo_white', 'logo_white_preview');
    
    // Test loader functionality
    $('#test_loader').click(function() {
        // Create test loader with current settings
        var testOverlay = $('<div id="test-loader-overlay" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: ' + 
            $('#background_color').val() + '; display: flex; justify-content: center; align-items: center; z-index: 99999;">' +
            '<div style="position: relative; z-index: 2; text-align: center;">' +
            '<img id="test-logo" src="' + $('#logo_color').val() + '" style="max-width: ' + $('#logo_size').val() + '; margin-bottom: 1rem;" />' +
            '<div id="test-message" style="font-size: 1.25rem; color: ' + $('#text_color').val() + '; font-weight: bold; margin-top: 1rem;">' + 
            $('#submitting_text').val() + '</div>' +
            '</div>' +
            '<div id="test-progress" style="position: absolute; bottom: -100%; left: 0; width: 100%; height: 100%; background: ' + 
            $('#progress_color').val() + '; z-index: 1; transition: bottom 2s linear;"></div>' +
            '</div>');
        
        $('body').append(testOverlay);
        
        // Animate progress
        setTimeout(function() {
            $('#test-progress').css('bottom', '0%');
            setTimeout(function() {
                $('#test-logo').attr('src', $('#logo_white').val());
            }, 1000);
            setTimeout(function() {
                $('#test-message').text($('#success_text').val());
                setTimeout(function() {
                    testOverlay.fadeOut(function() {
                        testOverlay.remove();
                    });
                }, 2000);
            }, 1800);
        }, 100);
    });
});
</script>

<style>
.form-table th {
    width: 200px;
}
.color-picker {
    width: 100px !important;
}
#logo_color_preview img,
#logo_white_preview img {
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>
