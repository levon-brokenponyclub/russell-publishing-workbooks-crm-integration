<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Shortcode to display gated preview content for a post if it is gated
 * Usage: [gated_preview_content id="123"]
 */
function dtr_gated_preview_content_shortcode($atts) {
    if (current_user_can('manage_options')) {
        echo '<pre style="background:#ffe6e6;border:1px solid #ff7875;padding:1em;overflow:auto;">';
        echo '[shortcode-gated-preview-content.php] Shortcode function called. Raw $atts: ' . print_r($atts, true) . "\n";
        echo '</pre>';
    }
    global $post;
    $atts = shortcode_atts([
        'id' => '',
        'post_id' => ''
    ], $atts, 'gated_preview_content');

    $post_id = !empty($atts['id']) ? intval($atts['id']) : (!empty($atts['post_id']) ? intval($atts['post_id']) : (isset($post->ID) ? $post->ID : 0));
    if (!$post_id) return '';

    // Use plugin's own meta fields for gating and preview
    $is_gated = get_post_meta($post_id, 'gate_content', true) == '1';
    $preview_text = get_post_meta($post_id, 'preview_text', true);
    $is_user_logged_in = is_user_logged_in();

    // Print debug info on page for admins
    if (current_user_can('manage_options')) {
        echo '<pre style="background:#fffbe6;border:1px solid #ffe58f;padding:1em;overflow:auto;">';
        echo '[gated_preview_content] CALLED\n';
        echo 'POST ID: ' . esc_html($post_id) . "\n";
        echo 'IS_GATED: ' . var_export($is_gated, true) . "\n";
        echo 'PREVIEW_TEXT: ' . var_export($preview_text, true) . "\n";
        echo 'IS_USER_LOGGED_IN: ' . var_export($is_user_logged_in, true) . "\n";
        echo '</pre>';
    }

    // Debug output for admins only
    if (current_user_can('manage_options')) {
        echo '<pre style="background:#fffbe6;border:1px solid #ffe58f;padding:1em;overflow:auto;">';
        echo 'POST ID: ' . esc_html($post_id) . "\n";
        echo 'GATE_CONTENT: ' . var_export($is_gated, true) . "\n";
        echo 'PREVIEW_TEXT: ' . var_export($preview_text, true) . "\n";
        echo 'USER LOGGED IN: ' . var_export($is_user_logged_in, true) . "\n";
        echo '</pre>';
    }

    if ($is_gated) {
        // Output JS alert on page load
        echo '<script>window.addEventListener("DOMContentLoaded",function(){alert("This post is gated.");});</script>';
    }
    if ($is_gated && !$is_user_logged_in) {
        ob_start();
        if (!empty($preview_text)) {
            echo '<div class="gated-preview-text">' . wp_kses_post($preview_text) . '</div>';
        } else {
            echo '<div class="gated-preview-text">This content is gated. Please sign up to view the full article.</div>';
        }
        echo '<div class="gated-signup-btn-wrapper" style="margin:2em 0;text-align:center;">'
            . '<a href="/free-membership" class="gated-signup-btn" style="display:inline-block;padding:0.75em 2em;background:#0073e6;color:#fff;border-radius:4px;text-decoration:none;font-weight:bold;">Click here to sign up</a>'
            . '</div>';
        return ob_get_clean();
    } elseif ($is_gated && $is_user_logged_in) {
        // Show full content for logged in users
        $main_content = get_post_meta($post_id, 'main_content', true);
        return '<div class="full-page main-body-content access-granted">' . $main_content . '</div>';
    }
    // Not gated, show nothing or optionally show full content
    return '';
}
add_shortcode('gated_preview_content', 'dtr_gated_preview_content_shortcode');
