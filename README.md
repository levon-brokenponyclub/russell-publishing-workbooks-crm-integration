# Workbooks CRM API Integration

## Overview

**Author**: Levon Gravett  
**Developed For**: [https://www.supersonicplayground.com](https://www.supersonicplayground.com)  
**Version**: 1.4.4

A comprehensive WordPress plugin that provides seamless integration between WordPress and Workbooks CRM. This plugin enables automatic user registration, CRM data synchronization, and advanced field mapping with robust error handling and debugging capabilities.

## Key Features

### 🔗 **Core CRM Integration**
- **API Integration**: Secure connection to Workbooks CRM using API keys with timeout and error handling
- **Person Record Management**: Create, update, and sync person records with comprehensive field mapping
- **Employer Synchronization**: Intelligent employer matching and organization management
- **Duplicate Detection**: Smart duplicate checking using email matching with Workbooks API
- **Comprehensive Logging**: Detailed debug logs for troubleshooting and monitoring

### 📝 **Advanced Ninja Forms Integration**
- **Automatic User Creation**: Seamlessly create WordPress users from form submissions
- **CRM Record Creation**: Automatically generate corresponding Workbooks person records
- **Field Mapping**: Complete mapping of all form fields to CRM equivalents including:
  - Personal person details
  - Contact information (name, email, telephone, address)
  - Employment details with editable employer names
  - Marketing preferences and subscription settings
  - Topics of Interest (TOI) to Areas of Interest (AOI) mapping
- **Error Recovery**: Robust error handling with detailed logging and recovery mechanisms

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

### 📊 **Webinar Integration**
- **Event Registration**: Direct integration with Workbooks events for webinar registrations
- **ACF Integration**: Support for Advanced Custom Fields for webinar metadata
- **Speaker Questions**: Optional speaker question submission with registrations
- **Sponsor Opt-in**: Configurable sponsor information opt-in functionality


### 🔒 **Gated Content & Article Previews**
- **Plugin-Based Gating System**: All article gating logic is now handled by the plugin, with no reliance on ACF fields.
- **Shortcode for Gated Content**: Use `[gated_preview_content id="123"]` or `[gated_preview_content post_id="123"]` to display preview/full content based on user login and gating status.
- **Preview & Full Content Logic**: Unregistered users see only the preview text; registered users see the full article content.
- **Admin UI for Gated Content**: Modern admin interface for managing all gated content fields, including preview text, images, video, gallery, CTA button, and Workbooks integration fields.
- **Dynamic List & Search**: Easily search, filter, and manage all gated articles from a single admin screen.
- **View Shortcode Button**: Instantly view the shortcode and all associated data for any gated article in the admin UI.
- **Debug Output & Logging**: Built-in debug output and logging for troubleshooting shortcode rendering and gating logic.
- **No ACF Required**: All gating fields are stored as post meta and managed via the plugin interface.
- **Vertical Tab Layout**: Intuitive vertical tab navigation for better user experience (admin UI).
- **Real-time Testing**: Built-in connection testing and validation tools.
- **User Management**: Comprehensive user listing with Workbooks ID management.
- **Debug Dashboard**: Centralized debugging and monitoring interface.

## Installation & Setup

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Ninja Forms plugin (for form integration features)
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
The plugin automatically maps form fields to Workbooks fields using the following conventions:
- `person_personal_title` → Personal titles
- `employer_name` → Editable employer field (recommended)
- `cf_person_aoi_*` → Areas of Interest fields
- Marketing preferences and subscription fields

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

## Usage Guide

### Ninja Forms Integration

#### Form Field Setup
Configure your Ninja Forms with the following field names for automatic mapping:
- **Title**: `person_personal_title`
- **First Name**: `first_name`
- **Last Name**: `last_name`
- **Email**: `email`
- **Employer**: `employer_name`
- **Job Title**: `job_title`
- **Marketing Preferences**: `cf_person_marketing_*` (customize as needed)
- **Topics of Interest**: Use predefined TOI options

#### Registration Process
1. User submits Ninja Form
2. WordPress user account created automatically
3. Form data mapped to Workbooks fields
4. Duplicate check performed via Workbooks API
5. New person record created in Workbooks (if no duplicate)
6. All actions logged for debugging

### Person Record Management

#### Supported Fields
- **Personal Information**: Title, first name, last name, job title
- **Contact Details**: Email, telephone, country, town, postcode
- **Employer Information**: Organization name and ID
- **Marketing Preferences**: News, events, webinars, third-party communications
- **Areas of Interest**: Business, diseases, drugs & therapies, genomics, R&D, technology, tools & techniques

#### Field Mapping Details
```php
// WordPress Meta → Workbooks Field
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

### Webinar Registration

#### Registration Flow
1. Select webinar from available events
2. Fetch event details from Workbooks
3. Submit participant information
4. Create webinar registration in Workbooks
5. Optional speaker questions and sponsor opt-in


### Gated Content Integration
- **No ACF Required**: All gating logic and fields are managed by the plugin.
- **Shortcode Usage**: Place `[gated_preview_content id="123"]` or `[gated_preview_content post_id="123"]` in any post, page, or template to display gated content.
- **Admin Management**: Use the Gated Content admin screen to configure preview and main content, media, and integration fields for each article.

## Advanced Features

### Topics of Interest Mapping

#### Automatic AOI Population
When users select Topics of Interest during registration:
- **Genomics** → Automatically sets `cf_person_biomarkers` and `cf_person_genomics`
- **Business** → Sets `cf_person_business`
- **Technology** → Sets `cf_person_technology`
- Additional mappings configurable via helper functions

#### Admin Visualization
The admin interface provides:
- Complete TOI to AOI mapping table
- Visual badges showing active mappings
- Field name reference for developers
- Mapping count statistics


### Error Handling & Debugging

#### Comprehensive Logging & Debugging
- **Registration Debug Log**: Step-by-step registration process logging
- **Workbooks API Log**: Daily API interaction logs
- **WordPress Debug Log**: Integration with WordPress debugging
- **Gated Content Debug Log**: Dedicated logging for all gated content and shortcode operations
- **Admin Notifications**: Real-time error reporting in admin interface

#### Debug Features
- Clear log files before testing
- Detailed API and shortcode response logging
- Timing diagnostics for performance monitoring
- Error recovery mechanisms
- Debug output panels in admin and on the frontend for gated content troubleshooting

### Database Schema

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
'cf_person_news' - News subscription
'cf_person_events' - Events subscription
'cf_person_webinar' - Webinar subscription

// Areas of Interest
'cf_person_aoi_*' - AOI field mappings
```

## Developer Information

### Plugin Structure
```
dtr-workbooks-crm-integration/
├── dtr-workbooks-crm-integration.php (Main plugin file)
├── includes/
│   ├── nf-user-register.php (Ninja Forms handler)
│   ├── helper-functions.php (TOI/AOI mapping)
│   ├── dtr-shortcodes.php (Shortcode functionality)
│   └── workbooks-employer-sync.php (Employer sync)
├── assets/
│   ├── admin.css (Admin styling)
│   └── dtr-ninjaform-title-select.js (Frontend scripts)
├── js/
│   ├── admin.js (Admin interface)
│   ├── employers-sync.js (Employer management)
│   └── webinar-endpoint.js (Webinar functionality)
├── lib/
│   └── workbooks_api.php (Workbooks API wrapper)
└── logs/ (Debug and error logs)
```

### API Integration
The plugin uses the Workbooks REST API with:
- **Authentication**: API key-based authentication
- **Endpoints**: People, Organizations, Events
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

## Changelog

### Version 1.4.3 (Current)
- ✅ **Enhanced Field Mapping**: Fixed employer field to use editable `employer_name`
- ✅ **Improved AOI Mapping**: Corrected AOI field prefix handling
- ✅ **Updated TOI Logic**: Enhanced genomics field mapping
- ✅ **Robust Error Handling**: Comprehensive error logging and recovery
- ✅ **Duplicate Detection**: Smart email-based duplicate checking
- ✅ **Modern Admin UI**: Vertical tab layout with improved UX
- ✅ **Enhanced Debugging**: Detailed step-by-step logging
- ✅ **API Response Parsing**: Fixed person ID extraction from `affected_objects`

### Previous Versions
- **1.4.2**: Initial stable release with core CRM integration
- **1.4.1**: Beta release with Ninja Forms integration
- **1.4.0**: Alpha release with basic Workbooks connectivity

## Support & Documentation

### Debugging
Enable WordPress debugging and check the following log files:
- `/wp-content/debug.log` - WordPress debug log
- `/wp-content/plugins/dtr-workbooks-crm-integration/logs/` - Plugin-specific logs
- `/wp-content/plugins/dtr-workbooks-crm-integration/includes/register-debug.log` - Registration debug log

### Common Issues
1. **API Connection Failures**: Verify API credentials and network connectivity
2. **Field Mapping Issues**: Check field naming conventions in forms
3. **Duplicate Records**: Review email-based duplicate detection logic
4. **Employer Sync Problems**: Ensure sufficient memory and execution time

### Support
For technical support or custom development:  
- 🌐 [https://www.supersonicplayground.com](https://www.supersonicplayground.com)  
- 📧 Levon Gravett: levon.gravett@supersonicplayground.com
- **Documentation**: Refer to inline code comments and debug logs

## License

This plugin is proprietary software developed by Levon Gravett for Supersonic Playground. All rights reserved.