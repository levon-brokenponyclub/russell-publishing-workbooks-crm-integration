jQuery(document).ready(function($) {
    // Hook before form submission
    $(document).on('nfFormSubmitResponse', function(e, data) {
        if (!data || !data.data || !data.data.form_id) return;

        var formId = data.data.form_id;

        if (formId !== 2) return; // Only for Ninja Form 2

        var $form = data.data.$form;
        var $statusArea = $('#wb-connection-status');

        if ($statusArea.length === 0) {
            $statusArea = $('<div id="wb-connection-status" style="margin-bottom: 10px; font-weight: bold;"></div>');
            $form.prepend($statusArea);
        }

        $statusArea.css('color', 'blue').text('Checking Workbooks connection...');

        $.post(ninja_forms_ajax_object.ajaxurl, { action: 'sspg_test_workbooks_connection' })
        .done(function(res) {
            if (res.success) {
                $statusArea.css('color', 'green').text('Workbooks connection successful. Submitting data...');
                // Let the form submission proceed normally
            } else {
                $statusArea.css('color', 'red').text('Workbooks connection failed: ' + (res.data.message || 'Unknown error'));
                // Prevent submission by showing error and cancel event
                e.preventDefault();
                $form.find('.nf-error').remove();
                $form.prepend('<div class="nf-error" style="color:red; margin-bottom:10px;">Connection failed. Please try again later.</div>');
            }
        })
        .fail(function() {
            $statusArea.css('color', 'red').text('Workbooks connection AJAX failed.');
        });
    });
});
