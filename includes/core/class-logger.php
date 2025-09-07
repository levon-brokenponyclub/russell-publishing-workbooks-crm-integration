<?php
/**
 * DTR Workbooks Logger Class
 * Centralized logging system with multiple output formats and log rotation.
 *
 * @package DTR/Core
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DTR_Logger {
    
    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    
    /**
     * Logger instance
     *
     * @var DTR_Logger
     */
    private static $instance = null;
    
    /**
     * Log directory
     *
     * @var string
     */
    private $log_dir;
    
    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode;
    
    /**
     * Maximum log file size (in bytes)
     *
     * @var int
     */
    private $max_file_size = 10485760; // 10MB
    
    /**
     * Get logger instance
     *
     * @return DTR_Logger
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->log_dir = DTR_WORKBOOKS_LOG_DIR;
        $this->debug_mode = defined('DTR_DEBUG') ? DTR_DEBUG : false;
        
        // Ensure log directory exists
        if (!is_dir($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
    }
    
    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    public function log($level, $message, array $context = []) {
        // Skip debug messages if not in debug mode
        if ($level === self::DEBUG && !$this->debug_mode) {
            return true;
        }
        
        $log_entry = $this->format_log_entry($level, $message, $context);
        
        // Write to file
        $file_result = $this->write_to_file($log_entry, $level);
        
        // Also log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[DTR-{$level}] {$message}");
        }
        
        return $file_result;
    }
    
    /**
     * Log debug message
     *
     * @param string $message Message
     * @param array $context Context
     * @return bool
     */
    public function debug($message, array $context = []) {
        return $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     *
     * @param string $message Message
     * @param array $context Context
     * @return bool
     */
    public function info($message, array $context = []) {
        return $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log warning message
     *
     * @param string $message Message
     * @param array $context Context
     * @return bool
     */
    public function warning($message, array $context = []) {
        return $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Log error message
     *
     * @param string $message Message
     * @param array $context Context
     * @return bool
     */
    public function error($message, array $context = []) {
        return $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Format log entry
     *
     * @param string $level Log level
     * @param string $message Message
     * @param array $context Context
     * @return string Formatted log entry
     */
    private function format_log_entry($level, $message, array $context = []) {
        $timestamp = current_time('Y-m-d H:i:s');
        $memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
        
        $entry = "[{$timestamp}] [{$level}] [Memory: {$memory_usage}MB] {$message}";
        
        if (!empty($context)) {
            $entry .= ' Context: ' . wp_json_encode($context);
        }
        
        return $entry . PHP_EOL;
    }
    
    /**
     * Write log entry to file
     *
     * @param string $log_entry Formatted log entry
     * @param string $level Log level
     * @return bool Success status
     */
    private function write_to_file($log_entry, $level) {
        $log_file = $this->get_log_file($level);
        
        // Check file size and rotate if necessary
        if (file_exists($log_file) && filesize($log_file) > $this->max_file_size) {
            $this->rotate_log_file($log_file);
        }
        
        $result = file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        return $result !== false;
    }
    
    /**
     * Get log file path for level
     *
     * @param string $level Log level
     * @return string Log file path
     */
    private function get_log_file($level) {
        $date = date('Y-m-d');
        
        // Separate error logs from others
        if (in_array($level, [self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY])) {
            return $this->log_dir . "dtr-errors-{$date}.log";
        }
        
        return $this->log_dir . "dtr-{$date}.log";
    }
    
    /**
     * Rotate log file when it gets too large
     *
     * @param string $log_file Log file path
     * @return void
     */
    private function rotate_log_file($log_file) {
        $backup_file = $log_file . '.bak';
        
        // Remove old backup if exists
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        
        // Move current log to backup
        rename($log_file, $backup_file);
    }
    
    /**
     * Get recent log entries
     *
     * @param int $lines Number of lines to retrieve
     * @param string $level Log level filter
     * @return array Log entries
     */
    public function get_recent_logs($lines = 100, $level = null) {
        $logs = [];
        $log_files = glob($this->log_dir . '*.log');
        
        // Sort files by modification time (newest first)
        usort($log_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $total_lines = 0;
        
        foreach ($log_files as $file) {
            if ($total_lines >= $lines) {
                break;
            }
            
            $file_logs = $this->read_log_file($file, $lines - $total_lines, $level);
            $logs = array_merge($logs, $file_logs);
            $total_lines += count($file_logs);
        }
        
        return array_slice($logs, 0, $lines);
    }
    
    /**
     * Read log file with optional filtering
     *
     * @param string $file Log file path
     * @param int $max_lines Maximum lines to read
     * @param string $level Level filter
     * @return array Log entries
     */
    private function read_log_file($file, $max_lines, $level = null) {
        if (!file_exists($file)) {
            return [];
        }
        
        $lines = [];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            return [];
        }
        
        while (($line = fgets($handle)) !== false && count($lines) < $max_lines) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // Filter by level if specified
            if ($level && strpos($line, "[{$level}]") === false) {
                continue;
            }
            
            $lines[] = [
                'timestamp' => $this->extract_timestamp($line),
                'level' => $this->extract_level($line),
                'message' => $this->extract_message($line),
                'raw' => $line
            ];
        }
        
        fclose($handle);
        
        return array_reverse($lines); // Most recent first
    }
    
    /**
     * Extract timestamp from log line
     *
     * @param string $line Log line
     * @return string Timestamp
     */
    private function extract_timestamp($line) {
        preg_match('/\[([\d\-\s:]+)\]/', $line, $matches);
        return $matches[1] ?? '';
    }
    
    /**
     * Extract level from log line
     *
     * @param string $line Log line
     * @return string Level
     */
    private function extract_level($line) {
        preg_match('/\] \[([^\]]+)\] \[/', $line, $matches);
        return $matches[1] ?? '';
    }
    
    /**
     * Extract message from log line
     *
     * @param string $line Log line
     * @return string Message
     */
    private function extract_message($line) {
        // Remove timestamp, level, and memory info
        $message = preg_replace('/^\[[\d\-\s:]+\] \[[^\]]+\] \[Memory: [^\]]+\] /', '', $line);
        return $message;
    }
    
    /**
     * Clear all log files
     *
     * @return bool Success status
     */
    public function clear_logs() {
        $log_files = glob($this->log_dir . '*.log*');
        $success = true;
        
        foreach ($log_files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get log file sizes
     *
     * @return array File sizes
     */
    public function get_log_file_sizes() {
        $log_files = glob($this->log_dir . '*.log*');
        $sizes = [];
        
        foreach ($log_files as $file) {
            $filename = basename($file);
            $sizes[$filename] = [
                'size' => filesize($file),
                'size_formatted' => size_format(filesize($file)),
                'modified' => filemtime($file)
            ];
        }
        
        return $sizes;
    }
}