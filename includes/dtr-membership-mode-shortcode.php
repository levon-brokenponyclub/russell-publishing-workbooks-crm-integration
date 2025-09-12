<?php
/**
 * Shortcode to display DTR Membership Registration Mode (Test/Live)
 * Usage: [dtr_membership_mode]
 */
function dtr_membership_mode_shortcode() {
    $options = get_option('dtr_workbooks_options', []);
    $test_mode = !empty($options['test_mode_forms'][15]) && $options['test_mode_forms'][15] == 1;
    $label = $test_mode ? 'LIVE MODE' : 'TEST MODE';
    $color = $test_mode ? '#d9534f' : '#f0ad4e';
    $html = '<div style="padding:8px 0;color:#fff;font-weight:bold;">';
    $html .= '<span style="background:' . $color . ';padding:4px 8px;border-radius:3px;">' . $label . '</span>';
    $html .= '</div>';
    return $html;
}
add_shortcode('dtr_membership_mode', 'dtr_membership_mode_shortcode');
