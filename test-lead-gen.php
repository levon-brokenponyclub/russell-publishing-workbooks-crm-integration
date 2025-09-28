<?php
/**
 * Test file to verify lead generation form shortcode functionality
 * This file can be accessed directly to test the shortcode implementation
 */

// Load WordPress
require_once(dirname(__DIR__, 3) . '/wp-load.php');

// Force user to be logged out for testing
wp_logout();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Lead Generation Form Test</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body>
    <div style="max-width: 800px; margin: 50px auto; padding: 20px;">
        <h1>Lead Generation Form Test</h1>
        
        <h2>Test 1: Not Logged In (should show preview + login/register)</h2>
        <div style="border: 1px solid #ddd; padding: 20px; margin: 20px 0;">
            <?php echo do_shortcode('[dtr_lead_generation_form content_id="123" preview_title="Test Gated Content" preview_text="This is a preview of the gated content that requires registration."]'); ?>
        </div>
        
        <h2>Test 2: With Custom Content</h2>
        <div style="border: 1px solid #ddd; padding: 20px; margin: 20px 0;">
            <?php echo do_shortcode('[dtr_lead_generation_form content_id="456" preview_title="Custom Title" preview_text="Custom preview text" gated_content="<h3>Premium Content</h3><p>This is the full content available after registration.</p>"]'); ?>
        </div>
        
        <hr>
        <p><strong>Debug Info:</strong></p>
        <p>User logged in: <?php echo is_user_logged_in() ? 'Yes' : 'No'; ?></p>
        <p>Current user ID: <?php echo get_current_user_id(); ?></p>
        
        <p><a href="<?php echo wp_login_url(get_permalink()); ?>">Login</a> | <a href="<?php echo wp_registration_url(); ?>">Register</a></p>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>