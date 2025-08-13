jQuery(document).ready(function($) {
    console.log('🔧 Webinar endpoint script loaded successfully');
    console.log('🔧 Available AJAX object:', workbooks_ajax);
    
    // Hook into Ninja Forms submission using the correct document event
    $(document).on('nfFormSubmitResponse', function(e, formData) {
        console.log('🔄 Ninja Forms submission response detected:', e, formData);
        
        // Check if this is webinar form (ID 2)
        console.log('🔍 Checking form ID:', formData?.id, 'Type:', typeof formData?.id);
        if (formData && formData.id && formData.id == 2) {
            console.log('🎯 Webinar form submission detected (Form ID 2)');
            
            // Get the form response data
            var responseData = formData.response || {};
            var submissionData = responseData.data || {};
            var fieldsData = submissionData.fields || {};
            
            console.log('Submission data:', submissionData);
            console.log('Fields data:', fieldsData);
            
            // Extract field values by field key (these should match your form field keys)
            var data = {
                action: 'workbooks_webinar_register',
                nonce: workbooks_ajax.nonce,
                form_id: '2',
                webinar_post_id: extractFieldValue(fieldsData, 'post_id'),
                webinar_title: extractFieldValue(fieldsData, 'webinar_title'),
                participant_email: extractFieldValue(fieldsData, 'email_address'),
                speaker_question: extractFieldValue(fieldsData, 'speaker_question'),
                sponsor_optin: extractFieldValue(fieldsData, 'sponsor_optin') || '0'
            };

            console.log('Sending webinar registration AJAX:', data);

            // Send AJAX request to webinar handler
            $.post(workbooks_ajax.ajax_url, data, function(ajaxResponse) {
                console.log('Webinar AJAX response:', ajaxResponse);
                if (ajaxResponse.success) {
                    console.log('✅ Webinar registration successful:', ajaxResponse.data);
                } else {
                    console.error('❌ Webinar registration failed:', ajaxResponse.data);
                }
            }).fail(function(xhr, status, error) {
                console.error('❌ Webinar AJAX request failed:', xhr, status, error);
            });
        }
    });

    // Helper function to extract field values from Ninja Forms response
    function extractFieldValue(fieldsData, fieldKey) {
        // Look for field by key in the fields data
        for (var fieldId in fieldsData) {
            var field = fieldsData[fieldId];
            if (field && field.key === fieldKey) {
                return field.value || '';
            }
        }
        return '';
    }

    // Submit webinar registration form (legacy - keeping for compatibility)
    $('#webinar-registration-form').on('submit', function(e) {
        e.preventDefault();

        var data = {
            action: 'workbooks_webinar_register',
            nonce: workbooks_ajax.nonce,
            form_id: '23', // Add this line
            webinar_post_id: $('#webinar_post_id').val(),
            participant_email: $('#participant_email').val(),
            speaker_question: $('#speaker_question').val(),
            sponsor_optin: $('#sponsor_optin').is(':checked') ? '1' : '0'
        };


        if (!data.nonce) {
            $('#webinar-response').html('<span style="color:red;">Error: Security token missing.</span>');
            $.post(workbooks_ajax.ajax_url, {
                action: 'workbooks_log_client_error',
                message: 'Security token missing in webinar registration form'
            });
            return;
        }

        if (!data.webinar_post_id || !data.participant_email) {
            $('#webinar-response').html('<span style="color:red;">Error: Please select a webinar and provide an email.</span>');
            $.post(workbooks_ajax.ajax_url, {
                action: 'workbooks_log_client_error',
                message: 'Missing webinar ID or email in form submission',
                details: JSON.stringify({ webinar_post_id: data.webinar_post_id, participant_email: data.participant_email })
            });
            return;
        }

        $('#webinar-response').html('<span style="color:blue;">Processing...</span>');

        $.post(workbooks_ajax.ajax_url, data, function(response) {
            if (response.success) {
                $('#webinar-response').html('<span style="color:green;">' + response.data + '</span>');
                $('#webinar-registration-form')[0].reset();
                $('#acf-info').hide();
                $('#event-fetch-response').empty();
                $('#workbooks_event_ref').val('');
            } else {
                $('#webinar-response').html('<span style="color:red;">Error: ' + response.data + '</span>');
                $.post(workbooks_ajax.ajax_url, {
                    action: 'workbooks_log_client_error',
                    message: 'Server error: ' + response.data,
                    details: JSON.stringify(response)
                });
            }
        }).fail(function(xhr, status, error) {
            var errorMsg = 'AJAX request failed: ' + (xhr.status ? xhr.status + ' ' + xhr.statusText : error);
            $('#webinar-response').html('<span style="color:red;">' + errorMsg + '</span>');
            $.post(workbooks_ajax.ajax_url, {
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

        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_webinar_acf_data',
            nonce: workbooks_ajax.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                $('#webinar_ref').text(response.data.workbooks_reference || 'Not set');
                $('#campaign_ref').text(response.data.campaign_reference || 'Not set');
                $('#acf-info').show();
            } else {
                $('#webinar-response').html('<span style="color:red;">Error fetching webinar data: ' + response.data + '</span>');
                $.post(workbooks_ajax.ajax_url, {
                    action: 'workbooks_log_client_error',
                    message: 'Error fetching webinar data: ' + response.data,
                    details: JSON.stringify(response)
                });
            }
        }).fail(function(xhr, status, error) {
            var errorMsg = 'AJAX request failed for webinar data: ' + (xhr.status ? xhr.status + ' ' + xhr.statusText : error);
            $('#webinar-response').html('<span style="color:red;">' + errorMsg + '</span>');
            $.post(workbooks_ajax.ajax_url, {
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
            $.post(workbooks_ajax.ajax_url, {
                action: 'workbooks_log_client_error',
                message: 'Empty event ID or reference in fetch event'
            });
            return;
        }

        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_workbooks_event',
            nonce: workbooks_ajax.nonce,
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
                $.post(workbooks_ajax.ajax_url, {
                    action: 'workbooks_log_client_error',
                    message: 'Fetch event error: ' + response.data,
                    details: JSON.stringify(response)
                });
            }
        }).fail(function(xhr, status, error) {
            var errorMsg = 'AJAX request failed for event fetch: ' + (xhr.status ? xhr.status + ' ' + xhr.statusText : error);
            $('#event-fetch-response').css('color', 'red').text(errorMsg);
            $.post(workbooks_ajax.ajax_url, {
                action: 'workbooks_log_client_error',
                message: errorMsg,
                details: JSON.stringify({ status: xhr.status, statusText: xhr.statusText, response: xhr.responseText })
            });
        });
    });
    
    // Additional event listeners for debugging
    $(document).on('nfFormReady', function(e, layoutView) {
        console.log('🔧 Ninja Form ready:', layoutView);
    });
    
    $(document).on('submit', 'form', function(e) {
        console.log('🔧 Generic form submission detected:', this);
    });
    
    // Check for existing Ninja Forms
    if (typeof nfRadio !== 'undefined') {
        console.log('🔧 Ninja Forms radio available');
    } else {
        console.log('⚠️ Ninja Forms radio not available');
    }
});