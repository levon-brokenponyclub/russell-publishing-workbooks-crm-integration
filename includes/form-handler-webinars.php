<?php
/**
 * Core Webinar Registration Handler & Mailing List Updater
 * Handles registration of webinar attendees in Workbooks CRM and mailing lists.
 *
 * @package DTR/Webinar
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Process webinar registration submission
 *
 * @param array $form_data The form submission data
 * @param int $form_id The form ID
 * @param string $debug_id Debug identifier for logging
 * @return bool Success status
 */
function dtr_process_webinar_registration($form_data, $form_id, $debug_id = '') {
    // Initialize default model data for Ninja Forms compatibility
    global $dtr_form_model;
    $dtr_form_model = [
        'fields' => [
            'email' => ['value' => ''],
            'sponsor_optin' => ['value' => 0],
            'speaker_question' => ['value' => '']
        ]
    ];
    if (!is_array($form_data) || empty($form_data)) {
        dtr_log_debug("Webinar registration failed: Invalid form data", $debug_id);
        return false;
    }

    // Validate required dependencies
    if (!function_exists('dtr_create_workbooks_person') || 
        !function_exists('dtr_create_workbooks_ticket') || 
        !function_exists('dtr_add_to_mailing_list')) {
        dtr_log_debug("Webinar registration failed: Required functions missing", $debug_id);
        return false;
    }

    dtr_log_debug("Starting webinar registration process", $debug_id);
    dtr_log_debug("Form data: " . print_r($form_data, true), $debug_id);

    // Map Ninja Forms fields to expected model
    if ($form_data && isset($form_data['fields'])) {
        foreach ($form_data['fields'] as $field_id => $field) {
            switch ($field_id) {
                case 'nf-field-387': // Speaker question field
                    $dtr_form_model['fields']['speaker_question']['value'] = $field['value'] ?? '';
                    break;
                case 'nf-field-58': // Sponsor opt-in field
                    $dtr_form_model['fields']['sponsor_optin']['value'] = !empty($field['value']) ? 1 : 0;
                    break;
                case 'nf-field-384': // Email field
                    $dtr_form_model['fields']['email']['value'] = $field['value'] ?? '';
                    break;
            }
        }
    }

    try {
        // Step 1: Create person in Workbooks
        $person_result = dtr_create_workbooks_person($form_data, $debug_id);
        if (!$person_result || !isset($person_result['success']) || !$person_result['success']) {
            throw new Exception("Failed to create person in Workbooks: " . 
                ($person_result['message'] ?? 'Unknown error'));
        }

        $person_id = $person_result['person_id'] ?? null;
        if (!$person_id) {
            throw new Exception("Person created but ID not returned");
        }

        dtr_log_debug("Person created successfully. ID: {$person_id}", $debug_id);

        // Step 2: Get event reference for ticket creation
        $event_reference = dtr_get_event_reference_from_form($form_data, $debug_id);
        if (!$event_reference) {
            throw new Exception("Could not determine event reference from form data");
        }

        // Step 3: Create webinar ticket
        $ticket_result = dtr_create_workbooks_ticket($person_id, $event_reference, $debug_id);
        if (!$ticket_result || !isset($ticket_result['success']) || !$ticket_result['success']) {
            throw new Exception("Failed to create webinar ticket: " . 
                ($ticket_result['message'] ?? 'Unknown error'));
        }

        dtr_log_debug("Webinar ticket created successfully", $debug_id);

        // Step 4: Add to mailing list
        $mailing_result = dtr_add_to_mailing_list($form_data, $debug_id);
        if (!$mailing_result || !isset($mailing_result['success']) || !$mailing_result['success']) {
            dtr_log_debug("Warning: Failed to add to mailing list: " . 
                ($mailing_result['message'] ?? 'Unknown error'), $debug_id);
            // Note: Not throwing exception here as webinar registration was successful
        }

        // Step 5: Process ACF questions if present
        dtr_process_webinar_questions($form_data, $person_id, $debug_id);

        dtr_log_debug("Webinar registration completed successfully", $debug_id);
        return true;

    } catch (Exception $e) {
        dtr_log_debug("Webinar registration failed: " . $e->getMessage(), $debug_id);
        return false;
    }
}

/**
 * Extract event reference from form data
 *
 * @param array $form_data Form submission data
 * @param string $debug_id Debug identifier
 * @return string|null Event reference or null if not found
 */
function dtr_get_event_reference_from_form($form_data, $debug_id = '') {
    $event_reference = null;

    // Check for event reference in various possible locations
    $possible_keys = ['event_reference', 'webinar_reference', 'reference'];
    
    foreach ($possible_keys as $key) {
        if (isset($form_data[$key]) && !empty($form_data[$key])) {
            $event_reference = sanitize_text_field($form_data[$key]);
            break;
        }
    }

    // If not found in direct fields, check meta fields
    if (!$event_reference && isset($form_data['fields']) && is_array($form_data['fields'])) {
        foreach ($form_data['fields'] as $field) {
            if (isset($field['key']) && in_array($field['key'], $possible_keys) && 
                isset($field['value']) && !empty($field['value'])) {
                $event_reference = sanitize_text_field($field['value']);
                break;
            }
        }
    }

    // Try ACF field lookup as fallback
    if (!$event_reference && function_exists('get_field')) {
        $event_reference = get_field('event_reference');
    }

    dtr_log_debug("Event reference extracted: " . ($event_reference ?: 'Not found'), $debug_id);
    return $event_reference;
}

/**
 * Process webinar-specific questions from ACF
 *
 * @param array $form_data Form submission data
 * @param int $person_id Workbooks person ID
 * @param string $debug_id Debug identifier
 * @return void
 */
function dtr_process_webinar_questions($form_data, $person_id, $debug_id = '') {
    if (!function_exists('get_field') || !function_exists('update_field')) {
        dtr_log_debug("ACF functions not available for question processing", $debug_id);
        return;
    }

    // Get existing ACF questions and form model data
    $acf_questions = get_field('add_questions') ?: [];
    $form_questions = dtr_extract_questions_from_form($form_data);
    
    // Debug output for form model
    dtr_log_debug("Form model data: " . print_r($dtr_form_model, true), $debug_id);
    
    if (empty($form_questions)) {
        dtr_log_debug("No questions found in form data", $debug_id);
        return;
    }

    // Merge questions, avoiding duplicates
    $updated_questions = dtr_merge_questions($acf_questions, $form_questions, $debug_id);
    
    // Update ACF field
    $update_result = update_field('add_questions', $updated_questions);
    
    if ($update_result) {
        dtr_log_debug("Questions updated successfully. Total count: " . count($updated_questions), $debug_id);
    } else {
        dtr_log_debug("Failed to update ACF questions", $debug_id);
    }
}

/**
 * Extract questions from form data
 *
 * @param array $form_data Form submission data
 * @return array Extracted questions
 */
function dtr_extract_questions_from_form($form_data) {
    $questions = [];
    
    if (!isset($form_data['fields']) || !is_array($form_data['fields'])) {
        return $questions;
    }

    foreach ($form_data['fields'] as $field) {
        if (!isset($field['type']) || $field['type'] !== 'textarea' || 
            !isset($field['value']) || empty(trim($field['value']))) {
            continue;
        }

        $question_text = sanitize_textarea_field($field['value']);
        $label = isset($field['label']) ? sanitize_text_field($field['label']) : 'Question';

        $questions[] = [
            'question' => $label,
            'answer' => $question_text
        ];
    }

    return $questions;
}

/**
 * Merge new questions with existing ones, avoiding duplicates
 *
 * @param array $existing_questions Existing ACF questions
 * @param array $new_questions New questions from form
 * @param string $debug_id Debug identifier
 * @return array Merged questions
 */
function dtr_merge_questions($existing_questions, $new_questions, $debug_id = '') {
    $existing_questions = is_array($existing_questions) ? $existing_questions : [];
    
    foreach ($new_questions as $new_question) {
        $is_duplicate = false;
        
        foreach ($existing_questions as $existing_question) {
            if (isset($existing_question['question'], $existing_question['answer'], 
                      $new_question['question'], $new_question['answer']) &&
                $existing_question['question'] === $new_question['question'] &&
                $existing_question['answer'] === $new_question['answer']) {
                $is_duplicate = true;
                break;
            }
        }
        
        if (!$is_duplicate) {
            $existing_questions[] = $new_question;
            dtr_log_debug("Added new question: " . $new_question['question'], $debug_id);
        } else {
            dtr_log_debug("Skipped duplicate question: " . $new_question['question'], $debug_id);
        }
    }

    return $existing_questions;
}

/**
 * Log debug information with consistent formatting
 *
 * @param string $message Debug message
 * @param string $debug_id Debug identifier
 * @return void
 */
function dtr_log_debug($message, $debug_id = '') {
    if (!function_exists('error_log')) {
        return;
    }

    $timestamp = current_time('Y-m-d H:i:s');
    $prefix = $debug_id ? "[{$debug_id}]" : '[DTR-Webinar]';
    $formatted_message = "{$timestamp} {$prefix} {$message}";
    
    error_log($formatted_message);
    
    // Also log to custom DTR log if function exists
    if (function_exists('dtr_custom_log')) {
        dtr_custom_log($formatted_message);
    }
}