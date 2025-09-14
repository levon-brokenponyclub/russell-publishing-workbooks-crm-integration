<?php
if (!defined('ABSPATH')) exit;
// Output the nonce and ajax_url for JS (if not already localized)
$ajax_nonce = wp_create_nonce('workbooks_nonce');
?>
<script>
window.workbooks_ajax = window.workbooks_ajax || {};
workbooks_ajax.nonce = <?php echo json_encode($ajax_nonce); ?>;
workbooks_ajax.ajax_url = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;
</script>
<h2>Daily fetching of Employers from Workbooks CRM.</h2>
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
    <input type="search" id="employer-search" placeholder="Search employers..." style="width:50%;height:36px;">
    <button type="button" id="employer-search-btn" class="button-primary">Search</button>
    <button type="button" id="employer-reset-btn" class="button-secondary">Reset</button>
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
    if ($next_sync) {
        echo date('Y-m-d H:i:s', $next_sync);
    } else {
        echo 'Not scheduled';
    }
    ?>
    </p>
    <div>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="<?php echo WORKBOOKS_TOGGLE_CRON_ACTION; ?>">
            <input type="hidden" name="toggle_cron" value="1">
            <button type="submit" class="button button-secondary">
                <?php echo wp_next_scheduled('workbooks_daily_employer_sync') ? 'Disable Cron' : 'Enable Cron'; ?>
            </button>
        </form>
    </div>
    <?php
    if (isset($_POST['toggle_cron'])) {
        if (wp_next_scheduled('workbooks_daily_employer_sync')) {
            wp_clear_scheduled_hook('workbooks_daily_employer_sync');
        } else {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'workbooks_daily_employer_sync');
        }
        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    ?>
</div>
<?php
add_action('wp_ajax_load_employers', 'workbooks_load_employers');
function workbooks_load_employers() {
    global $wpdb;

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'workbooks_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    // Pagination parameters
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Query employers
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, name, last_updated FROM wp_workbooks_employers ORDER BY last_updated DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );

    if (!$results) {
        wp_send_json_error(['message' => 'No employers found']);
    }

    // Format response
    $employers = array_map(function ($row) {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'last_updated' => $row->last_updated,
        ];
    }, $results);

    wp_send_json_success(['employers' => $employers]);
}

add_action('wp_ajax_fetch_workbooks_organisations_batch', 'workbooks_fetch_organisations_batch');
function workbooks_fetch_organisations_batch() {
    global $wpdb;

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'workbooks_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    // Pagination parameters
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 100;

    // Query organisations
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, name, last_updated FROM wp_workbooks_employers ORDER BY last_updated DESC LIMIT %d OFFSET %d",
            $batch_size,
            $start
        )
    );

    if (!$results) {
        wp_send_json_error(['message' => 'No organisations found']);
    }

    // Format response
    $organisations = array_map(function ($row) {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'last_updated' => $row->last_updated,
        ];
    }, $results);

    $has_more = count($organisations) === $batch_size;

    wp_send_json_success([
        'organisations' => $organisations,
        'has_more' => $has_more,
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM wp_workbooks_employers")
    ]);
}