<?php
/**
 * ACF Questions Handler for Ninja Forms
 * Appends form questions to the 'add_questions' repeater for forms 2 (Webinar) and 31 (Lead Gen).
 * Ignores empty and duplicate answers with comprehensive logging.
 *
 * @package DTR/ACF
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Initialize ACF questions hooks
 */
function dtr_init_acf_questions_hooks() {
    add_action('ninja_forms_after_submission', 'dtr_process_acf_questions_from_ninja_forms', 20, 1);
}

/**
 * Process ACF questions from Ninja Forms submission
 *
 * @param array $form_data Form submission data
 * @return void
 */
function dtr_process_acf_questions_from_ninja_forms($form_data) {
    $debug_id = 'ACF-Q-' . uniqid();
    
    if (!is_array($form_data) || empty($form_data)) {
        dtr_log_acf_questions("Invalid form data received", $debug_id);
        return;
    }

    // Check if ACF is available
    if (!function_exists('get_field') || !function_exists('update_field')) {
        dtr_log_acf_questions("ACF functions not available", $debug_id);
        return;
    }

    $form_id = dtr_extract_form_id_from_acf_data($form_data);
    if (!$form_id) {
        dtr_log_acf_questions("Could not determine form ID", $debug_id);
        return;
    }

    // Only process specific forms
    $supported_forms = [2, 31]; // Webinar and Lead Gen forms
    if (!in_array($form_id, $supported_forms)) {
        dtr_log_acf_questions("Form ID {$form_id} not supported for ACF questions", $debug_id);
        return;
    }

    dtr_log_acf_questions("Processing ACF questions for form ID: {$form_id}", $debug_id);

    // Extract questions from form data
    $new_questions = dtr_extract_questions_from_ninja_form($form_data, $debug_id);
    if (empty($new_questions)) {
        dtr_log_acf_questions("No questions found in form submission", $debug_id);
        return;
    }

    // Get existing ACF questions
    $existing_questions = get_field('add_questions') ?: [];
    if (!is_array($existing_questions)) {
        $existing_questions = [];
        dtr_log_acf_questions("Initialized empty questions array", $debug_id);
    }

    dtr_log_acf_questions("Found " . count($existing_questions) . " existing questions", $debug_id);
    dtr_log_acf_questions("Adding " . count($new_questions) . " new questions", $debug_id);

    // Merge questions avoiding duplicates
    $updated_questions = dtr_merge_acf_questions($existing_questions, $new_questions, $debug_id);

    // Update ACF field
    $update_result = update_field('add_questions', $updated_questions);
    
    if ($update_result) {
        $added_count = count($updated_questions) - count($existing_questions);
        dtr_log_acf_questions("Successfully updated ACF questions. Added: {$added_count}, Total: " . count($updated_questions), $debug_id);
    } else {
        dtr_log_acf_questions("Failed to update ACF questions field", $debug_id);
    }
}

/**
 * Extract form ID from ACF-specific form data
 *
 * @param array $form_data Form submission data
 * @return int|null Form ID or null if not found
 */
function dtr_extract_form_id_from_acf_data($form_data) {
    // Multiple possible locations for form ID
    $form_id_sources = [
        'form_id',
        'id',
        'form_settings.form_id',
        'settings.id'
    ];

    foreach ($form_id_sources as $source) {
        if (strpos($source, '.') !== false) {
            // Handle nested array access
            $keys = explode('.', $source);
            $value = $form_data;
            
            foreach ($keys as $key) {
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    $value = null;
                    break;
                }
            }
            
            if ($value && is_numeric($value)) {
                return intval($value);
            }
        } else {
            // Direct array access
            if (isset($form_data[$source]) && is_numeric($form_data[$source])) {
                return intval($form_data[$source]);
            }
        }
    }

    return null;
}

/**
 * Extract questions from Ninja Forms submission data
 *
 * @param array $form_data Form submission data
 * @param string $debug_id Debug identifier
 * @return array Array of questions and answers
 */
function dtr_extract_questions_from_ninja_form($form_data, $debug_id) {
    $questions = [];
    
    if (!isset($form_data['fields']) || !is_array($form_data['fields'])) {
        dtr_log_acf_questions("No fields array found in form data", $debug_id);
        return $questions;
    }

    foreach ($form_data['fields'] as $field_id => $field_data) {
        if (!is_array($field_data)) {
            continue;
        }

        // Check if this field should be treated as a question
        if (!dtr_is_question_field($field_data)) {
            continue;
        }

        $question_text = dtr_get_field_label($field_data, $field_id);
        $answer_text = dtr_get_field_value($field_data);

        // Skip empty answers
        if (empty(trim($answer_text))) {
            dtr_log_acf_questions("Skipping empty answer for field: {$question_text}", $debug_id);
            continue;
        }

        $questions[] = [
            'question' => sanitize_text_field($question_text),
            'answer' => sanitize_textarea_field($answer_text)
        ];

        dtr_log_acf_questions("Extracted question: '{$question_text}' with answer length: " . strlen($answer_text), $debug_id);
    }

    return $questions;
}

/**
 * Determine if a field should be treated as a question
 *
 * @param array $field_data Field data array
 * @return bool True if field is a question type
 */
function dtr_is_question_field($field_data) {
    // Question field types
    $question_types = ['textarea', 'textbox', 'select', 'radio', 'checkbox'];
    
    // Get field type
    $field_type = $field_data['type'] ?? '';
    
    // Must be a question type
    if (!in_array($field_type, $question_types)) {
        return false;
    }

    // Exclude system fields by key/name patterns
    $excluded_patterns = [
        'email', 'name', 'phone', 'company', 'submit', 'hidden',
        'first_name', 'last_name', 'user_', 'wp_'
    ];

    $field_key = strtolower($field_data['key'] ?? '');
    $field_label = strtolower($field_data['label'] ?? '');

    foreach ($excluded_patterns as $pattern) {
        if (strpos($field_key, $pattern) !== false || strpos($field_label, $pattern) !== false) {
            return false;
        }
    }

    return true;
}

/**
 * Get field label for question text
 *
 * @param array $field_data Field data
 * @param mixed $field_id Field ID fallback
 * @return string Field label
 */
function dtr_get_field_label($field_data, $field_id) {
    $label = $field_data['label'] ?? '';
    
    if (empty($label)) {
        $label = $field_data['key'] ?? '';
    }
    
    if (empty($label)) {
        $label = "Field {$field_id}";
    }

    return trim($label);
}

/**
 * Get field value for answer text
 *
 * @param array $field_data Field data
 * @return string Field value
 */
function dtr_get_field_value($field_data) {
    $value = $field_data['value'] ?? '';
    
    // Handle array values (checkboxes, multi-select)
    if (is_array($value)) {
        $value = implode(', ', array_filter($value));
    }

    return trim($value);
}

/**
 * Merge new questions with existing ones, avoiding duplicates
 *
 * @param array $existing_questions Existing ACF questions
 * @param array $new_questions New questions from form
 * @param string $debug_id Debug identifier
 * @return array Merged questions array
 */
function dtr_merge_acf_questions($existing_questions, $new_questions, $debug_id) {
    $merged_questions = $existing_questions;
    $duplicates_skipped = 0;
    $questions_added = 0;

    foreach ($new_questions as $new_question) {
        if (!dtr_is_duplicate_question($merged_questions, $new_question)) {
            $merged_questions[] = $new_question;
            $questions_added++;
            dtr_log_acf_questions("Added new question: '{$new_question['question']}'", $debug_id);
        } else {
            $duplicates_skipped++;
            dtr_log_acf_questions("Skipped duplicate question: '{$new_question['question']}'", $debug_id);
        }
    }

    dtr_log_acf_questions("Merge complete. Added: {$questions_added}, Skipped duplicates: {$duplicates_skipped}", $debug_id);
    return $merged_questions;
}

/**
 * Check if a question is a duplicate of existing questions
 *
 * @param array $existing_questions Array of existing questions
 * @param array $new_question New question to check
 * @return bool True if duplicate found
 */
function dtr_is_duplicate_question($existing_questions, $new_question) {
    foreach ($existing_questions as $existing_question) {
        if (!isset($existing_question['question'], $existing_question['answer'])) {
            continue;
        }

        // Check for exact match of both question and answer
        if ($existing_question['question'] === $new_question['question'] &&
            $existing_question['answer'] === $new_question['answer']) {
            return true;
        }

        // Check for similar question with different answer (consider as duplicate)
        if ($existing_question['question'] === $new_question['question']) {
            return true;
        }
    }

    return false;
}

/**
 * Log ACF questions debug information
 *
 * @param string $message Debug message
 * @param string $debug_id Debug identifier
 * @return void
 */
function dtr_log_acf_questions($message, $debug_id = '') {
    if (!function_exists('error_log')) {
        return;
    }

    $timestamp = current_time('Y-m-d H:i:s');
    $prefix = $debug_id ? "[{$debug_id}]" : '[DTR-ACF-Questions]';
    $formatted_message = "{$timestamp} {$prefix} {$message}";
    
    error_log($formatted_message);
    
    // Also log to custom DTR log if function exists
    if (function_exists('dtr_custom_log')) {
        dtr_custom_log($formatted_message);
    }
}

// Initialize hooks when this file is loaded
dtr_init_acf_questions_hooks();