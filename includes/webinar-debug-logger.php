<?php
/**
 * Webinar Debug Logger
 * Logs to /logs/webinar-debug.log in plugin root
 */
if (!function_exists('dtr_webinar_debug')) {
    function dtr_webinar_debug($message) {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = "[$timestamp] ";
        $log_entry = $prefix . $message . "\n";
        $debug_log_file = dirname(__DIR__, 2) . '/logs/webinar-debug.log';
        $logs_dir = dirname($debug_log_file);
        if (!file_exists($logs_dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($logs_dir);
            } else {
                mkdir($logs_dir, 0755, true);
            }
        }
        @file_put_contents($debug_log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}
