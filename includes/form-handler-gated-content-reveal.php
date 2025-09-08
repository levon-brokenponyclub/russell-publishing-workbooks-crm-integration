<?php
/**
 * Gated Content Form Handler & Ninja Forms Array Structure Patch
 * Handles all gated content form submissions and proactively prevents array_merge errors from malformed data.
 * Excludes Form ID 15 (registration) which now uses ninja-forms-membership-registration.php independently.
 *
 * @package DTR/GatedContent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Initialize gated content hooks
 */
function dtr_init_gated_content_hooks() {
    // Early intervention hooks to prevent array_merge errors
    add_action('ninja_forms_before_form_display', 'dtr_patch_form_structure_early', 5);
    add_action('ninja_forms_process', 'dtr_patch_form_data_before_processing', 1);
    
    // Main processing hook
    add_action('ninja_forms_after_submission', 'dtr_handle_gated_content_submission', 10, 1);
    
    // Additional safety nets
    add_filter('ninja_forms_render_fields', 'dtr_ensure_field_structure', 10, 2);
    add_filter('ninja_forms_field_template_file_paths', 'dtr_patch_field_templates', 10, 1);
}

/**
 * Early form structure patching to prevent fatal errors
 *
 * @param int $form_id Form ID
 * @return void
 */
function dtr_patch_form_structure_early($form_id) {
    if (!$form_id || !is_numeric($form_id)) {
        return;
    }

    dtr_log_gated("Early structure patch for form ID: {$form_id}");
    
    // Ensure global form data structure exists
    global $ninja_forms_processing;
    if (!is_array($ninja_forms_processing)) {
        $ninja_forms_processing = [];
    }
    
    // Initialize form-specific structure
    if (!isset($ninja_forms_processing[$form_id])) {
        $ninja_forms_processing[$form_id] = [
            'fields' => [],
            'form_settings' => [],
            'extra' => []
        ];
    }
}

/**
 * Patch form data structure before processing to prevent array_merge errors
 *
 * @param array $form_data Form data being processed
 * @return void
 */
function dtr_patch_form_data_before_processing($form_data) {
    if (!is_array($form_data)) {
        dtr_log_gated("Warning: Non-array form data detected, initializing as array");
        $form_data = [];
    }

    // Ensure critical array structures exist
    $required_structures = ['fields', 'settings', 'form_settings'];
    foreach ($required_structures as $structure) {
        if (!isset($form_data[$structure]) || !is_array($form_data[$structure])) {
            $form_data[$structure] = [];
            dtr_log_gated("Initialized missing {$structure} structure");
        }
    }

    // Fix malformed field data
    if (isset($form_data['fields'])) {
        $form_data['fields'] = dtr_sanitize_field_structure($form_data['fields']);
    }
}

/**
 * Ensure field structure integrity
 *
 * @param array $fields Form fields
 * @param int $form_id Form ID
 * @return array Sanitized fields
 */
function dtr_ensure_field_structure($fields, $form_id) {
    if (!is_array($fields)) {
        dtr_log_gated("Field structure corrupted for form {$form_id}, initializing");
        return [];
    }

    return dtr_sanitize_field_structure($fields);
}

/**
 * Sanitize field structure to prevent array_merge errors
 *
 * @param mixed $fields Field data to sanitize
 * @return array Sanitized field array
 */
function dtr_sanitize_field_structure($fields) {
    if (!is_array($fields)) {
        return [];
    }

    $sanitized = [];
    foreach ($fields as $key => $field) {
        if (!is_array($field)) {
            // Convert non-array field to proper structure
            $sanitized[$key] = [
                'id' => $key,
                'value' => is_scalar($field) ? $field : '',
                'type' => 'textbox',
                'label' => 'Field ' . $key
            ];
            continue;
        }

        // Ensure required field properties exist
        $field_defaults = [
            'id' => $key,
            'type' => 'textbox',
            'value' => '',
            'label' => '',
            'key' => ''
        ];

        $sanitized[$key] = array_merge($field_defaults, $field);
    }

    return $sanitized;
}

/**
 * Main gated content submission handler
 *
 * @param array $form_data Form submission data
 * @return void
 */
function dtr_handle_gated_content_submission($form_data) {
    // TEMP: Confirm handler is called and log form ID
    $form_id = isset($form_data['form_id']) ? $form_data['form_id'] : (isset($form_data['id']) ? $form_data['id'] : 'unknown');
    if (defined('DTR_WORKBOOKS_LOG_DIR')) {
        $file = DTR_WORKBOOKS_LOG_DIR . 'live-webinar-debug.log';
        file_put_contents($file, date('c') . " -- dtr_handle_gated_content_submission called for form_id=$form_id\n", FILE_APPEND | LOCK_EX);
    }
    // Output to browser console for debugging
    add_action('wp_footer', function() use ($form_id) {
        ?>
        <script>console.log('[DTR] dtr_handle_gated_content_submission called for form_id: <?php echo addslashes($form_id); ?>');</script>
        <?php
    }, 99);
    $debug_id = 'GATED-' . uniqid();
    
    if (!is_array($form_data) || empty($form_data)) {
        dtr_log_gated("Invalid form data received", $debug_id);
        return;
    }

    $form_id = dtr_extract_form_id($form_data);
    if (!$form_id) {
        dtr_log_gated("Could not determine form ID", $debug_id);
        return;
    }

    dtr_log_gated("Processing submission for form ID: {$form_id}", $debug_id);

    // Skip registration form (handled separately)
    if ($form_id == 15) {
        dtr_log_gated("Skipping form ID 15 (registration) - handled by separate module", $debug_id);
        return;
    }

    // Route to appropriate handler based on form type
    $result = dtr_route_form_submission($form_data, $form_id, $debug_id);
    
    if ($result) {
        dtr_log_gated("Form submission processed successfully", $debug_id);
    } else {
        dtr_log_gated("Form submission processing failed", $debug_id);
    }
}

/**
 * Extract form ID from submission data
 *
 * @param array $form_data Form submission data
 * @return int|null Form ID or null if not found
 */
function dtr_extract_form_id($form_data) {
    // Check various possible locations for form ID
    $possible_keys = ['form_id', 'id', 'form_settings.form_id'];
    
    foreach ($possible_keys as $key) {
        if (strpos($key, '.') !== false) {
            // Handle nested keys
            $keys = explode('.', $key);
            $value = $form_data;
            foreach ($keys as $nested_key) {
                if (isset($value[$nested_key])) {
                    $value = $value[$nested_key];
                } else {
                    $value = null;
                    break;
                }
            }
            if ($value && is_numeric($value)) {
                return intval($value);
            }
        } else {
            // Handle direct keys
            if (isset($form_data[$key]) && is_numeric($form_data[$key])) {
                return intval($form_data[$key]);
            }
        }
    }

    return null;
}

/**
 * Route form submission to appropriate handler
 *
 * @param array $form_data Form submission data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Success status
 */
function dtr_route_form_submission($form_data, $form_id, $debug_id) {
    // Define form routing
    $form_routes = [
        2 => 'webinar',    // Webinar registration
        31 => 'lead_gen'   // Lead generation
    ];

    $form_type = $form_routes[$form_id] ?? 'generic';
    
    dtr_log_gated("Routing form ID {$form_id} as type: {$form_type}", $debug_id);

    switch ($form_type) {
        case 'webinar':
            return dtr_handle_webinar_submission($form_data, $form_id, $debug_id);
            
        case 'lead_gen':
            return dtr_handle_lead_gen_submission($form_data, $form_id, $debug_id);
            
        case 'generic':
        default:
            return dtr_handle_generic_submission($form_data, $form_id, $debug_id);
    }
}

/**
 * Handle webinar form submissions
 *
 * @param array $form_data Form submission data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Success status
 */
function dtr_handle_webinar_submission($form_data, $form_id, $debug_id) {
    dtr_log_gated("Processing webinar submission", $debug_id);
    
    if (function_exists('dtr_process_webinar_registration')) {
        return dtr_process_webinar_registration($form_data, $form_id, $debug_id);
    } else {
        dtr_log_gated("Webinar handler function not available", $debug_id);
        return false;
    }
}

/**
 * Handle lead generation form submissions
 *
 * @param array $form_data Form submission data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Success status
 */
function dtr_handle_lead_gen_submission($form_data, $form_id, $debug_id) {
    dtr_log_gated("Processing lead generation submission", $debug_id);
    
    if (function_exists('dtr_process_lead_generation')) {
        return dtr_process_lead_generation($form_data, $form_id, $debug_id);
    } else {
        dtr_log_gated("Lead generation handler function not available", $debug_id);
        return false;
    }
}

/**
 * Handle generic form submissions
 *
 * @param array $form_data Form submission data
 * @param int $form_id Form ID
 * @param string $debug_id Debug identifier
 * @return bool Success status
 */
function dtr_handle_generic_submission($form_data, $form_id, $debug_id) {
    dtr_log_gated("Processing generic form submission", $debug_id);
    
    // Basic processing for unknown form types
    $email = dtr_extract_email_from_form($form_data);
    if ($email) {
        dtr_log_gated("Email captured: {$email}", $debug_id);
        // Add to basic mailing list or perform other generic actions
        return true;
    }
    
    dtr_log_gated("No email found in generic submission", $debug_id);
    return false;
}

/**
 * Extract email from form data
 *
 * @param array $form_data Form submission data
 * @return string|null Email address or null if not found
 */
function dtr_extract_email_from_form($form_data) {
    $email_keys = ['email', 'email_address', 'user_email'];
    
    foreach ($email_keys as $key) {
        if (isset($form_data[$key]) && is_email($form_data[$key])) {
            return sanitize_email($form_data[$key]);
        }
    }
    
    // Check in fields array
    if (isset($form_data['fields']) && is_array($form_data['fields'])) {
        foreach ($form_data['fields'] as $field) {
            if (isset($field['type']) && $field['type'] === 'email' && 
                isset($field['value']) && is_email($field['value'])) {
                return sanitize_email($field['value']);
            }
        }
    }
    
    return null;
}

/**
 * Patch field template file paths to prevent template errors
 *
 * @param array $paths Template file paths
 * @return array Patched paths
 */
function dtr_patch_field_templates($paths) {
    if (!is_array($paths)) {
        return [];
    }
    
    // Ensure default template directory exists in path
    $default_path = plugin_dir_path(__FILE__) . 'templates/';
    if (!in_array($default_path, $paths) && is_dir($default_path)) {
        $paths[] = $default_path;
    }
    
    return $paths;
}

/**
 * Log gated content debug information
 *
 * @param string $message Debug message
 * @param string $debug_id Debug identifier
 * @return void
 */
function dtr_log_gated($message, $debug_id = '') {
    if (!function_exists('error_log')) {
        return;
    }

    $timestamp = current_time('Y-m-d H:i:s');
    $prefix = $debug_id ? "[{$debug_id}]" : '[DTR-Gated]';
    $formatted_message = "{$timestamp} {$prefix} {$message}";
    
    error_log($formatted_message);

    // Also log to custom DTR log if function exists
    if (function_exists('dtr_custom_log')) {
        dtr_custom_log($formatted_message);
    }

    // Additionally, write to live-webinar-debug.log if this is a webinar submission (Form ID 2)
    if (strpos($formatted_message, 'form ID: 2') !== false || strpos($formatted_message, 'form ID 2') !== false || strpos($formatted_message, 'webinar') !== false) {
        if (defined('DTR_WORKBOOKS_LOG_DIR')) {
            $file = DTR_WORKBOOKS_LOG_DIR . 'live-webinar-debug.log';
            file_put_contents($file, $formatted_message . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}

// Initialize hooks when this file is loaded
dtr_init_gated_content_hooks();