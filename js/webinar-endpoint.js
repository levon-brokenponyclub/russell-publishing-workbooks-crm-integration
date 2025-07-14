jQuery(document).ready(function($) {
    // Submit webinar registration form
    $('#webinar-registration-form').on('submit', function(e) {
        e.preventDefault();

        var data = {
            action: 'workbooks_webinar_register',
            nonce: workbooksAjax.nonce,
            form_id: '23', // Add this line
            webinar_post_id: $('#webinar_post_id').val(),
            participant_email: $('#participant_email').val(),
            participant_question: $('#participant_question').val(),
            speaker_question: $('#speaker_question').val(),
            sponsor_optin: $('#sponsor_optin').is(':checked') ? '1' : '0'
        };


        if (!data.nonce) {
            $('#webinar-response').html('<span style="color:red;">Error: Security token missing.</span>');
            $.post(workbooksAjax.ajaxurl, {
                action: 'workbooks_log_client_error',
                message: 'Security token missing in webinar registration form'
            });
            return;
        }

        if (!data.webinar_post_id || !data.participant_email) {
            $('#webinar-response').html('<span style="color:red;">Error: Please select a webinar and provide an email.</span>');
            $.post(workbooksAjax.ajaxurl, {
                action: 'workbooks_log_client_error',
                message: 'Missing webinar ID or email in form submission',
                details: JSON.stringify({ webinar_post_id: data.webinar_post_id, participant_email: data.participant_email })
            });
            return;
        }

        $('#webinar-response').html('<span style="color:blue;">Processing...</span>');

        $.post(workbooksAjax.ajaxurl, data, function(response) {
            if (response.success) {
                $('#webinar-response').html('<span style="color:green;">' + response.data + '</span>');
                $('#webinar-registration-form')[0].reset();
                $('#acf-info').hide();
                $('#event-fetch-response').empty();
                $('#workbooks_event_ref').val('');
            } else {
                $('#webinar-response').html('<span style="color:red;">Error: ' + response.data + '</span>');
                $.post(workbooksAjax.ajaxurl, {
                    action: 'workbooks_log_client_error',
                    message: 'Server error: ' + response.data,
                    details: JSON.stringify(response)
                });
            }
        }).fail(function(xhr, status, error) {
            var errorMsg = 'AJAX request failed: ' + (xhr.status ? xhr.status + ' ' + xhr.statusText : error);
            $('#webinar-response').html('<span style="color:red;">' + errorMsg + '</span>');
            $.post(workbooksAjax.ajaxurl, {
                action: 'workbooks_log_client_error',
                message: errorMsg,
                details: JSON.stringify({ status: xhr.status, statusText: xhr.statusText, response: xhr.responseText })
            });
        });
    });

    // When webinar dropdown changes, fetch and show ACF fields
    $('#webinar_post_id').on('change', function() {
        var postId = $(this).val();
        if (!postId) {
            $('#acf-info').hide();
            return;
        }

        $.post(workbooksAjax.ajaxurl, {
            action: 'fetch_webinar_acf_data',
            nonce: workbooksAjax.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                $('#webinar_ref').text(response.data.workbooks_reference || 'Not set');
                $('#campaign_ref').text(response.data.campaign_reference || 'Not set');
                $('#acf-info').show();
            } else {
                $('#webinar-response').html('<span style="color:red;">Error fetching webinar data: ' + response.data + '</span>');
                $.post(workbooksAjax.ajaxurl, {
                    action: 'workbooks_log_client_error',
                    message: 'Error fetching webinar data: ' + response.data,
                    details: JSON.stringify(response)
                });
            }
        }).fail(function(xhr, status, error) {
            var errorMsg = 'AJAX request failed for webinar data: ' + (xhr.status ? xhr.status + ' ' + xhr.statusText : error);
            $('#webinar-response').html('<span style="color:red;">' + errorMsg + '</span>');
            $.post(workbooksAjax.ajaxurl, {
                action: 'workbooks_log_client_error',
                message: errorMsg,
                details: JSON.stringify({ status: xhr.status, statusText: xhr.statusText, response: xhr.responseText })
            });
        });
    });

    // Fetch Workbooks event details by event ID or ref
    $('#fetch-event-btn').on('click', function() {
        var eventRef = $('#workbooks_event_ref').val().trim();
        $('#event-fetch-response').css('color', '#444').text('Fetching event details...');

        if (!eventRef) {
            $('#event-fetch-response').css('color', 'red').text('Please enter an event ID or reference.');
            $.post(workbooksAjax.ajaxurl, {
                action: 'workbooks_log_client_error',
                message: 'Empty event ID or reference in fetch event'
            });
            return;
        }

        $.post(workbooksAjax.ajaxurl, {
            action: 'fetch_workbooks_event',
            nonce: workbooksAjax.nonce,
            event_ref: eventRef
        }, function(response) {
            if (response.success) {
                var event = response.data;
                var msg = 'Event Found: ' + event.name + ' (ID: ' + event.id + ')';
                if (event.start_date) msg += ', Starts: ' + event.start_date;
                if (event.end_date) msg += ', Ends: ' + event.end_date;
                $('#event-fetch-response').css('color', 'green').text(msg);
            } else {
                $('#event-fetch-response').css('color', 'red').text('Error: ' + response.data);
                $.post(workbooksAjax.ajaxurl, {
                    action: 'workbooks_log_client_error',
                    message: 'Fetch event error: ' + response.data,
                    details: JSON.stringify(response)
                });
            }
        }).fail(function(xhr, status, error) {
            var errorMsg = 'AJAX request failed for event fetch: ' + (xhr.status ? xhr.status + ' ' + xhr.statusText : error);
            $('#event-fetch-response').css('color', 'red').text(errorMsg);
            $.post(workbooksAjax.ajaxurl, {
                action: 'workbooks_log_client_error',
                message: errorMsg,
                details: JSON.stringify({ status: xhr.status, statusText: xhr.statusText, response: xhr.responseText })
            });
        });
    });
});