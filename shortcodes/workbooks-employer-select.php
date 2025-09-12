<?php
/**
 * Shortcode: [workbooks_employer_select]
 * Outputs a dynamic employer <select> field with Select2 and AJAX options.
 */
function workbooks_employer_select_shortcode($atts = array(), $content = null) {
    global $wpdb;
    $atts = shortcode_atts([
        'id' => 'workbooks-employer-select',
        'name' => 'employer',
        'class' => '',
        'placeholder' => 'Select your employer',
        'value' => '',
    ], $atts);
    $id = esc_attr($atts['id']);
    $name = esc_attr($atts['name']);
    $class = esc_attr(trim('workbooks-employers ' . $atts['class']));
    $placeholder = esc_attr($atts['placeholder']);
    $selected_value = esc_attr($atts['value']);

    // Query all employers from the DB
    $table = $wpdb->prefix . 'workbooks_employers';

    $employers = $wpdb->get_col("SELECT name FROM $table ORDER BY name ASC");
    $total_employers = is_array($employers) ? count($employers) : 0;

    ob_start();
    ?>
    <select id="<?php echo $id; ?>" name="<?php echo $name; ?>" class="<?php echo $class; ?>">
        <option value=""><?php echo $placeholder; ?></option>
        <?php foreach ($employers as $employer): ?>
            <option value="<?php echo esc_attr($employer); ?>" <?php selected($selected_value, $employer); ?>>
                <?php echo esc_html($employer); ?>
            </option>
        <?php endforeach; ?>
    </select>
        <!-- Removed console log script -->
    <?php
    return ob_get_clean();
}
add_shortcode('workbooks_employer_select', 'workbooks_employer_select_shortcode');
