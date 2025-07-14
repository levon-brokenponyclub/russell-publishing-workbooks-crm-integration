<?php
if (!defined('ABSPATH')) exit;
?>
<h2>Employers Sync</h2>
<div class="employers-actions" style="margin-bottom:20px;">
    <button id="workbooks_sync_employers" class="button button-primary">Sync All Employers</button>
    <button id="workbooks_generate_json" class="button button-secondary">Generate JSON from Database</button>
    <button id="workbooks_load_employers" class="button">Load Employers List</button>
    <span id="employers-sync-status" style="margin-left:10px;"></span>
</div>
<div id="employers-sync-progress" style="margin-bottom:20px; display:none;">
    <progress id="employers-progress-bar" value="0" max="100" style="width:100%;"></progress>
    <p id="employers-progress-text">Starting sync...</p>
</div>
<div id="employers-search-container" style="margin-bottom:15px; display:none;">
    <input type="search" id="employer-search" placeholder="Search employers..." style="width:50%;">
    <button type="button" id="employer-search-btn" class="button">Search</button>
    <button type="button" id="employer-reset-btn" class="button">Reset</button>
    <p><span id="employer-count">0</span> employers found</p>
</div>
<div id="employers-table-container" style="display:none;">
    <table class="wp-list-table widefat fixed striped employers-table">
        <thead>
            <tr>
                <th scope="col" width="10%">ID</th>
                <th scope="col" width="60%">Name</th>
                <th scope="col" width="20%">Last Updated</th>
                <th scope="col" width="10%">Actions</th>
            </tr>
        </thead>
        <tbody id="employers-table-body">
            <tr>
                <td colspan="4">No employers loaded yet.</td>
            </tr>
        </tbody>
    </table>
</div>
<div id="employers-pagination" style="margin-top:15px; display:none;">
    <button id="load-more-employers" class="button">Load More</button>
</div>
<div style="margin-top:20px;">
    <h3>Last Sync Information</h3>
    <?php
    $last_sync = get_option('workbooks_employers_last_sync');
    if ($last_sync) {
        echo '<p><strong>Last Sync:</strong> ' . date('Y-m-d H:i:s', $last_sync['time']) . '</p>';
        echo '<p><strong>Employers Count:</strong> ' . intval($last_sync['count']) . '</p>';
    } else {
        echo '<p>No sync has been performed yet.</p>';
    }
    ?>
    <p><strong>Next Scheduled Sync:</strong> 
    <?php
    $next_sync = wp_next_scheduled('workbooks_daily_employer_sync');
    echo $next_sync ? date('Y-m-d H:i:s', $next_sync) : 'Not scheduled';
    ?>
    </p>
</div>