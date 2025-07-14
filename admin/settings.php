<?php
if (!defined('ABSPATH')) exit;
?>
<form method="post" action="options.php">
    <?php settings_fields('workbooks_crm_options'); ?>
    <?php do_settings_sections('workbooks_crm_options'); ?>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="workbooks_api_url">API URL</label></th>
            <td>
                <input name="workbooks_api_url" id="workbooks_api_url" type="url" value="<?php echo esc_attr(get_option('workbooks_api_url')); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="workbooks_api_key">API Key</label></th>
            <td>
                <input name="workbooks_api_key" id="workbooks_api_key" type="text" value="<?php echo esc_attr(get_option('workbooks_api_key')); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="workbooks_logical_database_id">Logical Database</label></th>
            <td>
                <select name="workbooks_logical_database_id" id="workbooks_logical_database_id" disabled>
                    <option value="">Loading databases...</option>
                </select>
                <p class="description">Select your logical database (optional).</p>
            </td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>
<h2>Test Connection</h2>
<button id="workbooks_test_connection" class="button button-secondary">Test Connection</button>
<div id="workbooks_test_result" style="margin-top:10px;"></div>