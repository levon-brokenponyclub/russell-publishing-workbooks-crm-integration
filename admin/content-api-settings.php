<?php
$options = get_option('dtr_workbooks_options', []);
$api_url       = $options['api_url']       ?? '';
$api_key       = $options['api_key']       ?? '';
$enabled_forms = $options['enabled_forms'] ?? [2,15,31];
$debug_mode    = !empty($options['debug_mode']);
$available_forms = [2 => 'Webinar Form', 15 => 'Registration Form', 31 => 'Lead Gen Form'];
?>
<div class="live/development-indicator" style="display:inline-block;margin-left:8px;padding:2px 6px;font-size:10px;font-weight:bold;color:#fff;background-color:#f57c00;border-radius:3px;vertical-align:middle;">Disconnected</div>
<form method="post" action="options.php" id="workbooks-settings-form">
    <?php settings_fields('dtr_workbooks_options'); ?>
    <h2 class="title" style="margin-top:0;"><?php _e('Workbooks API Configuration', 'dtr-workbooks'); ?></h2>
    <p class="description"><?php _e('Configure your Workbooks API connection settings.', 'dtr-workbooks'); ?></p>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="api_url"><?php _e('API URL','dtr-workbooks'); ?></label></th>
                <td>
                    <input type="url" id="api_url" name="dtr_workbooks_options[api_url]" value="<?php echo esc_attr($api_url); ?>" class="regular-text" placeholder="https://russellpublishing-live.workbooks.com/" required />
                    <p class="description"><?php _e('The URL of your Workbooks API endpoint','dtr-workbooks'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="api_key"><?php _e('API Key','dtr-workbooks'); ?></label></th>
                <td>
                    <input type="password" id="api_key" name="dtr_workbooks_options[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" required autocomplete="new-password" />
                    <p class="description"><?php _e('Your Workbooks API key','dtr-workbooks'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>

    <h2 class="title" style="margin-top:30px;"><?php _e('Form Configuration','dtr-workbooks'); ?></h2>
    <p class="description"><?php _e('Configure which forms should be processed by the integration.','dtr-workbooks'); ?></p>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <td>
                    <?php foreach ($available_forms as $fid => $fname): 
                        $test_mode = !empty($options['test_mode_forms'][$fid]) ? 1 : 0;
                    ?>
                        <label style="display:block;margin-bottom:12px;">
                            <span style="font-weight:bold;display:inline-block;width:180px;"><?php echo esc_html($fname); ?> (ID: <?php echo (int)$fid; ?>)</span>
                            <label class="toggle-switch" style="margin-left:10px;">
                                <input type="checkbox" id="toggle-<?php echo esc_attr($fid); ?>" <?php echo !$test_mode ? 'checked' : ''; ?> />
                                <span class="slider"></span>
                            </label>
                            <span id="toggle-label-<?php echo esc_attr($fid); ?>" style="margin-left:10px;font-weight:bold;vertical-align:middle;">Live</span>
                            <input type="hidden" name="dtr_workbooks_options[test_mode_forms][<?php echo esc_attr($fid); ?>]" id="test-mode-<?php echo esc_attr($fid); ?>" value="<?php echo $test_mode; ?>" />
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        </tbody>
    </table>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($available_forms as $fid => $fname): ?>
        var toggle<?php echo $fid; ?> = document.getElementById('toggle-<?php echo esc_attr($fid); ?>');
        var hidden<?php echo $fid; ?> = document.getElementById('test-mode-<?php echo esc_attr($fid); ?>');
        var label<?php echo $fid; ?> = document.getElementById('toggle-label-<?php echo esc_attr($fid); ?>');
        function updateToggle<?php echo $fid; ?>() {
            if (toggle<?php echo $fid; ?>.checked) {
                hidden<?php echo $fid; ?>.value = '0';
                label<?php echo $fid; ?>.textContent = 'Live';
                label<?php echo $fid; ?>.style.color = 'green';
            } else {
                hidden<?php echo $fid; ?>.value = '1';
                label<?php echo $fid; ?>.textContent = 'Test Mode';
                label<?php echo $fid; ?>.style.color = '#b71c1c';
            }
        }
        toggle<?php echo $fid; ?>.addEventListener('change', updateToggle<?php echo $fid; ?>);
        updateToggle<?php echo $fid; ?>();
        <?php endforeach; ?>

        // Ensure all hidden test mode fields are updated before form submit
        var form = document.getElementById('workbooks-settings-form');
        if (form) {
            form.addEventListener('submit', function() {
                <?php foreach ($available_forms as $fid => $fname): ?>
                updateToggle<?php echo $fid; ?>();
                <?php endforeach; ?>
            });
        }
    });
    </script>

    <h2 class="title" style="margin-top:30px;"><?php _e('Debug & Logging','dtr-workbooks'); ?></h2>
    <p class="description"><?php _e('Configure debug and logging settings.','dtr-workbooks'); ?></p>

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Debug Mode','dtr-workbooks'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="dtr_workbooks_options[debug_mode]" value="1" <?php checked($debug_mode); ?> />
                        <?php _e('Enable debug mode','dtr-workbooks'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Console Log Mode','dtr-workbooks'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="dtr_workbooks_options[console_log_mode]" value="1" <?php checked(!empty($options['console_log_mode'])); ?> />
                        <?php _e('Enable all plugin console logging','dtr-workbooks'); ?>
                    </label>
                    <p class="description"><?php _e('Turn this off to suppress all plugin console logs in the browser.','dtr-workbooks'); ?></p>
                    <div style="margin-top:8px;font-weight:bold;">
                        <?php
                        $console_log_on = !empty($options['console_log_mode']);
                        echo '<span style="color:' . ($console_log_on ? '#2e7d32' : '#b71c1c') . ';">Custom Console Log: ' . ($console_log_on ? 'On' : 'Off') . '</span>';
                        ?>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="submit-wrapper" style="display:flex;align-items:center;gap:10px;margin-top:20px;">
        <?php submit_button(__('Save Settings', 'dtr-workbooks'), 'primary', 'submit', false); ?>
        <button type="button" id="test-connection" class="button button-secondary"><?php _e('Test Connection', 'dtr-workbooks'); ?></button>
        <div id="connection-result" style="margin-left:10px;display:inline-flex;align-items:center;min-height:30px;">
            <?php
            $last_test = get_option('dtr_workbooks_last_connection_test');
            if ($last_test) {
                echo '<span class="description" style="color:#666;">'.sprintf(__('Last tested: %s ago','dtr-workbooks'), human_time_diff($last_test, current_time('timestamp'))).'</span>';
            }
            ?>
        </div>
    </div>
</form>