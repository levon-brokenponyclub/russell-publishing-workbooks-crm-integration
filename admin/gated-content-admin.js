jQuery(document).ready(function($) {
    // Listen for click on article row (update selector as needed)
    $(document).on('click', '.gated-article-row', function() {
        var postId = $(this).data('post-id');
        var nonce = window.gatedContentNonce;
        $('#articles-content-config').hide();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'load_gated_content_settings',
                nonce: nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success && response.data) {
                    var data = response.data;
                    $('#articles-post-title').val(data.post_title || '');
                    $('#articles-gate-content').prop('checked', data.gate_content == '1');
                    $('#articles-preview-text').val(data.preview_text || '');
                    $('#articles-preview-image').val(data.preview_image || '');
                    $('#articles-preview-video').val(data.preview_video || '');
                    $('#articles-preview-gallery').val(data.preview_gallery || '');
                    $('#articles-preview-button-text').val(data.preview_button_text || '');
                    $('#articles-preview-button-url').val(data.preview_button_url || '');
                    $('#articles-preview-button-style').val(data.preview_button_style || 'primary');
                    $('#articles-workbooks-ref').val(data.workbooks_reference || '');
                    $('#articles-campaign-ref').val(data.campaign_reference || '');
                    $('#articles-redirect-url').val(data.redirect_url || '');
                    $('#articles-ninja-form').val(data.ninja_form_id || '');
                    $('#articles-content-config').show();
                    $('#articles-gated-settings').show();
                } else {
                    alert('Failed to load settings.');
                }
            },
            error: function() {
                alert('AJAX error loading settings.');
            }
        });
    });
});
