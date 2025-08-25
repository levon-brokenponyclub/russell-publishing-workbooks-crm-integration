<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Handles ACF questions from Ninja Forms submissions
 * Appends them to the 'add_questions' repeater for forms 2 (Webinar) and 31 (Lead Gen)
 * Ignores empty and duplicate answers
 * Logs all questions and answers
 */

add_action('ninja_forms_after_submission', function($form_data) {

    $form_id = $form_data['form_id'] ?? 0;
    if (!in_array($form_id, [2, 31])) return;

    $fields = $form_data['fields'] ?? [];

    // Identify post ID from hidden field
    $post_id = 0;
    foreach ($fields as $f) {
        if (!empty($f['key']) && $f['key'] === 'post_id') {
            $post_id = intval($f['value']);
            break;
        }
    }
    if (!$post_id) return;

    // Collect ACF answers
    $acf_answers = [];
    foreach ($fields as $f) {
        if (strpos($f['key'], 'acf_question_') === 0) {
            $value = is_array($f['value']) ? implode(', ', $f['value']) : $f['value'];
            $value = trim($value);
            if ($value !== '') { // skip empty
                $acf_answers[] = [
                    'question_title' => $f['key'],
                    'answer' => $value,
                ];
            }
        }
    }
    if (empty($acf_answers)) return;

    // Append to ACF repeater, avoid duplicates
    $existing = get_field('add_questions', $post_id) ?: [];
    foreach ($acf_answers as $new) {
        $duplicate = false;
        foreach ($existing as $old) {
            if ($old['question_title'] === $new['question_title'] && $old['answer'] === $new['answer']) {
                $duplicate = true;
                break;
            }
        }
        if (!$duplicate) {
            $existing[] = $new;
        }
    }
    update_field('add_questions', $existing, $post_id);

    // Log submission
    $log = [
        'form_id' => $form_id,
        'post_id' => $post_id,
        'answers' => $acf_answers,
        'timestamp' => current_time('mysql')
    ];
    error_log('ACF Questions Submission: ' . print_r($log, true));

}, 10, 1);
