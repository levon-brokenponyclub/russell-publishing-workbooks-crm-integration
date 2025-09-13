<?php
/**
 * Debug script to examine ACF field structure for webinar posts
 */

// Load WordPress
require_once dirname(__FILE__, 4) . '/wp-config.php';

$post_ids = [161189, 161471, 161472];

foreach ($post_ids as $post_id) {
    echo "\n=== POST ID: $post_id ===\n";
    
    $post = get_post($post_id);
    if ($post) {
        echo "Title: " . $post->post_title . "\n";
        echo "Post Type: " . $post->post_type . "\n";
        echo "Status: " . $post->post_status . "\n";
    } else {
        echo "Post not found!\n";
        continue;
    }
    
    // Check if ACF is available
    if (!function_exists('get_field')) {
        echo "ACF not available!\n";
        continue;
    }
    
    // Get all fields
    echo "\n--- All ACF Fields ---\n";
    $all_fields = get_fields($post_id);
    if ($all_fields) {
        foreach ($all_fields as $key => $value) {
            if (is_array($value)) {
                echo "$key => [array with " . count($value) . " items]\n";
                if ($key === 'webinar_fields') {
                    echo "  webinar_fields contents:\n";
                    foreach ($value as $sub_key => $sub_value) {
                        if (is_array($sub_value)) {
                            echo "    $sub_key => [array]\n";
                        } else {
                            echo "    $sub_key => " . (string)$sub_value . "\n";
                        }
                    }
                }
            } else {
                echo "$key => " . (string)$value . "\n";
            }
        }
    } else {
        echo "No ACF fields found.\n";
    }
    
    // Try specific field lookups
    echo "\n--- Specific Field Lookups ---\n";
    $webinar_fields = get_field('webinar_fields', $post_id);
    echo "get_field('webinar_fields'): " . (is_array($webinar_fields) ? "[array]" : (string)$webinar_fields) . "\n";
    
    if (is_array($webinar_fields)) {
        $workbooks_ref = $webinar_fields['workbooks_reference'] ?? 'NOT FOUND';
        echo "webinar_fields['workbooks_reference']: $workbooks_ref\n";
    }
    
    $direct_workbooks = get_field('workbooks_reference', $post_id);
    echo "get_field('workbooks_reference'): " . (string)$direct_workbooks . "\n";
    
    $direct_workbook = get_field('workbook_reference', $post_id);
    echo "get_field('workbook_reference'): " . (string)$direct_workbook . "\n";
    
    echo "\n--- Post Meta ---\n";
    $meta_keys = ['_webinar_fields_workbooks_reference', 'webinar_fields_workbooks_reference', '_workbooks_reference', 'workbooks_reference'];
    foreach ($meta_keys as $meta_key) {
        $meta_value = get_post_meta($post_id, $meta_key, true);
        echo "$meta_key: " . (string)$meta_value . "\n";
    }
}
