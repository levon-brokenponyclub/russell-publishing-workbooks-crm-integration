<?php
if (!defined('ABSPATH')) exit;
if (file_exists(WORKBOOKS_NF_PATH . 'includes/helper-functions.php')) {
    require_once WORKBOOKS_NF_PATH . 'includes/helper-functions.php';
}
$toi_options = function_exists('dtr_get_all_toi_options') ? dtr_get_all_toi_options() : [];
$aoi_field_names = function_exists('dtr_get_aoi_field_names') ? dtr_get_aoi_field_names() : [];
?>
<h2>Topics of Interest (TOI) and Areas of Interest (AOI) Mapping</h2>
<p>This table shows all available Topics of Interest and their corresponding Areas of Interest mappings. When a user selects a TOI during registration, the corresponding AOI fields will be set to 1 in Workbooks.</p>
<?php
if (empty($toi_options)) {
    echo '<p>No TOI options available.</p>';
} else {
    echo '<table class="wp-list-table widefat fixed striped toi-mapping-table">';
    echo '<thead>
        <tr>
            <th style="width: 30%;">Topic of Interest (TOI)</th>
            <th style="width: 70%;">Mapped Areas of Interest (AOI)</th>
        </tr>
    </thead><tbody>';
    foreach ($toi_options as $toi_field => $toi_name) {
        $aoi_mapping = function_exists('dtr_map_toi_to_aoi') ? dtr_map_toi_to_aoi([$toi_field]) : [];
        $mapped_aois = [];
        foreach ($aoi_mapping as $aoi_field => $value) {
            if ($value == 1 && isset($aoi_field_names[$aoi_field])) {
                $mapped_aois[] = $aoi_field_names[$aoi_field];
            }
        }
        $aoi_count = count($mapped_aois);
        $toi_display_name = $toi_name . ' AOI (' . $aoi_count . ')';
        echo '<tr>';
        echo '<td><strong>' . esc_html($toi_display_name) . '</strong><br><span class="toi-field-name">' . esc_html($toi_field) . '</span></td>';
        echo '<td>';
        if (empty($mapped_aois)) {
            echo '<span class="no-mapping">No AOI mappings configured</span>';
        } else {
            echo '<div class="aoi-badges">';
            foreach ($mapped_aois as $aoi_name) {
                echo '<span class="aoi-badge">' . esc_html($aoi_name) . '</span>';
            }
            echo '</div>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<h3 style="margin-top: 30px;">Available AOI Fields</h3>';
    echo '<p>These are all the available Areas of Interest fields.</p>';