<?php
/**
 * Load all required plugin components
 */
// Legacy loader kept for backward compatibility only. All active includes now
// loaded via main dtr-workbooks-crm-integration.php -> load_includes().
// Intentionally no-ops to avoid requiring archived files.
function dtr_load_plugin_components() {
    do_action('dtr_components_loaded');
}
add_action('plugins_loaded', 'dtr_load_plugin_components', 5);
