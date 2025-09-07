# Wordpress to Workbooks CRM API Integration

**Author:** Supersonic Playground / Levon Gravett  
**Website:** https://www.supersonicplayground.com  
**Version:** 2.1.0  
**License:** Proprietary. All rights reserved.

A production-ready WordPress plugin that integrates WordPress with the DTR Workbooks CRM. V2 delivers modular admin code, robust gated debugging, ACF/Ninja Forms-driven dynamic content gating, reliable person/employer/ticket/lead creation and synchronization, and advanced analytics.

---

## Comprehensive Feature Summary

A comprehensive WordPress plugin enabling seamless integration between WordPress and DTR Workbooks CRM. This solution powers automated user registration, advanced ACF-driven content gating, dynamic form generation from content metadata, robust event/ticket/lead creation, intelligent employer search, bidirectional account preference syncing, and detailed debugging across ALL gated content types (not just webinars).

---

## 🚀 Overview & Core Features

### 🔗 **Core CRM Integration**
- **API Integration**: Secure connection to Workbooks CRM using API keys with timeout and error handling
- **Person Record Management**: Create, update, and sync person records with comprehensive field mapping
- **Employer Synchronization**: Intelligent employer matching and organization management
- **Duplicate Detection**: Smart duplicate checking using email matching with Workbooks API
- **Comprehensive Logging**: Detailed debug logs for troubleshooting and monitoring

### 📝 **Gated Content Enhancements & Microanimation Additions**
- **🔐 Enhanced Gated Content**: Dynamic gated content generation based on post type. Streamlined content access tied to CRM campaign tracking.
- **🎨 User Interface Enhancements**: Smooth button animations and micro-interactions. Improved content presentation for gated resources.
- **👤 Account Management**: Customizable dashboards for users to manage their profiles and preferences. Full integration of CRM-linked user data within WordPress accounts.

### 🥷 **Advanced Ninja Forms & ACF-Powered Gated Content Integration**
- **Real-Time ACF Answer Logging**: The frontend now logs ACF question answers to the browser console in real time as users interact with the form, including dropdowns, checkboxes, radios, and text fields.
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

## 🛠️ Installation & Setup

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Ninja Forms plugin (for form integration features)
- Advanced Custom Fields (ACF) plugin for meta-driven gated content
- Valid Workbooks CRM API credentials
- cURL extension enabled

### Installation Steps

1. **Upload Plugin Files**
   - Copy the plugin directory to `/wp-content/plugins/dtr-workbooks-crm-integration/`.
2. **Activate Plugin**
   - Activate through the 'Plugins' screen in WordPress.
3. **Configure Settings**
   - Navigate to 'Workbooks CRM' in the admin menu.

### Configuration

#### API Settings
1. **API URL**: Enter your Workbooks CRM API endpoint
2. **API Key**: Provide your Workbooks API authentication key
3. **Test Connection**: Verify connectivity and credentials

#### Field Mapping Configuration
- Map form and ACF fields to Workbooks fields using standardized naming.
- ACF fields for event/campaign reference, dynamic questions, and sponsor opt-in are automatically mapped.
- No need to manually update forms for new gated content fields—just update the ACF field group.

---

## 📖 Usage Guide

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
- **Advanced Ninja Forms & ACF-Powered Gated Content Integration**: Full logging, mapping, and error handling as described in the feature summary above.

## 🖕 Handler & Submission Process Updates (v1.4.6)

- Refactored lead generation handler and mailing list logic for robustness and flexibility.
- Modularized and unified handler logic for submissions.
- Improved logging, error handling, and field validation.

## Handler & Submission Process Updates (v1.4.5)

- Unified Ninja Forms and webinar handlers with robust debug logging.
- Mailing list queue system with clear step-by-step logging and error capture.

---

## Error Handling & Debugging

- **Comprehensive Logging**: Step-by-step registration, API, and operational logs.
- **Debug Features**: Log clearing, API response logging, timing diagnostics, and error recovery.

---

### API Integration

- Uses Workbooks REST API with API key authentication, robust error handling, and rate limiting.

---

## 📝 Changelog

### 2.1.0 (In Progress – September 2025)
- Unified membership registration handler
- Paginated Select2 employer search (admin/frontend)
- Employer JSON relocation and legacy fallback
- Centralized logging/log reset workflow
- Bidirectional admin ↔ user preference & AOI sync
- Genomics key migration & cleanup utility
- Responsive admin tables, improved column sizing
- Toggle button for Workbooks fields (improved JS/accessibility)
- Ninja Forms country select improvements

### 2.0.0
- Modular admin, gated debug logging, verification fetch, ACF/Ninja Forms gating

### 1.4.x Series
- Universal gated content integration; always-create sales lead
- Enhanced AOI/TOI mapping & employer sync improvements
- Dynamic ACF question extraction & sponsor opt-in handling
- Performance & caching refinements for employer lookup

### 1.4.7
- Real-time ACF answer logging (frontend)
- Improved hidden field logging (post_id, campaign)
- Backend handler parity: lead gen matches webinar handler for ACF question extraction/logging
- PHP parse error fixes (ACF rendering logic)
- Site selection on setup: auto-configures field mappings per brand/site
- Advanced backend reporting in Gated Content Admin

### 1.4.6
- Refactored lead generation handler for mailing list logic
- Modularized mailing list upsert logic
- Improved error handling and debug logging
- Unified Ninja Forms and webinar handlers

### 1.4.5
- Universal gated content integration (all post types, ACF-driven)
- Always create sales leads for all gated submissions
- Enhanced debug logging
- Handler & submission process overhaul

### 1.4.4
- Enforced sales lead creation for every registration
- Unified debug logging (success/error)
- ACF webinar questions & campaigns support
- UI/admin improvements

### 1.4.3
- Enhanced employer mapping (editable field)
- AOI/TOI mapping overhaul (with genomics logic)
- Improved error handling and duplicate detection

### 1.4.2
- Stable core CRM integration
- Admin tools for API setup/testing
- Initial field mapping
- Cron-based employer sync

### 1.4.1
- Ninja Forms beta integration
- Early AOI/TOI mapping
- Workbooks API error logging
- User meta storage

### 1.4.0
- Alpha Workbooks connectivity
- Employer sync prototype
- Hardcoded field mapping
- Debug log prototyping

### 1.3.4
- Performance/caching improvements for employer sync
- Duplicate handling upgrades
- UI/admin polish

### 1.3.3
- Backend validation for required fields
- Improved employer lookup UX
- CRM/API error feedback in UI

### 1.3.2
- Dynamic field maps (admin remapping)
- Expanded AOI support
- Log levels/filtering in debug logs

### 1.3.1
- Bugfixes (registration, employer lookup)
- API timeout handling

### 1.3.0
- Media Planner integration
- Custom/sponsor questions from content
- Admin reporting

### 1.2.3
- Webinar mapping improvements
- Scheduling fixes
- Multi-language support

### 1.2.2
- API wrapper refactor
- Improved sanitization
- Admin notices

### 1.2.1
- UX improvements (registration flow, debug toggle)
- Field prepopulation

### 1.2.0
- Gated content foundation (beyond webinars)
- Ninja Forms dynamic field mapping
- Employer table migration

### 1.1.0
- Employer sync improvements
- Extended user meta
- Admin field mapping UI

### 1.0.0
- Plugin scaffold, API authentication, basic person creation, early error handling

### Major Updates (2025)
- AJAX-powered My Account Details
- Centralized AOI/TOI Mapping
- UI Consistency
- Email Address in Account Table
- Robust Duplicate Detection
- Improved Logging
- Employer Sync (daily/manual)
- Dynamic Gated Content (ACF-driven forms)
- Ninja Forms - Full Country Names Plugin

---

## 📁 Plugin Structure

```
dtr-workbooks-crm-integration/
├── dtr-workbooks-crm-integration.php
├── includes/
│   ├── class-acf-ninjaforms-merge.php
│   ├── class-array-merge-safety.php
│   ├── class-employer-sync.php
│   ├── class-form-submission-override.php
│   ├── class-helper-functions.php
│   ├── class-loader.php
│   ├── core/
│   ├── form-handler-gated-content-reveal.php
│   ├── form-handler-media-planner.php
│   ├── form-handler-membership-registration.php
│   ├── form-handler-webinars.php
│   ├── form-submission-processors-ninjaform-hooks.php
│   └── form-submission-processors-submission-fix.php
├── shortcodes/
│   ├── dtr-forgot-password.php
│   ├── dtr-my-account-details.php
│   └── dtr-shortcodes.php
├── js/
├── assets/
│   └── json/
│       └── employers.json
├── logs/
├── lib/
│   └── workbooks_api.php
├── scripts/
│   ├── setup.sh
│   ├── deploy.sh
│   ├── post-commit-sync.sh
│   ├── config.example.sh
│   └── README.md
```

---

## 📞 Support

- Website: https://www.supersonicplayground.com  
- Developer: Levon Gravett  

---

## License

Proprietary software developed by Supersonic Playground for DTR (Drug Target Review). All rights reserved.

