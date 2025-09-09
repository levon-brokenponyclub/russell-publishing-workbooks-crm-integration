<?php
/* --------------------------------------------------------------------------
 * Workbooks Webinar Registration Shortcode Registration
 *  * Shortcode: [workbooks-webinar-registration]
  * Renders the webinar registration form for a specified webinar.
  *
  * Usage: [workbooks-webinar-registration webinar_id="WEBINAR_ID"]
  * - Replace WEBINAR_ID with the actual ID of the webinar.
  *
  * Example: [workbooks-webinar-registration webinar_id="12345"]
  *
  * Note: Ensure that the 'class-webinar-registration.php' file is included
  * in your plugin or theme to provide the necessary functionality.
  *
 * -------------------------------------------------------------------------- */

// Ensure the logic is loaded
require_once dirname(__DIR__) . '/includes/class-webinar-registration.php';

// Register the shortcode
add_shortcode('workbooks-webinar-registration', 'workbooks_webinar_registration_shortcode');
