# Archived Plugin Files

These files were moved from active code paths to prevent conflicts and clean up the codebase. Retained for historical reference / rollback. They are not loaded by the plugin bootstrap.

If a file needs to be reactivated, move it back to its original location and add it to the loader list if required.

---

## Original Archive (2025-09-06)
These files were moved from active code paths on 2025-09-06:

includes/account-workbooks-form.php
includes/ajax-handlers.php
includes/class-nf-submission-controller.php
includes/class-translation-loader.php
includes/form-detector-enqueue.php
includes/form-test-debug.php
includes/get-workbooks-queues.php
includes/lead-generation-handler.php
includes/media-planner-ajax-handler.php
includes/nf-country-converter.php
includes/nf-enqueue.php
includes/nf-user-register.php
includes/ninja-forms-simple-fix.php
includes/ninja-forms-simple-hook-backup.php
includes/ninja-forms-simple-hook.php
includes/ninjaforms-workbooks-integration.php
includes/shortcode-gated-preview-content.php
includes/user-meta-fields.php
includes/webinar-debug-logger.php
includes/webinar-detector-enqueue.php
includes/workbooks-user-sync.php
admin/admin-person-update.php
admin/archive-logs.php
admin/employers.php
admin/gated-content-single.php
admin/gated-content.php
admin/membership-signup.php
admin/ninja-users.php
admin/person-record.php
admin/settings.php
admin/tab-scripts.php
admin/topics.php
admin/webinar-registration.php
employers.json
employers-supersize.json

## Webinar Duplication Fix Archive (2025-09-26)
These webinar-related files were moved to fix duplicate submission issues:

**form-handler-webinar-shortcode-registration.php** - Legacy webinar handler that was causing duplicate submissions
**class-webinar-registration-form-shortcode.php** - Conflicting webinar class with different AJAX actions

### Issue Resolved:
The webinar registration system was creating duplicate submissions because:
1. Multiple webinar handlers were registered for similar AJAX actions
2. Legacy files were being loaded alongside the new clean implementation
3. Main plugin was trying to register non-existent AJAX handler methods

### Current Clean Architecture:
- **shortcodes/webinar-registration-form-shortcode.php** - Main webinar form shortcode (HTML/AJAX)
- **includes/form-handler-live-webinar-registration.php** - Core Workbooks CRM integration
- No legacy webinar files loaded
- Single AJAX action: `dtr_submit_webinar_shortcode` -> `dtr_handle_webinar_submission()`

## JavaScript Cleanup Archive (2025-09-26)
These redundant JavaScript files were moved to reduce complexity and eliminate duplicate code:

**webinar-registration.js** (from assets/js/) - Contained duplicate webinar functionality already present in frontend.js. The frontend.js file now handles all webinar form interactions, including toggle buttons, login modals, and Ninja Forms submission handling.

**webinar-form-detector.js** (from assets/js/) - Originally designed to detect webinar forms on pages, but the entire contents were commented out and never used. No active functionality.

**webinar-endpoint.js** (from js/) - Empty file with no functionality. Was meant for webinar-specific endpoints but never implemented.

### Current Clean JavaScript Architecture:
- **assets/js/frontend.js** - Main frontend functionality (login modals, toggle buttons, webinar form submission)
- **assets/js/webinar-form.js** - Webinar-specific form validation and AJAX submission for logged-in users
- **assets/js/webinar-class-form.js** - Class-based webinar registration form handling
- No duplicate or empty JS files
