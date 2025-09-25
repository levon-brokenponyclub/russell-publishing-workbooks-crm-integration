<?php
/**
 * Member Registration Test Admin Page
 * Provides tools for testing member registration without Workbooks sync
 * and manual sync options for existing users
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap plugin-admin-content">
    <h1><?php echo esc_html__('Member Registration Test', 'dtr-workbooks'); ?></h1>
    
    <div class="dtr-admin-tabs">
        <h2 class="nav-tab-wrapper">
            <a href="#test-registration" class="nav-tab nav-tab-active"><?php _e('Test Registration', 'dtr-workbooks'); ?></a>
            <a href="#sync-users" class="nav-tab"><?php _e('Sync Existing Users', 'dtr-workbooks'); ?></a>
        </h2>

        <!-- Test Registration Tab -->
        <div id="test-registration" class="tab-content">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Simulate Member Registration', 'dtr-workbooks'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php _e('Create test users in WordPress without syncing to Workbooks. Perfect for testing form processing and data storage.', 'dtr-workbooks'); ?></p>
                    
                    <form id="test-registration-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="test-title"><?php _e('Title', 'dtr-workbooks'); ?></label></th>
                                <td>
                                    <select id="test-title" name="title">
                                        <option value="Mr.">Mr.</option>
                                        <option value="Mrs.">Mrs.</option>
                                        <option value="Ms.">Ms.</option>
                                        <option value="Dr.">Dr.</option>
                                        <option value="Prof.">Prof.</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="test-first-name"><?php _e('First Name', 'dtr-workbooks'); ?></label></th>
                                <td><input type="text" id="test-first-name" name="firstName" value="Test" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="test-last-name"><?php _e('Last Name', 'dtr-workbooks'); ?></label></th>
                                <td><input type="text" id="test-last-name" name="lastName" value="User" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="test-email"><?php _e('Email', 'dtr-workbooks'); ?></label></th>
                                <td>
                                    <input type="email" id="test-email" name="email" value="" class="regular-text" placeholder="<?php echo esc_attr('testuser' . time() . '@example.com'); ?>" />
                                    <p class="description"><?php _e('Leave blank to auto-generate unique email', 'dtr-workbooks'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="test-employer"><?php _e('Employer', 'dtr-workbooks'); ?></label></th>
                                <td><input type="text" id="test-employer" name="employer" value="Test Company Ltd" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="test-job-title"><?php _e('Job Title', 'dtr-workbooks'); ?></label></th>
                                <td><input type="text" id="test-job-title" name="jobTitle" value="Test Manager" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="test-country"><?php _e('Country', 'dtr-workbooks'); ?></label></th>
                                <td>
                                    <select id="test-country" name="country">
                                        <option value="United Kingdom">United Kingdom</option>
                                        <option value="United States">United States</option>
                                        <option value="Canada">Canada</option>
                                        <option value="Australia">Australia</option>
                                        <option value="Germany">Germany</option>
                                        <option value="France">France</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary" id="create-test-user">
                                <?php _e('Create Test User (WP Only)', 'dtr-workbooks'); ?>
                            </button>
                        </p>
                    </form>
                    
                    <div id="test-results" style="display: none;">
                        <h3><?php _e('Test Results', 'dtr-workbooks'); ?></h3>
                        <div id="test-output"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Users Tab -->
        <div id="sync-users" class="tab-content" style="display: none;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Search & Sync Users', 'dtr-workbooks'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php _e('Find users created via forms and manually sync them to Workbooks CRM.', 'dtr-workbooks'); ?></p>
                    
                    <div class="search-form">
                        <input type="text" id="user-search" placeholder="<?php esc_attr_e('Search users by name or email...', 'dtr-workbooks'); ?>" class="regular-text" />
                        <button type="button" id="search-users" class="button"><?php _e('Search', 'dtr-workbooks'); ?></button>
                        <button type="button" id="load-all-users" class="button"><?php _e('Load All Unsynced Users', 'dtr-workbooks'); ?></button>
                    </div>
                    
                    <div id="user-results" style="margin-top: 20px;">
                        <p class="description"><?php _e('Use the search above to find users, or click "Load All Unsynced Users" to see users that haven\'t been synced to Workbooks.', 'dtr-workbooks'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dtr-admin-tabs .nav-tab-wrapper {
    border-bottom: 1px solid #c3c4c7;
    margin-bottom: 20px;
}

.dtr-admin-tabs .tab-content {
    display: none;
}

.dtr-admin-tabs .tab-content.active {
    display: block;
}

.user-item {
    border: 1px solid #c3c4c7;
    padding: 15px;
    margin: 10px 0;
    background: #fff;
    border-radius: 4px;
}

.user-item h4 {
    margin: 0 0 10px 0;
    color: #1d2327;
}

.user-meta {
    margin: 5px 0;
    color: #646970;
    font-size: 13px;
}

.sync-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.sync-status.synced {
    background: #d1e7dd;
    color: #0f5132;
}

.sync-status.not-synced {
    background: #f8d7da;
    color: #721c24;
}

.user-overview {
    margin: 5px 0 15px 0;
    color: #646970;
    font-size: 13px;
}

.user-data-mapping {
    margin: 15px 0;
}

.user-data-mapping h5 {
    margin: 0 0 10px 0;
    color: #1d2327;
    font-size: 14px;
}

.workbooks-mapping-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
    font-size: 13px;
}

.workbooks-mapping-table th,
.workbooks-mapping-table td {
    border: 1px solid #c3c4c7;
    padding: 8px 12px;
    text-align: left;
}

.workbooks-mapping-table th {
    background: #f6f7f7;
    font-weight: 600;
    color: #1d2327;
}

.workbooks-mapping-table td:first-child {
    font-weight: 500;
    color: #2271b1;
    width: 25%;
}

.workbooks-mapping-table td:nth-child(2) {
    width: 25%;
    color: #646970;
    font-family: monospace;
    font-size: 12px;
}

.workbooks-mapping-table td:nth-child(3) {
    width: 25%;
}

.workbooks-mapping-table td:nth-child(4) {
    width: 25%;
    color: #646970;
    font-style: italic;
}

.sync-actions {
    margin: 15px 0 0 0;
}

.test-success {
    background: #dff0d8;
    border: 1px solid #d6e9c6;
    color: #3c763d;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}

.test-error {
    background: #f2dede;
    border: 1px solid #ebccd1;
    color: #a94442;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo wp_create_nonce('dtr_member_test_nonce'); ?>';
    const dtr_ajax_nonce = '<?php echo wp_create_nonce('dtr_test_nonce'); ?>';
    
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').removeClass('active').hide();
        $(target).addClass('active').show();
    });
    
    // Test registration form
    $('#test-registration-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'dtr_test_member_registration');
        formData.append('nonce', nonce);
        
        // Auto-generate email if empty
        if (!formData.get('email')) {
            formData.set('email', 'testuser' + Date.now() + '@example.com');
        }
        
        $('#create-test-user').prop('disabled', true).text('Creating...');
        $('#test-results').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#test-output').html(`
                        <div class="test-success">
                            <h4>✓ Test User Created Successfully</h4>
                            <p><strong>User ID:</strong> ${response.data.user_id}</p>
                            <p><strong>Email:</strong> ${response.data.email}</p>
                            <p><strong>Password:</strong> ${response.data.password}</p>
                            <p><strong>Status:</strong> Created in WordPress only (not synced to Workbooks)</p>
                            <p><em>You can now use the "Sync Users" tab to manually sync this user to Workbooks if needed.</em></p>
                        </div>
                    `);
                } else {
                    $('#test-output').html(`
                        <div class="test-error">
                            <h4>✗ Test Registration Failed</h4>
                            <p>${response.data.message}</p>
                        </div>
                    `);
                }
                $('#test-results').show();
            },
            error: function() {
                $('#test-output').html(`
                    <div class="test-error">
                        <h4>✗ Connection Error</h4>
                        <p>Failed to communicate with server.</p>
                    </div>
                `);
                $('#test-results').show();
            },
            complete: function() {
                $('#create-test-user').prop('disabled', false).text('Create Test User (WP Only)');
            }
        });
    });
    
    // User search
    function searchUsers(search = '') {
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'dtr_search_test_users',
                nonce: nonce,
                search: search
            },
            success: function(response) {
                if (response.success) {
                    displayUsers(response.data);
                } else {
                    $('#user-results').html(`<p class="test-error">${response.data.message}</p>`);
                }
            },
            error: function() {
                $('#user-results').html('<p class="test-error">Search failed.</p>');
            }
        });
    }
    
    function displayUsers(users) {
        if (users.length === 0) {
            $('#user-results').html('<p>No users found matching your criteria.</p>');
            return;
        }
        
        let html = '<h3>Found ' + users.length + ' user(s):</h3>';
        
        users.forEach(function(user) {
            const syncStatus = user.synced ? 
                '<span class="sync-status synced">Synced</span>' : 
                '<span class="sync-status not-synced">Not Synced</span>';
            
            const syncButton = user.synced ? 
                '<button class="button" disabled>Already Synced</button>' :
                `<button class="button button-primary sync-user" data-user-id="${user.ID}">Sync to Workbooks</button>`;
                
            const workbooksApiButton = user.synced && user.workbooks_id ?
                `<button class="button show-workbooks-api" data-workbooks-id="${user.workbooks_id}" data-user-id="${user.ID}">Show Workbooks API Fields for this User</button>` : '';
                
            const regenerateButton = user.synced && user.workbooks_id ?
                `<button class="button button-primary regenerate-employer-data" data-workbooks-id="${user.workbooks_id}" data-user-id="${user.ID}">Regenerate Data</button>` : '';
            
            html += `
                <div class="user-item">
                    <h4>${user.display_name} ${syncStatus}</h4>
                    <div class="user-overview">
                        <strong>Email:</strong> ${user.email} | 
                        <strong>User ID:</strong> ${user.ID} | 
                        <strong>Registered:</strong> ${user.registered}
                        ${user.workbooks_id ? ` | <strong>Workbooks ID:</strong> ${user.workbooks_id}` : ''}
                        ${user.workbooks_ref ? ` | <strong>Workbooks Ref:</strong> ${user.workbooks_ref}` : ''}
                    </div>
                    
                    <div class="user-data-mapping">
                        <h5>Workbooks Field Mapping:</h5>
                        <table class="workbooks-mapping-table">
                            <thead>
                                <tr>
                                    <th>Field Names</th>
                                    <th>Workbooks Field ID</th>
                                    <th>WP Meta</th>
                                    <th>Workbooks (once synced)</th>
                                </tr>
                            </thead>
                            <tbody id="user-data-${user.ID}">
                                <tr><td colspan="4"><em>Loading user data...</em></td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <p class="sync-actions">${syncButton} ${workbooksApiButton} ${regenerateButton}</p>
                </div>
            `;
        });
        
        $('#user-results').html(html);
        
        // Load detailed meta data for each user
        users.forEach(function(user) {
            loadUserMeta(user.ID);
        });
    }
    
    $('#search-users').on('click', function() {
        const search = $('#user-search').val();
        searchUsers(search);
    });
    
    $('#load-all-users').on('click', function() {
        searchUsers();
    });
    
    function loadUserMeta(userId) {
        console.log('LoadUserMeta called for user:', userId);
        console.log('Ajax URL:', ajaxurl);
        console.log('Nonce:', dtr_ajax_nonce);
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dtr_load_user_meta',
                user_id: userId,
                nonce: dtr_ajax_nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                console.log('Response success:', response.success);
                console.log('Response data:', response.data);
                if (response.success) {
                    console.log('Meta fields:', response.data?.meta_fields);
                    console.log('Meta fields length:', response.data?.meta_fields?.length);
                    if (response.data.meta_fields) {
                        let metaHtml = '';
                        response.data.meta_fields.forEach(function(field, index) {
                        // Show sync status for all rows to see all data
                        let workbooksColumn = '';
                        if (field.is_synced) {
                            // For synced users, show a placeholder indicating data is in Workbooks
                            workbooksColumn = field.wp_value ? 
                                '<em style="color: #00a32a;">✓ In Workbooks</em>' : 
                                '<em style="color: #646970;">—</em>';
                        } else {
                            workbooksColumn = '<em style="color: #d63638;">Not synced</em>';
                        }
                        
                        metaHtml += `
                            <tr>
                                <td><strong>${field.workbooks_field}</strong></td>
                                <td><code>${field.workbooks_field_id || field.workbooks_field}</code></td>
                                <td>${field.wp_value || '<em>Not set</em>'}</td>
                                <td>${workbooksColumn}</td>
                            </tr>
                        `;
                        });
                        $(`#user-data-${userId}`).html(metaHtml);
                    } else {
                        console.log('No meta_fields in response data');
                        $(`#user-data-${userId}`).html('<tr><td colspan="4"><em>No meta fields data</em></td></tr>');
                    }
                } else {
                    console.log('AJAX Error:', response.data);
                    $(`#user-data-${userId}`).html('<tr><td colspan="4"><em>Error loading user data</em></td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr, status, error);
                $(`#user-data-${userId}`).html('<tr><td colspan="4"><em>Error loading user data</em></td></tr>');
            }
        });
    }
    
    // Enter key search
    $('#user-search').on('keypress', function(e) {
        if (e.which === 13) {
            $('#search-users').click();
        }
    });
    
    // Sync user to Workbooks
    $(document).on('click', '.sync-user', function() {
        const button = $(this);
        const userId = button.data('user-id');
        
        button.prop('disabled', true).text('Syncing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dtr_sync_user_to_workbooks',
                nonce: nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    button.closest('.user-item').find('.sync-status')
                        .removeClass('not-synced').addClass('synced').text('Synced');
                    button.replaceWith('<button class="button" disabled>Already Synced</button>');
                    
                    // Add Workbooks info
                    const metaDiv = button.closest('.user-item').find('.user-meta');
                    if (response.data.workbooks_id) {
                        metaDiv.append(`<br><strong>Workbooks ID:</strong> ${response.data.workbooks_id}`);
                    }
                    if (response.data.workbooks_ref) {
                        metaDiv.append(`<br><strong>Workbooks Ref:</strong> ${response.data.workbooks_ref}`);
                    }
                    
                    alert('✓ User successfully synced to Workbooks!');
                } else {
                    alert('✗ Sync failed: ' + response.data.message);
                    button.prop('disabled', false).text('Sync to Workbooks');
                }
            },
            error: function() {
                alert('✗ Connection error during sync.');
                button.prop('disabled', false).text('Sync to Workbooks');
            }
        });
    });
    
    // Show Workbooks API Fields
    $(document).on('click', '.show-workbooks-api', function() {
        const button = $(this);
        const workbooksId = button.data('workbooks-id');
        const userId = button.data('user-id');
        
        console.log('Show Workbooks API Fields clicked');
        console.log('Workbooks ID:', workbooksId);  
        console.log('User ID:', userId);
        
        // Check if table already exists
        const existingTable = button.closest('.user-item').find('.workbooks-api-table');
        if (existingTable.length > 0) {
            existingTable.toggle();
            button.text(existingTable.is(':visible') ? 'Hide Workbooks API Fields' : 'Show Workbooks API Fields for this User');
            return;
        }
        
        button.prop('disabled', true).text('Loading...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dtr_get_workbooks_fields',
                nonce: dtr_ajax_nonce,
                workbooks_id: workbooksId,
                user_id: userId
            },
            success: function(response) {
                console.log('Workbooks API Fields Response:', response);
                console.log('Response success:', response.success);
                console.log('Response data:', response.data);
                if (response.success && response.data.fields) {
                    let tableHtml = `
                        <div class="workbooks-api-table" style="margin-top: 15px;">
                            <h5>All Workbooks API Fields for this User:</h5>
                            <table class="widefat striped" style="margin-top:8px; max-width:900px;">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    Object.entries(response.data.fields).forEach(([field, value]) => {
                        const displayValue = value !== null && value !== '' ? value : '';
                        tableHtml += `<tr><td>${field}</td><td>${displayValue}</td></tr>`;
                    });
                    
                    tableHtml += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    button.closest('.user-item').find('.sync-actions').after(tableHtml);
                    button.text('Hide Workbooks API Fields').prop('disabled', false);
                } else {
                    alert('✗ Failed to load Workbooks fields: ' + (response.data?.message || 'Unknown error'));
                    button.prop('disabled', false).text('Show Workbooks API Fields for this User');
                }
            },
            error: function() {
                alert('✗ Connection error while loading Workbooks fields.');
                button.prop('disabled', false).text('Show Workbooks API Fields for this User');
            }
        });
    });
    
    // Regenerate Employer Data
    $(document).on('click', '.regenerate-employer-data', function() {
        const button = $(this);
        const workbooksId = button.data('workbooks-id');
        const userId = button.data('user-id');
        
        if (!confirm('This will regenerate the employer data for this user record in Workbooks. This will:\n\n• Fix the employer_link field\n• Sync employer_name with cf_person_claimed_employer\n• Update the existing record (no new record created)\n\nContinue?')) {
            return;
        }
        
        button.prop('disabled', true).text('Regenerating...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dtr_regenerate_employer_data',
                nonce: dtr_ajax_nonce,
                workbooks_id: workbooksId,
                user_id: userId
            },
            success: function(response) {
                console.log('Regenerate Response:', response);
                if (response.success) {
                    alert('✅ Employer data regenerated successfully!\n\n' + response.data.message);
                    button.text('Regenerate Data').prop('disabled', false);
                    
                    // Refresh the Workbooks API fields table if it's visible
                    const apiTable = button.closest('.user-item').find('.workbooks-api-table');
                    if (apiTable.length > 0 && apiTable.is(':visible')) {
                        button.closest('.user-item').find('.show-workbooks-api').click().click(); // Hide and show to refresh
                    }
                } else {
                    alert('✗ Failed to regenerate employer data: ' + (response.data || 'Unknown error'));
                    button.text('Regenerate Data').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.log('Regenerate Error:', xhr, status, error);
                alert('✗ Connection error while regenerating employer data.');
                button.text('Regenerate Data').prop('disabled', false);
            }
        });
    });
});
</script>