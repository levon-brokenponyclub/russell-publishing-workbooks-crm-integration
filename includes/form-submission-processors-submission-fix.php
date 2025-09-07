<?php
/**
 * Ninja Forms Submission Handler Fix
 * Fixes array merge and field handling issues in Ninja Forms submissions
 */

if (!defined('ABSPATH')) {
    exit;
}

class DTR_NF_Submission_Fix {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize fixes
        add_action('init', [$this, 'initialize'], 0);
        
        // Add submission data filters
        add_filter('ninja_forms_submit_data', [$this, 'prepare_submit_data'], 5);
        add_filter('ninja_forms_pre_process', [$this, 'ensure_fields_array'], 5);
        add_filter('ninja_forms_submission_data', [$this, 'normalize_submission_data'], 5);
        
        // Fix array merge issues
        add_filter('ninja_forms_submission_array_merge', [$this, 'fix_array_merge'], 10, 2);
    }
    
    /**
     * Initialize fixes early
     */
    public function initialize() {
        // Ensure text domains are loaded at the right time
        add_action('plugins_loaded', function() {
            load_plugin_textdomain('ninja-forms');
            load_plugin_textdomain('ninja-forms-uploads');
        }, 0);
    }
    
    /**
     * Prepare submission data
     */
    public function prepare_submit_data($data) {
        if (!is_array($data)) {
            $data = [];
        }
        
        // Ensure required arrays exist
        $data['fields'] = isset($data['fields']) ? $data['fields'] : [];
        $data['extra'] = isset($data['extra']) ? $data['extra'] : [];
        $data['settings'] = isset($data['settings']) ? $data['settings'] : [];
        
        // Log the data for debugging
        error_log('Ninja Forms Submit Data (After Preparation): ' . print_r($data, true));
        
        return $data;
    }
    
    /**
     * Ensure fields array exists and is properly formatted
     */
    public function ensure_fields_array($data) {
        if (!isset($data['fields']) || !is_array($data['fields'])) {
            $data['fields'] = [];
        }
        
        // Transform any null values to empty arrays
        foreach ($data['fields'] as $key => $field) {
            if (is_null($field)) {
                $data['fields'][$key] = [];
            }
        }
        
        return $data;
    }
    
    /**
     * Normalize submission data
     */
    public function normalize_submission_data($data) {
        // If data is null, initialize it
        if (is_null($data)) {
            $data = [
                'fields' => [],
                'extra' => [],
                'settings' => []
            ];
        }
        
        // Ensure fields is an array
        if (!isset($data['fields']) || !is_array($data['fields'])) {
            $data['fields'] = [];
        }
        
        // Fix any null values in fields
        foreach ($data['fields'] as $key => $value) {
            if (is_null($value)) {
                $data['fields'][$key] = [];
            }
        }
        
        return $data;
    }
    
    /**
     * Fix array merge issues
     */
    public function fix_array_merge($array1, $array2) {
        // Ensure both arguments are arrays
        $array1 = (is_array($array1)) ? $array1 : [];
        $array2 = (is_array($array2)) ? $array2 : [];
        
        // Log merge operation for debugging
        error_log('Ninja Forms Array Merge - Array 1: ' . print_r($array1, true));
        error_log('Ninja Forms Array Merge - Array 2: ' . print_r($array2, true));
        
        // Perform merge
        $result = array_merge($array1, $array2);
        
        // Log result
        error_log('Ninja Forms Array Merge - Result: ' . print_r($result, true));
        
        return $result;
    }
}

// Initialize the fix
add_action('plugins_loaded', function() {
    DTR_NF_Submission_Fix::get_instance();
}, -999);
