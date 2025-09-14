# DTR - Workbooks CRM API Integration

## Overview

**Author**: Supersonic Playground / Levon Gravett  
**Website**: https://www.supersonicplayground.com  
**Version**: 2.1.0

A production-ready WordPress plugin that integrates WordPress with the DTR Workbooks CRM. V2 focuses on modular admin code, robust debugging that is gated for production, comprehensive gated-content and Ninja Forms integration (ACF-driven), and reliable Workbooks person/employer/ticket/lead creation and synchronization.

---

## Comprehensive Feature Summary

A comprehensive WordPress plugin enabling seamless integration between WordPress and DTR Workbooks CRM. This solution powers automated user registration, advanced ACF-driven content gating, dynamic form generation from content metadata, robust event/ticket/lead creation, intelligent employer search, bidirectional account preference syncing, and detailed debugging across ALL gated content types (not just webinars).

### 🔗 Core CRM Integration
* Secure API wrapper with timeout/error surfacing & lock_version handling
* Person create/update + verification fetch (optional) to confirm persistence
* Always-create Sales Lead for every gated submission (ensures engagement capture)
* Ticket / mailing list handling when event context present

### 🧬 AOI / TOI Mapping
* Automatic mapping from Topics of Interest selections to Areas of Interest fields
* Genomics & biomarkers special-case logic (migration details in Changelog)

### 🧾 Dynamic Gated Content & Forms
* ACF-driven field definitions – zero manual Ninja Forms edits for new gated content fields
* Unified membership & gated submission pipeline (duplicate handler callbacks removed)
* Automatic extraction of ACF questions, sponsor opt-in, campaign references
* Hidden field (post_id / campaign) resilience & logging (for debugging sessions)

### 👤 Membership & My Account
* My Account preference & AOI/TOI form synchronizes changes back to Workbooks
* Admin updates mirror into user meta (bidirectional parity)
* Optional WordPress user creation with stored Workbooks identifiers

### 🏢 Employer Management
* Custom table `wp_workbooks_employers` + transient cache
* Daily cron + manual sync (batched API pull)
* High‑performance server‑side Select2 endpoint with relevance ordering
* JSON generation (canonical path `assets/json/employers.json` + legacy fallback)

### 🔍 Employer Select2 Enhancements
* Exact > starts-with > contains ordering
* Paginated results (page/limit) with minimal counting for performance
* Graceful empty dataset on nonce failure (avoids UI break)

### 🧪 Observability & Logging
* Central `logs/` directory (daily rolling + specialized debug logs)
* Structured step logging for membership & account update flows
* Optional verbose admin logs gated by plugin debug setting
* Log reset workflow to prepare clean test baselines

### 🛡️ Resilience & Migration
* Backward-compatible employer JSON read/write helpers
* Legacy nonce acceptance (`dtr_workbooks_nonce`) for public forms
* Genomics field key migration (see Changelog) – removed from main body for clarity

### 🧰 Tooling & Dev Experience
* Deployment scripts & CI workflow scaffold
* Archived legacy code catalogued (`archive/README-ARCHIVED-FILES.md`)
* Clear plugin structure & helper abstraction layers

## Webinar Registration Template Logic Reference

### Logic Overview

#### 1. Webinar Type Detection
- If the ACF field `webinar_link` is present, the post is treated as an **On Demand Webinar**.
- Otherwise, it is a **Live Webinar** (date-based logic applies).

#### 2. User State Logic
- **Not logged in:**
   - Show a "Register Now" button that triggers the login modal.
- **Logged in:**
   - **On Demand Webinar (webinar_link present):**
      - Show "On Demand - Register Now" button.
      - If `add_additional_questions` is true and a form is set, display the Ninja Form below.
   - **Live Webinar (upcoming event, not on demand):**
      - If user is already registered:
         - Show "Registered" button and calendar link.
      - If user is not registered:
         - Show "Register Now" button (no link).
         - Display the Ninja Form below if set.
   - **Live Webinar (event has passed, not on demand):**
      - Show "On Demand - Register Now" button.
      - If `add_additional_questions` is true and a form is set, display the Ninja Form below.

#### 3. Logging
- Console logs are output at each major logic branch to identify webinar type and user state for debugging.
- PHP comments and section titles are used throughout the template for maintainability.

#### 4. Future Extensions
- Placeholder for JS logging/debug code is kept for future use.
- Logic is modular and clearly commented for easy adaptation to Lead Generation or other flows.

---

## Installation & Setup

1. Copy the plugin directory to `/wp-content/plugins/dtr-workbooks-crm-integration/`.
2. Activate the plugin via WordPress Admin → Plugins.
3. Configure API settings: Plugins → Workbooks CRM (API URL, API Key). Save.
4. Set `debug_mode` in plugin settings only when troubleshooting (see Debugging section).

### Requirements

- WordPress 5.0+  
- PHP 7.4+  
- cURL extension  
- Ninja Forms (optional, required for Ninja Forms features)  
- Advanced Custom Fields (ACF) for gated content features

## Configuration

- API URL / API Key: enter under plugin settings.  
- Debug Mode: toggle under plugin settings. When enabled the plugin will write admin debug files in `admin/` (see notes below). In production, keep debug_mode disabled.


## Debugging & Logs

### Common Issue: Function Argument Error

**Issue:**
You may see an error stating that the function `dtr_register_workbooks_webinar` is defined to require at least 2 arguments, but it is being called with only one (an array).

**Fix:**
Update the call so that all required arguments are passed individually, matching the function signature. Do not pass a single array; instead, provide each argument as expected by the function. This resolves the fatal error.

- Main plugin logs are located in:
  - `wp-content/plugins/dtr-workbooks-crm-integration/logs/` (daily API and operational logs)
  - `wp-content/plugins/dtr-workbooks-crm-integration/admin/connection-debug.log` (connection tests) — written only if `debug_mode` = true
  - `wp-content/plugins/dtr-workbooks-crm-integration/admin/update-debug.log` (person update payloads/responses/verify fetches) — written only if `debug_mode` = true

- Important: debug logs are gated. To enable admin debug logs, set plugin option `dtr_workbooks_options['debug_mode'] = true` via settings or admin UI. This avoids noisy logs in production.

- Archival on disable: When `debug_mode` is turned off via the plugin settings, any existing admin debug logs in the `admin/` folder are moved to `admin/archive/` with a timestamped filename (so you keep historical logs without exposing them in the webroot).

## Usage Guide

### Gated Content & Ninja Forms Integration
**Form & Field Setup**
* Attach ACF field group to any gated content post type (webinar, report, whitepaper, etc.)
* Add / modify fields – forms adapt automatically (no manual Ninja Forms edits)
* Standard core + marketing + AOI/TOI + employer fields are auto-recognized

**Registration Process**
1. User submits dynamic form
2. (Optional) WordPress user created / updated
3. Fields mapped & sanitized → Workbooks person create/update
4. Sales Lead always created
5. Ticket / mailing list updated when event context present
6. Debug logs written (if enabled)

### Person Record Management
Supported key field categories:
* Personal: title, first name, last name, job title
* Contact: email, telephone, country, town, postcode
* Employer: free‑text employer name resolved to organisation (created if absent)
* Preferences: marketing/news/events/webinars/third‑party
* AOI/TOI: business, diseases, drugs & therapies, genomics, R&D, technology, tools & techniques

Example Mapping Snippet:
```php
'person_personal_title' => 'person_personal_title',
'employer_name'         => 'employer_name',
'cf_person_aoi_biomarkers' => 'cf_person_biomarkers',
'cf_person_aoi_genomics'   => 'cf_person_genomics',
```

### Employer Synchronization
Automatic Sync Features:
* Daily cron & manual trigger
* Batch API retrieval → DB table + transient + regenerated JSON
* Select2 endpoint reads from DB (paged) – JSON fallback for legacy code paths

Sync Steps:
1. Fetch batches from Workbooks
2. Upsert into table
3. Write JSON (new path + fallback)
4. Update transient & last sync data

### Webinar & Content Registration Flow
1. User selects gated content / event
2. ACF metadata (event IDs, sponsor, questions) loaded
3. Submission handled by unified pipeline
4. Person + ticket + lead operations executed
5. Logging & optional verification fetch

### My Account Updates
* Frontend changes immediately synced to Workbooks
* Admin preference edits mirrored back to user meta (keeps UI consistent)

### Debugging Workflow (Typical)
1. Enable debug mode (settings)
2. Reset daily log
3. Perform test submission (capture debug ID)
4. Review specialized + daily logs
5. Disable debug mode post‑diagnosis

---

### Logging Helpers
* `dtr_admin_log($message, $file)` – gated by debug mode
* Daily operational log – automatic append (naming pattern `dtr-workbooks-YYYY-MM-DD.log`)

Enable Debug Mode programmatically (optional):
```php
update_option('dtr_workbooks_options', array_merge(
   get_option('dtr_workbooks_options', []),
   ['debug_mode' => true]
));
```
Disable afterward to reduce I/O.

## Deployment
* Scripts in `scripts/` (setup, deploy, post-commit sync) – optional CI
* Ensure permissions allow writing to `logs/` and `assets/json/`

---

## Changelog (Highlights)

### Backend Improvements
- **Database Fixes**: Resolved issues with database table creation to ensure proper functionality.
- **AJAX Handlers**: Improved AJAX functionality for dynamic updates without page reloads.
- **Shortcodes**: Implemented shortcodes for displaying user details dynamically.
- **Logout Functionality**: Ensured consistent logout behavior after password updates, including redirection to the homepage.

### Frontend Enhancements
- **Form Submission Animations**: Added animations for better user experience during form submissions.
- **Gated Content Buttons**: Updated buttons to use relative URLs and trigger modals for login functionality.
- **Button Alignment**: Ensured buttons are displayed side by side inline for better visual alignment.
- **Text Centering**: Adjusted button styles to ensure text is perfectly vertically centered.

### Usability and UX/UI Improvements
- Enhanced user interface for gated content, including updated button styles and alignment.
- Improved accessibility and responsiveness across various components.
- Streamlined user interactions with modals and dynamic updates.

### Future Plans
- Further optimization of AJAX handlers.
- Additional UX/UI improvements based on user feedback.
- Integration with more CRM features.

### 2.1.0
- Refactored both webinar and lead generation registration logic into dedicated, modular classes and handler files for maintainability and testability.
- All registration shortcodes (webinar and lead gen) are always loaded and registered, ensuring UI is available wherever needed.
- Unified and robust logging for all registration flows, with step-by-step debug output and specialized logs for lead generation and webinars.
- Dynamic ACF-powered question rendering: registration forms now automatically display all ACF-defined questions for the current post/event, with no manual edits required.
- Frontend UI improvements: always-on form display for logged-in users, dynamic feedback, and microanimation for form submission.
- Fixed all PHP parse errors and logic bugs in lead generation and webinar registration flows, including array mapping, HTML structure, and conditional logic.
- Improved error handling and admin/test mode for safe, repeatable testing.
- Updated documentation and changelog to reflect all recent progress and improvements.
- Unified Membership Registration Handler: All membership and gated content registrations now use a single, robust handler for consistency and easier maintenance.
- Paginated Select2 Employer Search: Employer search in admin and frontend now uses paginated Select2 with exact, starts-with, and contains ordering for faster, more relevant results.
- Employer JSON Relocation: Employer data JSON is now stored at `assets/json/employers.json` with new helper functions for reading/writing, plus legacy fallback for compatibility.
- Central Logging & Log Reset: All plugin logs are now consolidated, with a workflow for resetting logs to prepare for clean test runs and easier debugging.
- Admin ↔ User Preference & AOI Sync: Improvements to ensure admin changes to user preferences and AOI fields are mirrored to user meta, and vice versa, for true bidirectional sync.
- Genomics Key Migration: Logic for genomics field migration has been moved out of the main code body. The plugin now repairs incorrect legacy `cf_person_genomics_3744` keys and uses the canonical key, with an optional cleanup utility for old data.
- Responsive Tables: Admin tables are now fully responsive, with columns auto-sizing and horizontal scrolling on small screens.
- Column Width Fixes: First column adapts to content, second and third columns have a max width of 250px with ellipsis for overflow.
- Toggle Button for Workbooks Fields: The "Show Workbooks API Fields for this User" link is now a button with improved JS toggle logic and accessibility.
- Ninja Forms Country Select: The Ninja Forms - Full Country Names plugin now processes selects inside containers with `.full-iso-country-names`, only logs the selected country (not all options) in the console, both on load and change, handles both ISO code and full country name as selected value (with reverse lookup for code), and uses improved MutationObserver and logging for dynamic forms.

---

## Key Features

### 🔗 **Core CRM Integration**

### 🧩 **Modular & Extensible Architecture**
- **Fully Modular Registration Logic**: All registration, handler, and UI logic is separated into dedicated classes and shortcodes for maintainability and easy extension.
- **Shortcode-Based UI**: Webinar registration and save-for-later features are rendered via shortcodes, allowing flexible placement and theme integration.
- **Centralized Logging**: All debug and process logs are routed to a central directory, with step-by-step logging for every major action.
- **Admin/Test Mode**: Dedicated admin-side test forms and handlers allow robust, repeatable testing without affecting live data.
- **Frontend/Backend Separation**: All business logic is server-side, with AJAX-powered UI for seamless user experience.
- **User Content Collections**: Users can save posts to their "My Account" area for later viewing, with the ability to add personal notes to each saved post.
- **Dynamic Button States**: Save buttons update in real time to show "Saved" status, and revert if removed, providing instant feedback.


### 📝 **Gated Content Enhancements & Microanimation Additions**

- **🔐 Enhanced Gated Content**: 
- Dynamic gated content generation based on post type.
- Streamlined content access tied to CRM campaign tracking.

- **🎨 User Interface Enhancements**: 
- Smooth button animations and micro-interactions.
- Improved content presentation for gated resources.

- **👤 Account Management**: 
 -Customizable dashboards for users to manage their profiles and preferences.
- Full integration of CRM-linked user data within WordPress accounts.


### 🥷 **Advanced Ninja Forms & ACF-Powered Gated Content Integration**

- **Real-Time ACF Answer Logging**: The frontend now logs ACF question answers to the browser console in real time as users interact with the form, including dropdowns, checkboxes, radios, and text fields. This helps confirm that all user input is being captured before submission.
- **Post ID & Campaign Logging**: The script logs the current `post_id` (and `campaign` if present) alongside ACF answers, ensuring these hidden fields are always visible in the console for debugging and validation.
- **Robust Field Detection**: Improved JavaScript logic to reliably find hidden fields (like `post_id`) both globally and inside the Ninja Form, so values are always logged even if the form structure changes.
- **Backend Handler Improvements**: The lead generation handler and Ninja Forms hook were updated to ensure that ACF questions (from the `add_questions` field) are always extracted, merged, and logged, and that all lead generation actions are written to `lead-generation-debug.log`.
- **Parse Error Fixes**: Fixed PHP parse errors in the ACF question rendering logic by moving array mapping outside of inline echo statements and using `isset()` for compatibility.
- **Automatic User Creation**: Seamlessly create WordPress users from form submissions
- **CRM Record Creation**: Automatically generate corresponding Workbooks person records
- **Dynamic Form Generation**: Forms for gated content are now generated based on ACF fields within any post type (webinars, reports, whitepapers, etc.), minimizing manual form edits and maintenance
- **Flexible Mapping**: All ACF fields (including event/campaign references, questions, sponsor opt-in, etc.) are mapped and synchronized to Workbooks CRM
- **Field Mapping**: Complete mapping of all form fields to CRM equivalents, including:
  - Personal titles (Dr., Mr., Mrs., Ms., Prof., etc.)
  - Contact information (name, email, telephone, address)
  - Employment details with editable employer names
  - Marketing preferences and subscription settings
  - Topics of Interest (TOI) to Areas of Interest (AOI) mapping
- **Error Recovery**: Robust error handling with detailed logging and recovery mechanisms

### 📚 **Universal Gated Content Integration (Webinars & More)**
- **Dynamic ACF Question Handling**: All ACF questions defined on the post are now merged and passed to the backend, and are visible in both the frontend console and backend debug logs for every submission.
- **All Gated Content Supported**: Handles webinars, reports, whitepapers, and any other ACF-powered gated content post type
- **ACF-Driven Registration**: All critical event/campaign references, questions, and dynamic fields are defined and managed via ACF on the content itself
- **Zero-Edit Forms**: When you add or update a field in the ACF group for a gated post, your registration form adapts automatically
- **Lead and Ticket Automation**: Submissions always create a Workbooks person, event ticket (when relevant), and a new sales lead—ensuring all engagement is tracked

### 🎯 **Topics of Interest & Areas of Interest**
- **Dynamic Mapping**: Sophisticated mapping system between Topics of Interest and Areas of Interest
- **Genomics Specialization**: Enhanced mapping for genomics-related fields including biomarkers
- **Automated Population**: TOI selections automatically populate corresponding AOI fields in Workbooks
- **Admin Visualization**: Clear admin interface showing all mappings and relationships

### 🏢 **Employer Management**
- **Intelligent Sync**: Daily automated synchronization of employer data from Workbooks
- **Search Functionality**: Advanced employer search with pagination and filtering
- **JSON Generation**: Optimized JSON data generation for frontend performance
- **Database Caching**: Local caching of employer data for improved performance

### 📊 **Webinar & Content Registration**
- **Direct Event Registration**: Integration with Workbooks for all event and gated content registrations—not just webinars
- **ACF Integration**: Support for Advanced Custom Fields for all gated content metadata
- **Dynamic Questions**: Collect custom questions per event or content, as defined in ACF
- **Sponsor Opt-in**: Configurable sponsor information opt-in on a per-content basis

### 🎨 **Modern Admin Interface**
- **Vertical Tab Layout**: Intuitive vertical tab navigation for better user experience
- **Real-time Testing**: Built-in connection testing and validation tools
- **User Management**: Comprehensive user listing with Workbooks ID management
- **Debug Dashboard**: Centralized debugging and monitoring interface

---

## Installation & Setup

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Ninja Forms plugin (for form integration features)
- Advanced Custom Fields (ACF) plugin for meta-driven gated content
- Valid Workbooks CRM API credentials
- cURL extension enabled

### Installation Steps

1. **Upload Plugin Files**
   ```
   Upload the plugin files to `/wp-content/plugins/dtr-workbooks-crm-integration/`
   ```

2. **Activate Plugin**
   ```
   Activate through the 'Plugins' screen in WordPress
   ```

3. **Configure Settings**
   ```
   Navigate to 'Workbooks CRM' in the admin menu
   ```

### Configuration

#### API Settings
1. **API URL**: Enter your Workbooks CRM API endpoint
2. **API Key**: Provide your Workbooks API authentication key
3. **Logical Database**: Select your database instance (if applicable)
4. **Test Connection**: Verify connectivity and credentials

#### Field Mapping Configuration
- Map form and ACF fields to Workbooks fields using standardized naming.
- ACF fields for event/campaign reference, dynamic questions, and sponsor opt-in are automatically mapped.
- No need to manually update forms for new gated content fields—just update the ACF field group.

---

## Deployment System

### 🚀 **Professional Deployment Infrastructure**

The plugin includes a comprehensive deployment system for syncing plugin files to multiple environments (staging, production, backup locations) with both automated and manual deployment options.

#### Features
- **Automated Git Hooks**: Sync on every commit using post-commit hooks
- **Manual Deployment**: Run deployments on-demand with the deploy script
- **Multi-Environment Support**: Configure multiple target directories
- **GitHub Actions Integration**: CI/CD pipeline for remote deployments
- **Backup Safety**: Automatic backups before deployments
- **Flexible Configuration**: Environment-specific settings

#### Quick Setup

1. **Navigate to Scripts Directory**
   ```bash
   cd /wp-content/plugins/dtr-workbooks-crm-integration/scripts/
   ```

2. **Run One-Time Setup**
   ```bash
   ./setup.sh
   ```

3. **Configure Your Environments**
   ```bash
   cp config.example.sh config.sh
   # Edit config.sh with your target directories
   ```

#### Available Scripts

| Script | Purpose | Usage |
|--------|---------|-------|
| `setup.sh` | One-time setup and git hook installation | `./setup.sh` |
| `deploy.sh` | Manual deployment to configured environments | `./deploy.sh [environment]` |
| `post-commit-sync.sh` | Automatic sync after git commits | Auto-triggered |
| `config.example.sh` | Template for environment configuration | Copy to `config.sh` |

#### Deployment Options

**1. Automatic Deployment (Git Hooks)**
```bash
# After setup, deployments happen automatically on commit:
git add .
git commit -m "Update plugin features"
# Plugin automatically syncs to configured directories
```

**2. Manual Deployment**
```bash
# Deploy to all environments
./deploy.sh

# Deploy to specific environment
./deploy.sh staging
./deploy.sh production
```

**3. GitHub Actions (Remote Deployment)**
The included `.github/workflows/deploy.yml` enables:
- Automatic deployment on push to main branch
- Secure credential management with GitHub Secrets
- Multi-server deployment support
- Deployment status notifications

#### Configuration Example
```bash
# config.sh example
ENVIRONMENTS=("staging" "production" "backup")
STAGING_PATH="/path/to/staging/wp-content/plugins/dtr-workbooks-crm-integration"
PRODUCTION_PATH="/path/to/production/wp-content/plugins/dtr-workbooks-crm-integration"
BACKUP_PATH="/path/to/backup/wp-content/plugins/dtr-workbooks-crm-integration"
ENABLE_BACKUPS=true
BACKUP_RETENTION_DAYS=30
```

For detailed deployment documentation, see `scripts/README.md`.

---

## Usage Guide

### Gated Content & Ninja Forms Integration

#### Form & Field Setup
- For any gated content (webinar, report, whitepaper, etc.), use ACF to define all required registration fields.
- Forms are generated dynamically—simply update the ACF field group attached to your gated content post type.
- Standard fields (first name, last name, email, employer, job title, marketing preferences, topics of interest) are recognized and mapped.
- Add additional ACF fields (e.g., speaker question, sponsor opt-in) to enable advanced capture and mapping.

#### Registration Process
1. User submits the registration form (auto-generated from ACF) on any gated content post.
2. WordPress user account may be created (if enabled).
3. Form and ACF data is mapped to Workbooks fields.
4. Duplicate check performed via Workbooks API.
5. New person record created in Workbooks (if no duplicate).
6. Event ticket (if relevant) and sales lead **always** created for every registration.
7. All actions logged for debugging.

### Person Record Management

#### Supported Fields
- **Personal Information**: Title, first name, last name, job title
- **Contact Details**: Email, telephone, country, town, postcode
- **Employer Information**: Organization name and ID
- **Marketing Preferences**: DTR news, events, webinars, third-party communications
- **Areas of Interest**: Business, diseases, drugs & therapies, genomics, R&D, technology, tools & techniques

#### Field Mapping Details
```php
// WordPress Meta / ACF → Workbooks Field
'person_personal_title' → 'person_personal_title'
'employer_name' → 'employer_name' (editable field)
'cf_person_aoi_biomarkers' → 'cf_person_biomarkers'
'cf_person_aoi_genomics' → 'cf_person_genomics'
```

### Employer Synchronization

#### Automatic Sync Features
- **Daily Scheduled Sync**: Automatic employer data updates
- **Manual Sync**: On-demand synchronization via admin interface
- **Progress Tracking**: Real-time sync progress monitoring
- **Search & Filter**: Advanced employer search functionality

#### Sync Process
1. Connect to Workbooks API
2. Fetch all employer organizations
3. Update local database cache
4. Generate optimized JSON for frontend use
5. Log sync statistics and errors

### Webinar & Content Registration

#### Registration Flow
1. Select gated content (webinar, report, etc.)
2. Fetch event/content details from ACF fields
3. Submit participant information via generated form
4. Create Workbooks person, event ticket (if applicable), and **always create a sales lead**
5. Optional dynamic questions and sponsor opt-in handled via ACF fields

---
## 👥 Handler & Submission & Full ACF Powered Forms (v1.4.7)

- **Real-Time ACF Answer Logging**: Improved workflows and CRM synchronization for all gated resources, ensuring seamless data capture and integration.

- **Button Animations**: Added interactive button animations for a more engaging and dynamic user experience.

- **Account Customization**: Introduced a fully customizable account dashboard featuring user-specific widgets and flexible layout options.

- **Form Improvements**: Enhanced ACF answer logging, more robust hidden field detection, and improved error recovery for reliable submissions.

- **User Management Features**: Automatic user creation, CRM record generation, and improved dashboard integration for streamlined user management.

**Advanced Ninja Forms & ACF-Powered Gated Content Integration**

- **Real-Time ACF Answer Logging**: The frontend now logs ACF question answers to the browser console in real time as users interact with the form, including dropdowns, checkboxes, radios, and text fields. This helps confirm that all user input is being captured before submission.
- **Post ID & Campaign Logging**: The script logs the current `post_id` (and `campaign` if present) alongside ACF answers, ensuring these hidden fields are always visible in the console for debugging and validation.
- **Robust Field Detection**: Improved JavaScript logic to reliably find hidden fields (like `post_id`) both globally and inside the Ninja Form, so values are always logged even if the form structure changes.
- **Backend Handler Improvements**: The lead generation handler and Ninja Forms hook were updated to ensure that ACF questions (from the `add_questions` field) are always extracted, merged, and logged, and that all lead generation actions are written to `lead-generation-debug.log`.
- **Parse Error Fixes**: Fixed PHP parse errors in the ACF question rendering logic by moving array mapping outside of inline echo statements and using `isset()` for compatibility.


## 🖕 Handler & Submission Process Updates (v1.4.6)

- **Lead Generation Handler Overhaul:**
   - Refactored `lead-generation-handler.php` to match the robust logic of the webinar handler.
   - Now checks for the presence of a `mailing_list_id` before attempting ticket or mailing list creation.
   - If no `mailing_list_id` is present, the handler skips ticket/mailing list creation but still returns success, improving flexibility for non-mailing-list forms.
   - Modularized mailing list upsert logic for maintainability and code reuse.
   - Improved error handling and debug logging throughout the lead gen process.
   - All lead gen actions are now logged to `lead-generation-debug.log` with unique debug IDs for traceability.
   - Enhanced minimal payload testing and field validation for Workbooks API integration.

- **Modular Handler Improvements:**
   - Unified and modularized form handler logic for both webinars and lead generation.
   - Improved dynamic field extraction and mapping using ACF and Ninja Forms meta.
   - Ensured all handlers use `require_once`/`include_once` to prevent function redeclaration errors.


## Handler & Submission Process Updates (v1.4.5)

### 🛠️ Unified Submission Handlers

- **Ninja Forms Handler** (`includes/ninja-forms-simple-hook.php`):
   - Catches all Ninja Forms submissions for both webinars and lead generation.
   - Distinguishes between webinar forms (ID 2) and lead gen forms (ID 31), routing each to the correct process.
   - Extracts and normalizes all relevant fields, including dynamic ACF fields, sponsor opt-in, and speaker questions.
   - Ensures all actions are logged with a unique submission/debug ID for traceability.

- **Webinar Registration Handler** (`includes/webinar-handler.php`):
   - Handles the full registration process for webinars, including:
      1. Validating required data (post ID, email, etc.)
      2. Resolving event references and extracting event IDs
      3. Creating or updating the person in Workbooks CRM
      4. Creating or updating the event ticket
      5. **Mailing List Queue:** Adds or updates the participant in the event's mailing list, including sponsor opt-in and speaker questions
      6. Logs every step and error for debugging
   - Uses a robust debug logger to write to both the PHP error log and a dedicated plugin log file (`logs/gated-post-submissions-debug.log`).
   - All failures and successes are clearly logged, with detailed debug reports for each submission.

### 📬 Mailing List Queue Logic

- After successful ticket creation, the handler checks for a mailing list associated with the event.
- If a mailing list entry for the participant exists, it is updated (including sponsor opt-in and speaker questions); otherwise, a new entry is created.
- All mailing list actions are logged, and exceptions are handled gracefully with detailed error output.

### 📝 Debug Logging & Error Handling
- **Lead Generation Debug Log**: All lead generation actions, including extracted ACF questions and field values, are now logged to `lead-generation-debug.log` for full traceability.

- Every submission (webinar or lead) is assigned a unique debug ID for tracking.
- All major steps (person creation, ticket creation, mailing list update) are logged with timestamps and context.
- Errors and exceptions are captured and included in the debug report, making troubleshooting much easier.

### 🔄 Submission Flow (Webinar Example)

1. **Form Submission:** User submits a Ninja Form (webinar or lead gen)
2. **Handler Routing:** Form is routed to the correct handler based on form ID
3. **Data Extraction:** All fields (including ACF, sponsor opt-in, questions) are extracted and normalized
4. **Person Sync:** Person is created or updated in Workbooks CRM
5. **Ticket Creation:** Event ticket is created/updated for the person
6. **Mailing List Update:** Participant is added/updated in the event's mailing list with all relevant info
7. **Debug Logging:** Every step, success, and error is logged with a unique debug ID

---

## Error Handling & Debugging

#### Comprehensive Logging
- **Registration Debug Log**: Step-by-step registration process logging
- **Workbooks API Log**: Daily API interaction logs
- **WordPress Debug Log**: Integration with WordPress debugging
- **Admin Notifications**: Real-time error reporting in admin interface

#### Debug Features
- Clear log files before testing
- Detailed API response logging
- Timing diagnostics for performance monitoring
- Error recovery mechanisms

---

## Database Schema

#### Custom Tables
```sql
wp_workbooks_employers
├── id (bigint) - Workbooks organization ID
├── name (varchar) - Organization name
└── last_updated (datetime) - Last sync timestamp
```

#### User Meta Fields
```php
// Core Fields
'workbooks_person_id' - Workbooks person ID
'workbooks_object_ref' - Workbooks object reference
'created_via_ninja_form' - Registration source flag
'employer_name' - Editable employer name

// Marketing Preferences
'cf_person_dtr_news' - DTR news subscription
'cf_person_dtr_events' - DTR events subscription
'cf_person_dtr_webinar' - DTR webinar subscription

// Areas of Interest
'cf_person_aoi_*' - AOI field mappings
```

---

## Developer Information

### Plugin Structure
```
dtr-workbooks-crm-integration/
├── dtr-workbooks-crm-integration.php (Main plugin file)
├── includes/
│   ├── ninja-forms-membership-registration.php (Membership registration handler - Form 15)
│   ├── ninja-forms-simple-hook.php (New Ninja Forms handler)
│   ├── helper-functions.php (TOI/AOI mapping)
│   ├── dtr-shortcodes.php (Shortcode functionality)
│   ├── workbooks-employer-sync.php (Employer sync)
│   ├── webinar-handler.php (Webinar registration & mailing list handler)
│   └── media-planner-ajax-handler.php (Media planner AJAX handler)
├── assets/
│   ├── admin.css (Admin styling)
│   └── dtr-ninjaform-title-select.js (Frontend scripts)
├── js/
│   ├── admin.js (Admin interface)
│   ├── employers-sync.js (Employer management)
│   └── webinar-endpoint.js (Webinar functionality)
├── lib/
│   └── workbooks_api.php (Workbooks API wrapper)
├── logs/ (Debug and error logs)
├── scripts/
│   ├── setup.sh
│   ├── deploy.sh
│   ├── post-commit-sync.sh
│   ├── config.example.sh
│   └── README.md
└── .github/
    └── workflows/deploy.yml (GitHub Actions deployment)
```

### API Integration
The plugin uses the Workbooks REST API with:
- **Authentication**: API key-based authentication
- **Endpoints**: People, Organizations, Events, Tickets, Sales Leads
- **Error Handling**: Comprehensive exception catching
- **Rate Limiting**: Respectful API usage patterns

### Hooks & Filters
```php
// User Registration
add_action('nf_after_final_submit', 'nf_workbooks_user_register_submission');

// Employer Sync
add_action('workbooks_daily_employer_sync', 'workbooks_sync_employers_cron');

// AJAX Handlers
add_action('wp_ajax_get_workbooks_titles', 'dtr_ajax_get_workbooks_titles');
```

---

## Changelog

### 2025-09-09
**Admin Webinar System Overhaul & Debugging Enhancements**
- Moved the admin-side webinar registration test form into the plugin admin area for robust, repeatable testing.
- Test form now uses hardcoded, controlled values for all fields, including person_id, to ensure consistent results.
- All field names and values are logged to the browser console and backend debug log for every submission.
- Handler updated to use the submitted person_id if present, matching legacy field structure and debug output format.
- Enforced array-wrapped pattern for all Workbooks API calls in the webinar handler for consistency and reliability.
- Ticket creation and all registration steps are now fully logged in the dedicated debug log (`admin/admin-webinar-debug.log` or `includes/webinar-debug.log`).
- Debug output now matches legacy format, making it easier to compare with previous logs and troubleshoot issues.
- All changes validated: registration, ticket creation, and debug output confirmed working end-to-end.

**UI/UX Improvements & Save for Later**
- Save button text now changes to "Saved" when a post is saved, and reverts if removed.
- Users can add a personal note when saving a post to their collection.
- Saved posts and notes are visible in the "My Account" section for later viewing.
- All save/remove/note actions are AJAX-powered for instant feedback.



### Version 2.0 Features (Coming Soon)

### Version 1.4.7 (2025-08-25/26)
- **Real-Time ACF Answer Logging**: Frontend logs ACF answers and hidden fields as users interact with the form.
- **Improved Hidden Field Logging**: More robust detection and logging of `post_id` and `campaign` fields.
- **Backend Handler Parity**: Lead generation handler now mirrors webinar handler for ACF question extraction and logging.
- **Parse Error Fixes**: PHP code for ACF question rendering is now compatible with all environments.
- **Site Selection on Setup**: When setting up the plugin, administrators will be able to select the relevant site or brand. The plugin will then auto-configure custom field mappings and integration settings for Workbooks CRM, eliminating all hardcoded field lists or site-specific code.
- **Advanced Backend Reporting**: A comprehensive reporting suite in the Gated Content Admin section will provide analytics and exportable reports for all registrations, leads, and CRM sync activity—giving marketing and editorial teams actionable insights into content performance.

### Version 1.4.5
- **Universal Gated Content Integration**: Registration and CRM sync now work for all gated content post types (not just webinars), using ACF-driven dynamic forms. Adding or changing fields on content is now as easy as updating the ACF group—no more manual form edits or code updates for new fields.
- **Always Create Sales Leads**: Every event/content registration (via Ninja Forms, Media Planner, etc.) now always results in a new sales lead in Workbooks CRM. This ensures every engagement is captured, even for duplicate people or existing tickets.
- **ACF-Driven Form Generation**: Registration forms for gated content are auto-generated from ACF field groups, so the backend forms always match the content requirements.
- **Debug Logging Enhanced**: Lead creation, ticket generation, and event/person sync are now logged with greater detail, including Workbooks object IDs and clear success/failure signals.
- **Documentation and File Structure**: Major updates to documentation, changelog history, and feature explanations to reflect the new universal content gating approach and improved process.
- **Handler & Submission Process Overhaul**: New unified Ninja Forms and webinar handlers, with robust debug logging, stepwise processing, and a dedicated mailing list queue system for all webinar/event registrations. All actions are logged with unique debug IDs for traceability and troubleshooting.

### Version 1.4.4
- **Sales Lead Enforcement**: Ensured that a new sales lead is always created for every event/ticket registration, regardless of existing tickets or person records, across Ninja Forms and Media Planner integration.
- **Unified Debug Logging**: Introduced celebratory and error logging for every step of lead and ticket creation, simplifying troubleshooting and confirming successful actions.
- **ACF Webinar Questions & Campaigns**: Added full support for registering and mapping speaker questions, sponsor opt-ins, and campaign references from ACF fields on webinars/events.
- **Admin UI & Logging Improvements**: Improved WordPress admin interface with clearer options, logging, and sync status feedback.
- **File Structure & Script Automation**: Refactored scripts directory, improved deployment automation, and enhanced backup procedures for safe releases.

### Version 1.4.3
- **Enhanced Field Mapping**: Switched employer mapping to use the editable `employer_name` field for more reliable CRM matching.
- **AOI/TOI Mapping Overhaul**: Improved handling and mapping of Areas of Interest (AOI) and Topics of Interest (TOI), including special logic for genomics and related fields.
- **Error Handling**: Introduced comprehensive error trapping, logging, and recovery for API failures and data mismatches.
- **Duplicate Detection**: Added robust duplicate checking using email address with Workbooks API, reducing accidental duplicate person records.
- **Admin UI Modernization**: Updated admin panels with vertical tabbed navigation, showing mapping tables and debugging tools.
- **Debugging & API Response Handling**: Improved debug logs with step-by-step detail, and corrected API response parsing, especially for extracting Workbooks IDs.

### Version 1.4.2
- **Stable Core CRM Integration**: Marked the first stable release, including automated person record creation, employer sync, and basic lead generation.
- **Basic Admin Tools**: Provided admin page for API setup and simple connection testing.
- **Initial Field Mapping**: Mapped standard registration fields to Workbooks, including personal, contact, and employer info.
- **Cron-Based Employer Sync**: Scheduled daily sync of employer data from Workbooks to local database.

### Version 1.4.1
- **Ninja Forms Beta Integration**: Added support for Ninja Forms-based user registration and lead capture, including automatic creation of WordPress users and CRM person records.
- **Early AOI/TOI Mapping**: Introduced initial AOI/TOI mapping logic, with admin display for field relationships.
- **Workbooks API Error Logging**: Added first version of debug logs for API errors and registration issues.
- **User Meta Storage**: Started storing Workbooks person IDs and mapping data on WordPress user meta.

### Version 1.4.0
- **Alpha Workbooks Connectivity**: Enabled basic API connectivity with Workbooks CRM, supporting person record creation from static forms.
- **Employer Sync Prototype**: First implementation of AJAX-based employer search and caching.
- **Hardcoded Field Mapping**: Initial hardcoded mapping for registration fields to Workbooks.
- **Debug Log Prototyping**: Began development of debug log files for API troubleshooting.

### Version 1.3.4
- **Performance & Caching**: Improved employer sync speed and optimized local caching for faster lookups.
- **Duplicate Handling**: Enhanced duplicate email detection logic for more accurate CRM person record matching.
- **UI Polish**: Refined admin screens and added contextual help for field mapping.

### Version 1.3.3
- **Form Field Validation**: Added backend validation for required fields on all registration forms.
- **Employer Lookup UX**: Improved autocomplete and dropdown experience for employer selection.
- **Better Error Feedback**: Displayed specific CRM/API errors to admins in the UI.

### Version 1.3.2
- **Dynamic Field Maps**: Allowed admins to remap form fields to CRM fields via the admin interface.
- **Expanded AOI Support**: Added new AOI categories and improved mapping logic.
- **Logging Upgrades**: Added log levels and filtering to debug logs.

### Version 1.3.1
- **Bugfix Release**: Addressed edge-case bugs in registration flow (e.g., missing employer, empty email).
- **API Timeout Handling**: Improved recovery from slow or failed Workbooks API requests.

### Version 1.3.0
- **Media Planner Integration**: Added support for Media Planner gated content and registration forms, synced to Workbooks CRM.
- **Form Customization**: Allowed custom questions and sponsor opt-ins on media planner content.
- **Admin Reporting**: Added basic reporting of registration stats in the admin dashboard.

### Version 1.2.3
- **Webinar Enhancements**: Improved mapping for webinar-specific questions and campaign reference fields.
- **Scheduling Fixes**: Fixed issues where scheduled employer syncs would occasionally fail.
- **Multi-language Support**: Added preliminary support for multilingual forms.

### Version 1.2.2
- **API Refactoring**: Refactored Workbooks API wrapper for better reliability and maintainability.
- **Data Sanitization**: Improved sanitization and escaping of form inputs before CRM sync.
- **Admin Notices**: Added more helpful admin notices for setup and troubleshooting.

### Version 1.2.1
- **UX Improvements**: Streamlined the registration flow for users, reducing friction and confusion.
- **Debug Mode Toggle**: Added a toggle in admin for enabling/disabling detailed debug logging.
- **Field Prepopulation**: Introduced logic to prepopulate form fields with known user data.

### Version 1.2.0
- **Gated Content Foundation**: Laid groundwork for supporting all gated content types, not just webinars.
- **Ninja Forms Field Mapping**: Upgraded Ninja Forms integration to support dynamic/optional fields.
- **Employer Table Migration**: Migrated employer sync storage to a dedicated custom table.

### Version 1.1.0
- **Employer Sync Improvements**: Improved reliability of employer sync, added manual sync button in admin.
- **User Meta Enhancements**: Expanded storage of Workbooks-related data in user meta.
- **Field Mapping UI**: Added visual field mapping table in admin for easier reference.

### Version 1.0.0
- **Plugin Scaffold Created**: Set up the core WordPress plugin structure, hooks, and initial admin menu.
- **Workbooks API Authentication**: Implemented first version of API key and endpoint configuration.
- **Prototype Person Creation**: Added basic form for creating a Workbooks person from WordPress.
- **Early Error Handling**: Logged API success/failure to a basic debug file.

### Major Updates (2025)
- **AJAX-powered My Account Details**: The my-account details form now uses AJAX for instant feedback and animated button states, matching the UX of other shortcodes.
- **Centralized AOI/TOI Mapping**: All mapping logic is now in `class-helper-functions.php` and used consistently across registration, shortcodes, and user update flows.
- **UI Consistency**: All update buttons use `<input type="submit">` and have consistent animation and feedback.
- **Email Address in Account Table**: The account details table now displays the user's email address (not editable).
- **Robust Duplicate Detection**: Improved duplicate detection for both WordPress and Workbooks CRM, with exact email match checks.
- **Improved Logging**: Enhanced debug and operational logging, with logs gated by debug mode and archived on disable.
- **Employer Sync**: Daily and manual sync of employer data, with Select2 endpoint and JSON fallback.
- **Dynamic Gated Content**: Forms for gated content are generated from ACF field groups, requiring no manual edits for new fields.
- **Ninja Forms - Full Country Names Plugin**: Adds full country name support to Ninja Forms country fields and any select with the class `full-iso-country-names` (see below).

---

## License
Proprietary software developed by Supersonic Playground for DTR (Drug Target Review). All rights reserved.