<?php
/**
 * Fetch all Event Registration Field Mappings Queues via API.
 * Returns an array of [queue_id => name].
 *
 * @param object $workbooks  An authenticated Workbooks API client instance.
 * @return array|false  Associative array of queue_id => name, or false on error.
 */
function dtr_get_event_registration_field_mapping_queues($workbooks) {
    // Custom debug log file in admin folder
    $log_file = dirname(__FILE__) . '/../admin/gated-content-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    try {
        $endpoint = '/custom_record_queues/event_registration_field_mappings.api';
        file_put_contents($log_file, "[$timestamp] event-registration-queues: Querying endpoint $endpoint\n", FILE_APPEND);

        $result = $workbooks->get($endpoint, []);
        file_put_contents($log_file, "[$timestamp] event-registration-queues: API result: " . print_r($result, true) . "\n", FILE_APPEND);

        if (empty($result['data']) || !is_array($result['data'])) {
            file_put_contents($log_file, "[$timestamp] event-registration-queues: No data or invalid API response\n", FILE_APPEND);
            return false;
        }
        $queues = [];
        foreach ($result['data'] as $row) {
            if (isset($row['id']) && isset($row['name'])) {
                $queues[$row['id']] = $row['name'];
            }
        }
        file_put_contents($log_file, "[$timestamp] event-registration-queues: Final queues: " . print_r($queues, true) . "\n", FILE_APPEND);
        return $queues;
    } catch (Exception $e) {
        file_put_contents($log_file, "[$timestamp] event-registration-queues: Exception: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
?>