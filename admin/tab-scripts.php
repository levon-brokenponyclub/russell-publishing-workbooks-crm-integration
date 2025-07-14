<?php if (!defined('ABSPATH')) exit; ?>
<script>
jQuery(document).ready(function($) {
    console.log('Tab scripts loaded');
    console.log('workbooks_ajax available:', typeof workbooks_ajax !== 'undefined');
    
    $('.nav-tab').click(function(e) {
        e.preventDefault();
        console.log('Tab clicked:', $(this).attr('id'));
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.workbooks-tab-content').hide();

        if ($(this).attr('id') === 'workbooks-settings-tab') {
            $('#workbooks-settings-content').show();
            console.log('Showing settings tab');
        } else if ($(this).attr('id') === 'workbooks-person-tab') {
            $('#workbooks-person-content').show();
            console.log('Showing person tab');
            fetchOrganisations();
        } else if ($(this).attr('id') === 'workbooks-webinar-tab') {
            $('#workbooks-webinar-content').show();
            console.log('Showing webinar tab');
        } else if ($(this).attr('id') === 'workbooks-membership-tab') {
            $('#workbooks-membership-content').show();
            console.log('Showing membership tab');
        } else if ($(this).attr('id') === 'workbooks-employers-tab') {
            $('#workbooks-employers-content').show();
            console.log('Showing employers tab');
        } else if ($(this).attr('id') === 'workbooks-ninja-users-tab') {
            $('#workbooks-ninja-users-content').show();
            console.log('Showing ninja users tab');
        } else if ($(this).attr('id') === 'workbooks-topics-tab') {
            $('#workbooks-topics-content').show();
            console.log('Showing topics tab');
        }
    });

    // Fetch organisations for Person Record tab
    function fetchOrganisations() {
        if (typeof workbooks_ajax === 'undefined') {
            console.error('workbooks_ajax not defined');
            return;
        }
        
        $('#employer-loading').show();
        $.getJSON(workbooks_ajax.plugin_url + 'employers.json', function(data) {
            var $select = $('#employer');
            $select.empty().append('<option value="">-- Select Employer --</option>');
            $('#employer-loading').hide();
            if (data && Array.isArray(data)) {
                $.each(data, function(index, org) {
                    $select.append('<option value="' + org.name + '">' + org.name + '</option>');
                });
                // Set saved value if available
                var savedEmployer = $('#employer').data('saved-value');
                if (savedEmployer) {
                    $select.val(savedEmployer);
                }
            } else {
                console.error('Invalid or empty employers.json data');
                fetchOrganisationsFromAjax();
            }
        }).fail(function() {
            console.error('Failed to load employers.json');
            fetchOrganisationsFromAjax();
        });
    }

    // Fallback function to fetch organisations via AJAX
    function fetchOrganisationsFromAjax() {
        if (typeof workbooks_ajax === 'undefined') {
            console.error('workbooks_ajax not defined');
            return;
        }
        
        $('#employer-loading').show();
        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_workbooks_organisations',
            nonce: workbooks_ajax.nonce,
        }, function(response) {
            var $select = $('#employer');
            $select.empty().append('<option value="">-- Select Employer --</option>');
            $('#employer-loading').hide();
            if (response.success && response.data.length) {
                $.each(response.data, function(index, org) {
                    $select.append('<option value="' + org.name + '">' + org.name + '</option>');
                });
                // Set saved value if available
                var savedEmployer = $('#employer').data('saved-value');
                if (savedEmployer) {
                    $select.val(savedEmployer);
                }
            } else {
                console.error('Error loading organisations:', response.data);
                $select.append('<option value="">No employers found</option>');
            }
        }).fail(function() {
            console.error('AJAX request failed for organisations');
            $('#employer-loading').hide();
            $('#employer').append('<option value="">Error loading employers</option>');
        });
    }

    // Handle Generate JSON button click
    $('#workbooks_generate_json').on('click', function() {
        if (typeof workbooks_ajax === 'undefined') {
            console.error('workbooks_ajax not defined');
            return;
        }
        
        var $button = $(this);
        var $status = $('#employers-sync-status');
        
        $button.prop('disabled', true);
        $status.html('<span style="color:#666;">Generating JSON...</span>');
        
        $.post(workbooks_ajax.ajax_url, {
            action: 'workbooks_generate_employers_json',
            nonce: workbooks_ajax.nonce
        }, function(response) {
            if (response.success) {
                $status.html('<span style="color:green;">' + response.data + '</span>');
            } else {
                $status.html('<span style="color:red;">Error: ' + response.data + '</span>');
            }
            $button.prop('disabled', false);
        }).fail(function() {
            $status.html('<span style="color:red;">Request failed. Please try again.</span>');
            $button.prop('disabled', false);
        });
    });

    // Fetch logical databases
    function fetchDatabases() {
        if (typeof workbooks_ajax === 'undefined') {
            console.error('workbooks_ajax not defined');
            return;
        }
        
        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_workbooks_databases',
            nonce: workbooks_ajax.nonce,
        }, function(response) {
            var $select = $('#workbooks_logical_database_id');
            $select.empty();
            if (response.success) {
                if (response.data.no_selection_required) {
                    $select.append('<option value="">No database selection required</option>');
                    $select.prop('disabled', true);
                } else if (response.data.length) {
                    $select.append('<option value="">-- Select a Logical Database --</option>');
                    $.each(response.data, function(index, db) {
                        $select.append('<option value="' + db.logical_database_id + '">' + db.name + '</option>');
                    });
                    $select.prop('disabled', false);
                    // Set saved value if available
                    var savedValue = $('#workbooks_logical_database_id').data('saved-value');
                    if (savedValue) {
                        $select.val(savedValue);
                    }
                } else {
                    $select.append('<option value="">No databases found</option>');
                    $select.prop('disabled', true);
                }
            } else {
                $select.append('<option value="">Error loading databases</option>');
                console.error('Error loading databases:', response.data);
                $select.prop('disabled', true);
            }
        });
    }
    
    // Fetch databases on page load
    fetchDatabases();
    
    // Fetch organisations when Person Record tab is active
    if ($('#workbooks-person-content').is(':visible')) {
        fetchOrganisations();
    }
    
    // Update form submission to use AJAX
    $('#workbooks_update_user_form').on('submit', function(e) {
        if (typeof workbooks_ajax === 'undefined') {
            console.error('workbooks_ajax not defined');
            return;
        }
        
        e.preventDefault();
        var formData = $(this).serialize();
        formData += '&action=workbooks_update_user&nonce=' + workbooks_ajax.nonce;
        
        $.post(workbooks_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                alert('Success: ' + response.data);
            } else {
                alert('Error: ' + response.data);
            }
        }).fail(function() {
            alert('Request failed. Please try again.');
        });
    });
    
    // Test connection button
    $('#workbooks_test_connection').on('click', function() {
        if (typeof workbooks_ajax === 'undefined') {
            console.error('workbooks_ajax not defined');
            return;
        }
        
        var $button = $(this);
        var $result = $('#workbooks_test_result');
        
        $button.prop('disabled', true);
        $result.html('<span style="color:#666;">Testing connection...</span>');
        
        $.post(workbooks_ajax.ajax_url, {
            action: 'workbooks_test_connection',
            nonce: workbooks_ajax.nonce
        }, function(response) {
            if (response.success) {
                $result.html('<span style="color:green;">Success: ' + response.data + '</span>');
            } else {
                $result.html('<span style="color:red;">Error: ' + response.data + '</span>');
            }
            $button.prop('disabled', false);
        }).fail(function() {
            $result.html('<span style="color:red;">Request failed. Please check your settings and try again.</span>');
            $button.prop('disabled', false);
        });
    });
});
</script>
