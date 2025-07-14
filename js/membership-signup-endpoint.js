jQuery(document).ready(function($) {
    $('#workbooks_membership_signup_form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var data = $form.serializeArray();
        data.push({name: 'action', value: 'workbooks_membership_signup'});
        // Optionally add a nonce for security

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                alert('Membership sign up successful!');
                $form[0].reset();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        }).fail(function(xhr, status, error) {
            alert('AJAX error: ' + error);
        });
    });
});
