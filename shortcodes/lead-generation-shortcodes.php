<?php
// --------------------------------------------------------------------------
// Workbooks Lead Generation Registration Shortcode Registration
// Shortcode: [workbooks-lead-generation-registration]
// Usage: [workbooks-lead-generation-registration lead_generation_id="12345"]
// --------------------------------------------------------------------------

// Ensure the logic file is always loaded, regardless of loader constant timing
require_once dirname(__DIR__) . '/includes/class-lead-generation-registration.php';

add_shortcode('workbooks-lead-generation-registration', 'workbooks_lead_generation_registration_shortcode');