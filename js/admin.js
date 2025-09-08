jQuery(document).ready(function($) {
    // Test connection button click
    $('#workbooks_test_connection').on('click', function(e) {
        e.preventDefault();
        $('#workbooks_test_result').css('color', '').text('Testing connection...');

        $.post(workbooks_ajax.ajax_url, {
            action: 'workbooks_test_connection',
            nonce: workbooks_ajax.nonce,
        }, function(response) {
            if (response.success) {
                $('#workbooks_test_result').css('color', 'green').text(response.data);
            } else {
                $('#workbooks_test_result').css('color', 'red').text(response.data || 'Connection failed');
            }
        }).fail(function(xhr, status, error) {
            $('#workbooks_test_result').css('color', 'red').text('AJAX error: ' + error);
        });
    });

    // Handle the person record update form submission via AJAX
    $('#workbooks_update_user_form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var data = $form.serializeArray();

        // Add required AJAX action and nonce
        data.push({name: 'action', value: 'workbooks_update_user'});
        data.push({name: 'nonce', value: workbooks_ajax.nonce});

        // Disable submit button to prevent duplicate submissions
        $form.find('input[type=submit]').prop('disabled', true);

        $.post(workbooks_ajax.ajax_url, data, function(response) {
            if (response.success) {
                alert('Person record updated and user meta saved successfully.');
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        }).fail(function(xhr, status, error) {
            alert('AJAX error: ' + error);
        }).always(function() {
            $form.find('input[type=submit]').prop('disabled', false);
        });
    });
});
