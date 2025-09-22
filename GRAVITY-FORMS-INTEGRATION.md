# Gravity Forms Integration - Complete Setup

## Overview
This document outlines the complete integration setup for Gravity Forms ID 1 with the DTR Workbooks CRM system.

## Files Created/Modified

### 1. Gravity Forms Handler
**File:** `includes/gravity-forms-handler.php`
- **Purpose:** Main form processing handler for Gravity Forms ID 1
- **Key Features:**
  - Field mapping from form to CRM
  - User account creation
  - Workbooks CRM synchronization
  - Marketing and topic preferences handling

### 2. Employer Field Enhancement Script
**File:** `js/gravityforms-employers-field.js`
- **Purpose:** Dynamic employer field population from Workbooks database
- **Key Features:**
  - AJAX loading of employer data
  - Search functionality
  - Support for Chosen/Select2 libraries
  - Comprehensive debugging logs

### 3. Main Plugin Integration
**File:** `dtr-workbooks-crm-integration.php`
- Modified to include Gravity Forms handler
- Added JavaScript enqueuing for employer field enhancement

## Field Mapping Configuration

Based on the actual Gravity Form HTML structure:

```php
$field_map = array(
    'title' => '35',           // Mr/Mrs/Ms dropdown
    'first_name' => '1.3',     // First name input
    'last_name' => '1.6',      // Last name input
    'email' => '2',            // Email address
    'employer' => '37',        // Employer selection field
    'marketing_emails' => array('3.1'), // Marketing emails checkbox
    'topic_preferences' => array('38.1', '38.2', '38.3', '38.4', '38.5', '38.6') // Topics checkboxes
);
```

## Form Structure

### Step 1: Personal Details
- Field 35: Title (Mr/Mrs/Ms/Dr/Prof)
- Field 1.3: First Name
- Field 1.6: Last Name  
- Field 2: Email Address
- Field 37: Employer (enhanced with AJAX search)

### Step 2: Marketing Preferences
- Field 3.1: Marketing emails checkbox

### Step 3: Topics of Interest
- Field 38.1: Personal Finance
- Field 38.2: Economics  
- Field 38.3: Business
- Field 38.4: Politics
- Field 38.5: International
- Field 38.6: Other

## CRM Integration

### User Creation
- Creates WordPress user account
- Maps form data to user meta fields
- Handles duplicate email prevention

### Workbooks Sync
- Creates/updates Person record in Workbooks CRM
- Maps checkbox preferences to custom fields
- Handles employer association

### Field Mapping to CRM
```php
// User Meta Fields
'user_title' => Field 35
'first_name' => Field 1.3  
'last_name' => Field 1.6
'user_email' => Field 2
'employer_name' => Field 37
'marketing_emails' => Field 3.1 status
'personal_finance' => Field 38.1 status
'economics' => Field 38.2 status
'business' => Field 38.3 status
'politics' => Field 38.4 status
'international' => Field 38.5 status
'other_topics' => Field 38.6 status
```

## Employer Field Enhancement

### JavaScript Functionality
- Targets field `#input_1_37`
- Loads employers via AJAX from existing endpoint
- Supports both Chosen and Select2 libraries
- Provides search-as-you-type functionality

### Debug Logging
Comprehensive console logging for troubleshooting:
- Form detection
- Field location
- Script initialization
- AJAX responses
- Enhancement library detection

## Testing Workflow

1. **Form Detection:** Check console for Gravity Form 1 detection
2. **Field Enhancement:** Verify employer field populates with search capability
3. **Form Submission:** Test complete form submission process
4. **User Creation:** Verify WordPress user account creation
5. **CRM Sync:** Check Workbooks for new Person record with mapped data

## Troubleshooting

### Common Issues
1. **Employer field not enhancing:** Check console logs for field detection
2. **Form not submitting:** Verify field mapping IDs match actual form structure
3. **CRM sync failing:** Check Workbooks API credentials and field mapping

### Debug Commands
```javascript
// Check if script loaded
console.log('[GF Employers] Script loaded');

// Check form detection
console.log('Forms found:', $('form').length);

// Check field detection  
console.log('Employer field:', $('#input_1_37').length);
```

## Next Steps

1. Test form submission with debug mode enabled
2. Verify employer field enhancement in browser console
3. Check Workbooks CRM for successful data sync
4. Validate user account creation and meta field mapping