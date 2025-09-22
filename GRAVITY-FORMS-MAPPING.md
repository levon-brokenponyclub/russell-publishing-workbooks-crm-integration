# Gravity Forms Handler - Field Mapping Guide

The new Gravity Forms handler has been created at:
`/wp-content/plugins/dtr-workbooks-crm-integration/includes/gravity-forms-handler.php`

## Current Field Mapping (Update these based on your actual form)

The handler currently uses these field IDs - **YOU NEED TO UPDATE THESE** based on your actual Gravity Form field IDs:

```php
$field_map = [
    'title' => '1',           // Title field ID (Mr., Ms., Dr., etc.)
    'first_name' => '2',      // First name field ID  
    'last_name' => '3',       // Last name field ID
    'email' => '4',           // Email field ID
    'password' => '5',        // Password field ID
    'employer' => '6',        // Employer field ID
    'job_title' => '7',       // Job title field ID
    'telephone' => '8',       // Phone field ID
    'country' => '9',         // Country field ID
    'town' => '10',           // Town/City field ID
    'postcode' => '11',       // Postcode field ID
    'marketing_prefs' => '12', // Marketing preferences field ID (checkbox)
    'topics_interest' => '13'  // Topics of interest field ID (checkbox)
];
```

## How to Find Your Field IDs

1. Go to WordPress Admin → Forms → Edit your membership registration form
2. Click on each field and note the Field ID in the field settings
3. Update the `$field_map` array in the handler with your actual field IDs

## Marketing Preferences Mapping

The handler expects these checkbox values for marketing preferences:
- 'Newsletter' → maps to 'cf_person_dtr_news'
- 'Event' → maps to 'cf_person_dtr_events'
- 'Third party' → maps to 'cf_person_dtr_third_party'
- 'Webinar' → maps to 'cf_person_dtr_webinar'

## Topics of Interest Mapping

The handler expects these checkbox values for topics of interest:
- 'Business' → maps to 'cf_person_business'
- 'Diseases' → maps to 'cf_person_diseases'
- 'Drugs & Therapies' → maps to 'cf_person_drugs_therapies'
- 'Genomics' → maps to 'cf_person_genomics_3774'
- 'Research & Development' → maps to 'cf_person_research_development'
- 'Technology' → maps to 'cf_person_technology'
- 'Tools & Techniques' → maps to 'cf_person_tools_techniques'

## Checkbox Field Handling

Gravity Forms stores checkbox values with decimal notation:
- Field ID 12 with 3 options becomes: 12.1, 12.2, 12.3
- The handler automatically parses these sub-fields

## Features Included

✅ **Logging**: Comprehensive logging to `gf-member-registration-debug.log`
✅ **Test Mode**: Respects test mode settings from plugin options
✅ **User Creation**: Creates WordPress users with proper meta fields
✅ **Workbooks Sync**: Syncs data to Workbooks CRM
✅ **Duplicate Detection**: Handles existing users/contacts
✅ **AOI Mapping**: Maps Topics of Interest to Areas of Interest
✅ **Employer Org Linking**: Links users to organization records
✅ **Error Handling**: Comprehensive error handling with rollback

## Next Steps

1. **Update Field IDs**: Edit the `$field_map` array with your actual field IDs
2. **Test the Form**: Submit a test registration
3. **Check Logs**: Review the log files for any issues
4. **Verify Data**: Confirm data appears correctly in WordPress and Workbooks

The handler is now loaded and will automatically process Form ID 1 submissions.