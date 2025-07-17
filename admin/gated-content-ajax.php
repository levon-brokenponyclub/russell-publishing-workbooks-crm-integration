<?php
if (!defined('ABSPATH')) exit;

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
    $posts = get_posts($args);
    $data = array();
    foreach ($posts as $post) {
        // Get ACF group field
        $acf_group = get_field('restricted_content_fields', $post->ID);
        $workbooks_reference = isset($acf_group['reference']) ? $acf_group['reference'] : '';
        $campaign_reference = isset($acf_group['campaign_reference']) ? $acf_group['campaign_reference'] : '';
        $ninja_form_id = isset($acf_group['select_lead_form']) ? $acf_group['select_lead_form'] : '';
        $data[] = array(
            'post_id' => $post->ID,
            'title' => $post->post_title,
            'workbooks_reference' => $workbooks_reference,
            'campaign_reference' => $campaign_reference,
            'ninja_form_id' => $ninja_form_id,
            'ninja_form_title' => '', // Optionally fetch Ninja Form title
            'edit_url' => get_edit_post_link($post->ID)
        );
    }
    // Optionally fetch Ninja Form titles
    if (function_exists('ninja_forms_get_all_forms')) {
        $forms = ninja_forms_get_all_forms();
        $form_titles = array();
        foreach ($forms as $form) {
            $form_titles[$form['id']] = $form['title'];
        }
        foreach ($data as &$item) {
            if (!empty($item['ninja_form_id']) && isset($form_titles[$item['ninja_form_id']])) {
                $item['ninja_form_title'] = $form_titles[$item['ninja_form_id']];
            }
        }
    }
    wp_send_json_success($data);
}

// Load gated content settings for a post
add_action('wp_ajax_load_gated_content_settings', 'dtr_load_gated_content_settings');
function dtr_load_gated_content_settings() {
    check_ajax_referer('gated_content_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }
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
}

// Save gated content settings
add_action('wp_ajax_save_gated_content_settings', 'dtr_save_gated_content_settings');
function dtr_save_gated_content_settings() {
    check_ajax_referer('gated_content_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        error_log('GATED PLUGIN: Invalid post ID in save_gated_content_settings');
        wp_send_json_error('Invalid post ID');
    }
    error_log('GATED PLUGIN: Saving gated content for post_id=' . $post_id . ' POST: ' . print_r($_POST, true));
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
    error_log('GATED PLUGIN: update_post_meta results: ' . print_r($results, true));
    wp_send_json_success();
}

// Clear gated content settings
add_action('wp_ajax_clear_gated_content_settings', 'dtr_clear_gated_content_settings');
function dtr_clear_gated_content_settings() {
    check_ajax_referer('gated_content_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }
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
    wp_send_json_success();
}
