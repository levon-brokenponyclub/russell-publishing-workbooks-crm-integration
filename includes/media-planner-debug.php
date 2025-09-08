<?php
/**
 * Media Planner 2025 Form Handler Debug Log
 *
 * This file is used to log all hidden fields and values, and all fields as they are entered for the Media Planner 2025 form submissions.
 *
 * Log format: [timestamp] [type] [field_name] => [value]
 */

define('DTR_MEDIA_PLANNER_DEBUG_LOG', __DIR__ . '/media-planner-debug.log');

function dtr_media_planner_debug_log($type, $fields) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entries = [];
    foreach ($fields as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $log_entries[] = "[$timestamp] [$type] [$key] => $value";
    }
    $log_content = implode("\n", $log_entries) . "\n";
    file_put_contents(DTR_MEDIA_PLANNER_DEBUG_LOG, $log_content, FILE_APPEND);
}

// Usage example:
// dtr_media_planner_debug_log('hidden', $_POST['hidden_fields']);
// dtr_media_planner_debug_log('entered', $_POST);
