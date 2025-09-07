<?php
/**
 * Ninja Forms Submission Override
 * Fixes array merge issues in Ninja Forms submission processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class DTR_NF_Submission_Override {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Override Ninja Forms submission processing
        add_filter('ninja_forms_pre_process', [$this, 'pre_process_submission'], 1);
        add_filter('ninja_forms_submit_data', [$this, 'prepare_submit_data'], 1);
        
        // Override the process function
        add_filter('ninja_forms_submit_process', [$this, 'override_process'], 1);
        
        // Ensure data is properly formatted before array merge
        add_filter('ninja_forms_submission_array_merge', [$this, 'safe_array_merge'], 1, 2);
    }
    
    /**
     * Pre-process submission data
     */
    public function pre_process_submission($data) {
        if (!is_array($data)) {
            $data = array();
        }
        
        // Ensure required arrays exist
        if (!isset($data['fields']) || !is_array($data['fields'])) {
            $data['fields'] = array();
        }
        
        if (!isset($data['extra']) || !is_array($data['extra'])) {
            $data['extra'] = array();
        }
        
        // Log the pre-processed data
        error_log('NF Pre-process data: ' . print_r($data, true));
        
        return $data;
    }
    
    /**
     * Prepare submission data
     */
    public function prepare_submit_data($data) {
        if (!is_array($data)) {
            $data = array();
        }
        
        // Ensure fields array exists and is properly formatted
        if (!isset($data['fields']) || !is_array($data['fields'])) {
            $data['fields'] = array();
        }
        
        // Initialize missing arrays
        $data['extra'] = isset($data['extra']) ? (array)$data['extra'] : array();
        $data['settings'] = isset($data['settings']) ? (array)$data['settings'] : array();
        
        // Log the prepared data
        error_log('NF Submit data prepared: ' . print_r($data, true));
        
        return $data;
    }
    
    /**
     * Override process function
     */
    public function override_process($data) {
        // Ensure we have arrays to work with
        $data = $this->prepare_submit_data($data);
        
        // Get form ID
        $form_id = absint($data['id']);
        
        // Initialize or ensure fields array exists
        if (!isset($data['fields']) || !is_array($data['fields'])) {
            $data['fields'] = array();
        }
        
        // Initialize processing data
        $data['process_data'] = array(
            'fields' => $data['fields'],
            'extra' => isset($data['extra']) ? $data['extra'] : array()
        );
        
        // Log the processing data
        error_log('NF Process data: ' . print_r($data['process_data'], true));
        
        return $data;
    }
    
    /**
     * Safe array merge
     */
    public function safe_array_merge($array1, $array2) {
        // Ensure both arguments are arrays
        $array1 = is_array($array1) ? $array1 : array();
        $array2 = is_array($array2) ? $array2 : array();
        
        // Log the arrays being merged
        error_log('NF Array merge - Array 1: ' . print_r($array1, true));
        error_log('NF Array merge - Array 2: ' . print_r($array2, true));
        
        // Perform safe merge
        $result = array_merge($array1, $array2);
        
        // Log the result
        error_log('NF Array merge - Result: ' . print_r($result, true));
        
        return $result;
    }
}

// Initialize early
add_action('init', function() {
    DTR_NF_Submission_Override::get_instance();
}, -999);
