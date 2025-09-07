jQuery(document).ready(function($) {
    // Only code needed: load and display gated content lists, Edit button links to post edit page
    function renderGatedContentTable(content) {
        if (!content || content.length === 0) {
            return '<p class="no-gated-content">No gated content found.</p>';
        }
        var html = '<table class="gated-content-table">';
        html += '<thead><tr>';
        html += '<th>Title</th>';
        html += '<th>Workbooks Ref</th>';
        html += '<th>Campaign Ref</th>';
        html += '<th>Form</th>';
        html += '<th class="actions">Actions</th>';
        html += '</tr></thead><tbody>';
        content.forEach(function(item) {
            html += '<tr>';
            html += '<td><strong>' + item.title + '</strong></td>';
            html += '<td>' + (item.workbooks_reference || '-') + '</td>';
            html += '<td>' + (item.campaign_reference ? 'CAMP-' + item.campaign_reference : '-') + '</td>';
            html += '<td>' + (item.ninja_form_title || 'Request Access Form') + '</td>';
            html += '<td class="actions" style="padding:0;">';
            html += '<a href="' + item.edit_url + '" class="button button-primary button-small" target="_blank">Edit</a>';
            html += '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        return html;
    }

    function loadGatedContentList(postType, containerId) {
        $.post(ajaxurl, {
            action: 'load_current_gated_articles',
            nonce: window.gatedContentAdmin && window.gatedContentAdmin.nonce ? window.gatedContentAdmin.nonce : '',
            post_type: postType
        }, function(response) {
            if (response.success) {
                $(containerId).html(renderGatedContentTable(response.data));
            } else {
                $(containerId).html('<p class="loading">Error loading gated content.</p>');
            }
        });
    }

    loadGatedContentList('articles', '#gated-articles-list');
    loadGatedContentList('whitepapers', '#gated-whitepapers-list');
    loadGatedContentList('news', '#gated-news-list');
    loadGatedContentList('events', '#gated-events-list');
});
