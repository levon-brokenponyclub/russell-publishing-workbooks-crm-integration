<?php
if (!defined('ABSPATH')) exit;

// Get counts for each post type
$post_types = ['articles', 'publications', 'whitepapers'];
$content_stats = [];

foreach ($post_types as $post_type) {
    $total_posts = wp_count_posts($post_type);
    $total_published = isset($total_posts->publish) ? $total_posts->publish : 0;
    
    // Count gated posts (posts with restrict_post = 1, matching AJAX handler)
    $gated_posts = get_posts(array(
        'post_type' => $post_type,
        'meta_query' => array(
            array(
                'key' => 'restrict_post',
                'value' => '1',
                'compare' => '='
            )
        ),
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    $content_stats[$post_type] = array(
        'gated' => count($gated_posts),
        'total' => $total_published,
        'open' => $total_published - count($gated_posts)
    );
}
?>

<h2>Gated Content Management Overview</h2>
<p>Manage access control for your content. Configure which content requires user authentication and specific interests.</p>

<div class="gated-content-overview">
    <div class="stats-grid">
        <?php foreach ($post_types as $post_type): ?>
            <div class="stats-card">
                <h3><?php echo ucfirst($post_type); ?></h3>
                <div class="stats-numbers">
                    <div class="stat-item gated">
                        <span class="stat-number"><?php echo $content_stats[$post_type]['gated']; ?></span>
                        <span class="stat-label">Gated</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $content_stats[$post_type]['total']; ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-item open">
                        <span class="stat-number"><?php echo $content_stats[$post_type]['open']; ?></span>
                        <span class="stat-label">Open</span>
                    </div>
                </div>
                <div class="stats-actions">
                    <a href="#" class="button button-secondary manage-type-btn" data-type="<?php echo $post_type; ?>">
                        Manage <?php echo ucfirst($post_type); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Recent Activity: Current Gated Content Lists -->
    <div class="recent-activity" id="recent-gated-activity">
        <h3>Current Gated Articles</h3>
        <div class="gated-content-list-section">
            <div id="gated-articles-list"><p class="loading">Loading current gated articles...</p></div>
        </div>
    </div>

    <div class="recent-activity" id="recent-gated-activity">
        <h3>Current Gated Publications</h3>
        <div class="gated-content-list-section">
            <div id="gated-publications-list"><p class="loading">Loading current gated publications...</p></div>
        </div>
    </div>

    <div class="recent-activity" id="recent-gated-activity">
        <h3>Current Gated Whitepapers</h3>
        <div class="gated-content-list-section">
            <div id="gated-whitepapers-list"><p class="loading">Loading current gated whitepapers...</p></div>
        </div>
    </div>

    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <button type="button" class="button button-primary" id="view-all-gated">View All Gated Content</button>
            <button type="button" class="button button-secondary" id="access-settings">Global Access Settings</button>
            <button type="button" class="button button-secondary" id="export-settings">Export Gated Settings</button>
        </div>
    </div>

</div>

<style>
/* Gated Content Table Styles */
.gated-content-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background: #fff;
    font-size: 13px;
}
.gated-content-table th,
.gated-content-table td {
    text-align: left;
    padding: 10px 12px;
    border-bottom: 1px solid #e1e4e7;
    vertical-align: middle;
}
.gated-content-table th {
    background: #f8f9fa;
    color: #23282d;
    font-weight: 600;
    font-size: 14px;
    border-top: 1px solid #e1e4e7;
}
.gated-content-table td.actions {
    min-width: 120px;
    padding: 6px 0px !important;
}
.gated-content-table a.button-small {
    font-size: 12px;
    padding: 4px 10px;
    line-height: 1.4;
}
/* End Gated Content Table Styles */
/* Gated Content Overview Styles */
.gated-content-overview {
    margin: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stats-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s ease;
}

.stats-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.stats-card h3 {
    margin: 0 0 15px 0;
    color: #23282d;
    font-size: 18px;
    font-weight: 600;
    border-bottom: 1px solid #bcbcbcff;
    padding-bottom: 8px;
}

.stats-numbers {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-number {
    display: block;
    font-size: 22px;
    font-weight: 600;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-item .stat-number {
    color: #666;
}

.stat-item.gated .stat-number {
    color: #d63638;
}

.stat-item.open .stat-number {
    color: #00a32a;
}

.stat-label {
    display: block;
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.stats-actions {
    text-align: center;
}

.stats-actions .button {
    width: 100%;
    text-align: center;
}

.quick-actions {
    background: #f8f9fa;
    border: 1px solid #e1e4e7;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
}

.quick-actions h3 {
    margin: 0 0 15px 0;
    color: #23282d;
    font-size: 16px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons .button {
    flex: 1;
    min-width: 200px;
    text-align: center;
}

.recent-activity {
    background: #ffffff;
    border: 1px solid #ccd0d4;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 30px;
}

.recent-activity h3 {
    margin: 0 0 15px 0;
    color: #23282d;
    font-size: 16px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-content {
    flex: 1;
}

.activity-content strong {
    display: block;
    margin-bottom: 5px;
    color: #23282d;
}

.post-type-badge {
    display: inline-block;
    background: #0073aa;
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 500;
    margin-left: 10px;
}

.activity-meta {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.activity-meta span {
    margin-right: 15px;
}

.requirements {
    background: #f0f6fc;
    padding: 2px 6px;
    border-radius: 3px;
    border: 1px solid #0073aa;
    color: #0073aa;
    font-weight: 500;
}

.activity-actions {
    flex-shrink: 0;
}

.no-activity {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}

/* Content Type Tabs */
.content-type-tabs .nav-tab-wrapper {
    background: #f1f1f1;
    padding: 0;
    margin-bottom: 0;
}

.content-type-tabs .nav-tab {
    background: #e0e0e0;
    border: 1px solid #ccd0d4;
    margin-right: 5px;
    padding: 10px 20px;
    border-radius: 4px 4px 0 0;
    transition: all 0.2s ease;
}

.content-type-tabs .nav-tab.nav-tab-active,
.content-type-tabs .nav-tab:hover {
    background: #fff;
    border-bottom: 1px solid #fff;
    color: #0073aa;
}

.content-type-panel {
    display: none;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
}

.content-type-panel.active {
    display: block;
}

.gated-content-wrapper {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
}

.content-config {
    background: #f9f9f9;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: 20px;
}

.content-config h4 {
    margin-top: 0;
    color: #23282d;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.post-select {
    min-width: 300px;
}

.content-search {
    min-width: 300px;
}

.search-results {
    margin-top: 5px;
    font-size: 0.9em;
}

.no-results {
    color: #dc3232;
    font-weight: bold;
}

.gated-content-form .submit {
    border-top: 1px solid #ddd;
    padding-top: 15px;
    margin-top: 20px;
}

/* AOI/TOI Access Control Styling */
fieldset {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background: #f9f9f9;
}

fieldset legend {
    font-weight: 600;
    padding: 0 10px;
    color: #23282d;
}

.access-control-section {
    margin: 15px 0;
}

.access-control-section .description {
    margin-bottom: 10px;
    font-style: italic;
    color: #666;
}

.access-control-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.access-control-checkboxes label {
    display: flex;
    align-items: center;
    padding: 5px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    margin-bottom: 5px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.access-control-checkboxes label:hover {
    background: #f0f8ff;
    border-color: #0073aa;
}

.access-control-checkboxes input[type="checkbox"] {
    margin-right: 8px;
}

.access-control-checkboxes input[type="checkbox"]:checked + span {
    font-weight: 600;
    color: #0073aa;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Only code needed: load and display gated content lists, Edit button links to post edit page
    function renderGatedContentTable(content) {
        if (!content || content.length === 0) {
            return '<p class="no-gated-content">No gated content found.</p>';
        }
        var html = '<table class="gated-content-table">';
        html += '<thead><tr>';
        html += '<th>Title</th>';
        html += '<th>Reference</th>';
        html += '<th>Campaign Ref</th>';
        html += '<th class="actions">Actions</th>';
        html += '</tr></thead><tbody>';
        content.forEach(function(item) {
            html += '<tr>';
            html += '<td><strong>' + item.title + '</strong></td>';
            html += '<td>' + (item.workbooks_reference ? item.workbooks_reference : '-') + '</td>';
            html += '<td>' + (item.campaign_reference ? item.campaign_reference : '-') + '</td>';
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
            nonce: '<?php echo wp_create_nonce("gated_content_nonce"); ?>',
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
    loadGatedContentList('publications', '#gated-publications-list');
    loadGatedContentList('whitepapers', '#gated-whitepapers-list');
});
</script>
