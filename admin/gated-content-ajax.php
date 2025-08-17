<?php
if (!defined('ABSPATH')) exit;

// Debug helper: log errors to admin/gated-content-debug.log
function dtr_gated_content_debug_log($message, $data = null) {
    $log_file = __DIR__ . '/gated-content-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $message";
    if ($data !== null) {
        $entry .= ' | ' . (is_string($data) ? $data : print_r($data, true));
    }
    // Ensure the logs directory exists (just in case)
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    file_put_contents($log_file, $entry . PHP_EOL, FILE_APPEND);
}

// Load current gated articles
add_action('wp_ajax_load_current_gated_articles', 'dtr_load_current_gated_articles');
function dtr_load_current_gated_articles() {
    check_ajax_referer('gated_content_nonce', 'nonce');
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'articles';
    $args = array(
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => 'restrict_post',
                'value' => '1',
                'compare' => '='
            )
        )
    );
    try {
        $posts = get_posts($args);
        $data = array();
        foreach ($posts as $post) {
            // Get ACF group field
            $acf_group = get_field('restricted_content_fields', $post->ID);
            $workbooks_reference = '';
            $campaign_reference = '';
            if (is_array($acf_group)) {
                $workbooks_reference = isset($acf_group['reference']) ? $acf_group['reference'] : '';
                $campaign_reference = isset($acf_group['campaign_reference']) ? $acf_group['campaign_reference'] : '';
            }
            $data[] = array(
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'workbooks_reference' => $workbooks_reference,
                'campaign_reference' => $campaign_reference,
                'edit_url' => get_edit_post_link($post->ID)
            );
        }
        wp_send_json_success($data);
    } catch (Exception $e) {
        dtr_gated_content_debug_log('Error in dtr_load_current_gated_articles', $e->getMessage());
        wp_send_json_error('Error loading gated articles');
    }
}

// Load gated content settings for a post
add_action('wp_ajax_load_gated_content_settings', 'dtr_load_gated_content_settings');
function dtr_load_gated_content_settings() {
    check_ajax_referer('gated_content_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        dtr_gated_content_debug_log('Invalid post ID in dtr_load_gated_content_settings', $_POST);
        wp_send_json_error('Invalid post ID');
    }
    try {
        $data = array(
            'post_title' => get_the_title($post_id),
            'gate_content' => get_post_meta($post_id, 'gate_content', true),
            'preview_text' => get_post_meta($post_id, 'preview_text', true),
            'preview_image' => get_post_meta($post_id, 'preview_image', true),
            'preview_video' => get_post_meta($post_id, 'preview_video', true),
            'preview_gallery' => get_post_meta($post_id, 'preview_gallery', true),
            'preview_button_text' => get_post_meta($post_id, 'preview_button_text', true),
            'preview_button_url' => get_post_meta($post_id, 'preview_button_url', true),
            'preview_button_style' => get_post_meta($post_id, 'preview_button_style', true),
            'workbooks_reference' => get_post_meta($post_id, 'workbooks_reference', true),
            'campaign_reference' => get_post_meta($post_id, 'campaign_reference', true),
            'redirect_url' => get_post_meta($post_id, 'redirect_url', true),
            'ninja_form_id' => get_post_meta($post_id, 'ninja_form_id', true)
        );
        wp_send_json_success($data);
    } catch (Exception $e) {
        dtr_gated_content_debug_log('Error in dtr_load_gated_content_settings', $e->getMessage());
        wp_send_json_error('Error loading gated content settings');
    }
}

// Save gated content settings
add_action('wp_ajax_save_gated_content_settings', 'dtr_save_gated_content_settings');
function dtr_save_gated_content_settings() {
    check_ajax_referer('gated_content_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        dtr_gated_content_debug_log('Invalid post ID in dtr_save_gated_content_settings', $_POST);
        wp_send_json_error('Invalid post ID');
    }
    try {
        dtr_gated_content_debug_log('Saving gated content for post_id=' . $post_id, $_POST);
        $results = [];
        $results['gate_content'] = update_post_meta($post_id, 'gate_content', isset($_POST['gate_content']) ? intval($_POST['gate_content']) : 0);
        $results['preview_text'] = update_post_meta($post_id, 'preview_text', isset($_POST['preview_text']) ? wp_kses_post($_POST['preview_text']) : '');
        $results['preview_image'] = update_post_meta($post_id, 'preview_image', isset($_POST['preview_image']) ? sanitize_text_field($_POST['preview_image']) : '');
        $results['preview_video'] = update_post_meta($post_id, 'preview_video', isset($_POST['preview_video']) ? esc_url_raw($_POST['preview_video']) : '');
        $results['preview_gallery'] = update_post_meta($post_id, 'preview_gallery', isset($_POST['preview_gallery']) ? sanitize_text_field($_POST['preview_gallery']) : '');
        $results['preview_button_text'] = update_post_meta($post_id, 'preview_button_text', isset($_POST['preview_button_text']) ? sanitize_text_field($_POST['preview_button_text']) : '');
        $results['preview_button_url'] = update_post_meta($post_id, 'preview_button_url', isset($_POST['preview_button_url']) ? esc_url_raw($_POST['preview_button_url']) : '');
        $results['preview_button_style'] = update_post_meta($post_id, 'preview_button_style', isset($_POST['preview_button_style']) ? sanitize_text_field($_POST['preview_button_style']) : '');
        $results['workbooks_reference'] = update_post_meta($post_id, 'workbooks_reference', isset($_POST['workbooks_reference']) ? sanitize_text_field($_POST['workbooks_reference']) : '');
        $results['campaign_reference'] = update_post_meta($post_id, 'campaign_reference', isset($_POST['campaign_reference']) ? sanitize_text_field($_POST['campaign_reference']) : '');
        $results['redirect_url'] = update_post_meta($post_id, 'redirect_url', isset($_POST['redirect_url']) ? esc_url_raw($_POST['redirect_url']) : '');
        $results['ninja_form_id'] = update_post_meta($post_id, 'ninja_form_id', isset($_POST['ninja_form_id']) ? intval($_POST['ninja_form_id']) : '');
        dtr_gated_content_debug_log('update_post_meta results', $results);
        wp_send_json_success();
    } catch (Exception $e) {
        dtr_gated_content_debug_log('Error in dtr_save_gated_content_settings', $e->getMessage());
        wp_send_json_error('Error saving gated content settings');
    }
}

// Clear gated content settings
add_action('wp_ajax_clear_gated_content_settings', 'dtr_clear_gated_content_settings');
function dtr_clear_gated_content_settings() {
    check_ajax_referer('gated_content_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        dtr_gated_content_debug_log('Invalid post ID in dtr_clear_gated_content_settings', $_POST);
        wp_send_json_error('Invalid post ID');
    }
    try {
        delete_post_meta($post_id, 'gate_content');
        delete_post_meta($post_id, 'preview_text');
        delete_post_meta($post_id, 'preview_image');
        delete_post_meta($post_id, 'preview_video');
        delete_post_meta($post_id, 'preview_gallery');
        delete_post_meta($post_id, 'preview_button_text');
        delete_post_meta($post_id, 'preview_button_url');
        delete_post_meta($post_id, 'preview_button_style');
        delete_post_meta($post_id, 'workbooks_reference');
        delete_post_meta($post_id, 'campaign_reference');
        delete_post_meta($post_id, 'redirect_url');
        delete_post_meta($post_id, 'ninja_form_id');
        dtr_gated_content_debug_log('Cleared gated content for post_id=' . $post_id);
        wp_send_json_success();
    } catch (Exception $e) {
        dtr_gated_content_debug_log('Error in dtr_clear_gated_content_settings', $e->getMessage());
        wp_send_json_error('Error clearing gated content settings');
    }
}

// Fetch Workbooks Queues (for admin UI button)
add_action('wp_ajax_dtr_fetch_workbooks_queues', function() {
    dtr_gated_content_debug_log('AJAX: dtr_fetch_workbooks_queues called');
    check_ajax_referer('workbooks_nonce', 'nonce');
    try {
        if (!function_exists('get_workbooks_instance')) {
            dtr_gated_content_debug_log('AJAX: get_workbooks_instance missing');
            wp_send_json_error('Workbooks API unavailable');
        }
        $workbooks = get_workbooks_instance();
        if (!$workbooks) {
            dtr_gated_content_debug_log('AJAX: Workbooks API not available');
            wp_send_json_error('Workbooks API not available');
        }
        if (!function_exists('dtr_get_event_registration_field_mapping_queues')) {
            $queues_file = dirname(__FILE__) . '/../includes/get-workbooks-queues.php';
            dtr_gated_content_debug_log('AJAX: require_once ' . $queues_file);
            if (file_exists($queues_file)) {
                require_once $queues_file;
            } else {
                dtr_gated_content_debug_log('AJAX: queues file not found!');
                wp_send_json_error('Queues file missing');
            }
        }
        $queues = dtr_get_event_registration_field_mapping_queues($workbooks);
        dtr_gated_content_debug_log('AJAX: Queues result', $queues);
        if ($queues && is_array($queues) && count($queues)) {
            dtr_gated_content_debug_log('AJAX: Queues found and returned');
            wp_send_json_success($queues);
        } else {
            dtr_gated_content_debug_log('AJAX: No queues found or API error');
            wp_send_json_error('No queues found or API error');
        }
    } catch (Exception $e) {
        dtr_gated_content_debug_log('AJAX: Exception in dtr_fetch_workbooks_queues', $e->getMessage());
        wp_send_json_error('Error fetching queues: ' . $e->getMessage());
    }
});
?>