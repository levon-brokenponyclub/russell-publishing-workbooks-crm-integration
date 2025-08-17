# DTR - Workbooks CRM API Integration

## Overview

**Author**: Supersonic Playground / Levon Gravett  
**Website**: [https://www.supersonicplayground.com](https://www.supersonicplayground.com)  
**Version**: 1.4.5

A comprehensive WordPress plugin enabling seamless integration between WordPress and DTR Workbooks CRM. This solution powers automated user registration, advanced ACF-driven content gating, dynamic form generation from content metadata, robust event/ticket/lead creation, and detailed debugging—across ALL gated content types (not just webinars).

---

## Key Features

### 🔗 **Core CRM Integration**
- **API Integration**: Secure connection to Workbooks CRM using API keys with timeout and error handling
- **Person Record Management**: Create, update, and sync person records with comprehensive field mapping
- **Employer Synchronization**: Intelligent employer matching and organization management
- **Duplicate Detection**: Smart duplicate checking using email matching with Workbooks API
- **Comprehensive Logging**: Detailed debug logs for troubleshooting and monitoring

### 📝 **Advanced Ninja Forms & ACF-Powered Gated Content Integration**
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
│   ├── nf-user-register.php (Ninja Forms handler)
│   ├── ninja-forms-simple-hook.php (New Ninja Forms handler)
│   ├── helper-functions.php (TOI/AOI mapping)
│   ├── dtr-shortcodes.php (Shortcode functionality)
│   ├── workbooks-employer-sync.php (Employer sync)
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

### Version 1.4.5
- ✅ **Universal Gated Content Integration**: All gated content post types (not just webinars) now use ACF-driven dynamic forms for Workbooks CRM registration, with zero-edit form maintenance.
- ✅ **Always Create Sales Leads**: The lead creation process ensures a new sales lead is always created for every event/content registration (Ninja Forms, Media Planner, etc.), even for duplicates or existing tickets.
- ✅ **ACF-Driven Form Generation**: Forms are now generated based on ACF fields attached to gated content, not hardcoded field lists.
- ✅ **Debug Logging Enhanced**: Additional logging for lead creation, including all IDs and celebratory confirmation messages.
- ✅ **Documentation and File Structure**: Updated to reflect the new universal content gating and lead registration process.

### Version 1.4.4
- Always creates sales leads on event/ticket registration (Ninja Forms & Media Planner)
- Unified debug logging with celebratory confirmation on success
- Complete ACF support for event/webinar questions and campaign references
- Improved admin UI and logging for troubleshooting
- Updated file structure and script automation
- Enhanced deployment and backup integration

### Previous Versions
- **1.4.3**: Enhanced field mapping, improved AOI/TOI logic, better error handling, admin UI and debugging
- **1.4.2**: Initial stable release with core CRM integration
- **1.4.1**: Beta release with Ninja Forms integration
- **1.4.0**: Alpha release with basic Workbooks connectivity

---

## Support & Documentation

### Debugging
Enable WordPress debugging and check the following log files:
- `/wp-content/debug.log` - WordPress debug log
- `/wp-content/plugins/dtr-workbooks-crm-integration/logs/` - Plugin-specific logs
- `/wp-content/plugins/dtr-workbooks-crm-integration/includes/register-debug.log` - Registration debug log

### Common Issues
1. **API Connection Failures**: Verify API credentials and network connectivity
2. **Field Mapping Issues**: Check field naming conventions in forms and ACF
3. **Duplicate Records**: Review email-based duplicate detection logic
4. **Employer Sync Problems**: Ensure sufficient memory and execution time

### Support
For technical support and customization requests:
- **Website**: [https://www.supersonicplayground.com](https://www.supersonicplayground.com)
- **Email**: Contact through the website
- **Developer**: Levon Gravett
- **Documentation**: Refer to inline code comments and debug logs

---

## License

This plugin is proprietary software developed by Supersonic Playground for DTR (Drug Target Review) and Levon Gravett.  
All rights reserved.
