<?php

/**
 * Plugin Name: WooCommerce Delivery Pick Day
 * Description: Adds a delivery date & time field to WooCommerce checkout with advanced options.
 * Version: 2.2
 * Author: CostasCh
 * Author URI: https://costasch.xyz
 * Text Domain: wc-delivery-pickday
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 8.2
 * Copyright: © 2025 CostasCh
 */

if (!defined('ABSPATH')) exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>WooCommerce Delivery Pick Day</strong> requires WooCommerce to be installed and activated.</p></div>';
    });
    return;
}

// 1. Add delivery pick day & time fields
function wc_delivery_pick_day_field($checkout)
{
    $options = get_option('wc_delivery_pick_day_options');
    $field_title = $options['field_title'] ?? __('Choose Delivery Pick Day', 'wc-delivery-pickday');
    $field_label = $options['field_label'] ?? __('Choose Delivery Day', 'wc-delivery-pickday');
    $delivery_times = !empty($options['delivery_times']) ? array_map('trim', explode(',', $options['delivery_times'])) : ['10:00', '12:00', '14:00', '16:00'];
    $field_required = !empty($options['field_required']) ? 'required' : '';

    echo '<div id="delivery_pick_day_field"><h2>' . esc_html($field_title) . '</h2>';
    echo '<p class="form-row form-row-wide">
        <label for="delivery_pick_day">' . esc_html($field_label) . ($field_required ? ' <abbr class="required" title="required">*</abbr>' : '') . '</label>
        <input type="text" id="delivery_pick_day" name="delivery_pick_day" class="input-text" ' . $field_required . '>
    </p>';
    echo '<p class="form-row form-row-wide">
        <label for="delivery_pick_time">' . __('Choose Delivery Time', 'wc-delivery-pickday') . ($field_required ? ' <abbr class="required" title="required">*</abbr>' : '') . '</label>
        <select name="delivery_pick_time" id="delivery_pick_time" ' . $field_required . '>
            <option value="">' . __('Select time', 'wc-delivery-pickday') . '</option>';
    foreach ($delivery_times as $time) {
        echo '<option value="' . esc_attr($time) . '">' . esc_html($time) . '</option>';
    }
    echo '</select></p></div>';
}
add_action('woocommerce_after_order_notes', 'wc_delivery_pick_day_field');

function wc_delivery_pick_location_field($checkout)
{
    $options = get_option('wc_delivery_pick_day_options');
    $accepted_locations = !empty($options['accepted_locations']) ? array_map('trim', explode("\n", $options['accepted_locations'])) : [];

    echo '<p class="form-row form-row-wide">
        <label for="delivery_location">' . __('Delivery Location', 'wc-delivery-pickday') . ' <abbr class="required" title="required">*</abbr></label>
        <select id="delivery_location" name="delivery_location" class="input-text" required>
            <option value="">' . __('Select a location', 'wc-delivery-pickday') . '</option>';
    foreach ($accepted_locations as $location) {
        echo '<option value="' . esc_attr($location) . '">' . esc_html($location) . '</option>';
    }
    echo '</select></p>';
}
add_action('woocommerce_after_order_notes', 'wc_delivery_pick_location_field');

// 2. Load datepicker and JS restrictions
function wc_delivery_pick_day_enqueue_scripts()
{
    if (!is_checkout()) return;
    wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
    wp_enqueue_script('flatpickr-locale-gr', 'https://npmcdn.com/flatpickr/dist/l10n/gr.js', ['flatpickr'], null, true);
    wp_enqueue_style('flatpickr-style', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');

    $options = get_option('wc_delivery_pick_day_options');
    $blackout = !empty($options['blackout_dates']) ? array_map('trim', explode(',', $options['blackout_dates'])) : [];
    $no_delivery_days = [];

    if (!empty($options['no_delivery_days'])) {
        $no_delivery_days = is_array($options['no_delivery_days']) ? $options['no_delivery_days'] : explode(',', $options['no_delivery_days']);
    }

    $disable_days_script = "function(date) { return [" . implode(',', array_map('intval', $no_delivery_days)) . "].includes(date.getDay()); },";
    wp_add_inline_script('flatpickr', 'document.addEventListener("DOMContentLoaded", function() {
        flatpickr("#delivery_pick_day", {
            dateFormat: "Y-m-d",
            minDate: "today",
            disable: [
                ' . $disable_days_script . '
                ' . json_encode($blackout) . '
            ],
            locale: "gr"
        });
    });');
}
add_action('wp_enqueue_scripts', 'wc_delivery_pick_day_enqueue_scripts');

// 3. Validation
add_action('woocommerce_checkout_process', function () {
    if (empty($_POST['delivery_pick_day']) || empty($_POST['delivery_pick_time'])) {
        wc_add_notice(__('Please choose both delivery date and time.', 'wc-delivery-pickday'), 'error');
    }

    $max_orders = 10;
    $chosen_date = sanitize_text_field($_POST['delivery_pick_day']);
    $args = [
        'post_type' => 'shop_order',
        'post_status' => ['wc-processing', 'wc-completed', 'wc-on-hold'],
        'meta_query' => [[
            'key' => '_delivery_pick_day',
            'value' => $chosen_date
        ]]
    ];
    $orders = get_posts($args);
    if (count($orders) >= $max_orders) {
        wc_add_notice(__('This day is fully booked. Please choose another.', 'wc-delivery-pickday'), 'error');
    }
});

add_action('woocommerce_checkout_process', function () {
    $options = get_option('wc_delivery_pick_day_options');
    $accepted_locations = !empty($options['accepted_locations']) ? array_map('trim', explode("\n", $options['accepted_locations'])) : [];

    if (empty($_POST['delivery_location']) || !in_array(sanitize_text_field($_POST['delivery_location']), $accepted_locations)) {
        wc_add_notice(__('The delivery location is not accepted. Please choose a valid location.', 'wc-delivery-pickday'), 'error');
    }
});

// 4. Save metadata
add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    if (!empty($_POST['delivery_pick_day'])) {
        update_post_meta($order_id, '_delivery_pick_day', sanitize_text_field($_POST['delivery_pick_day']));
    }
    if (!empty($_POST['delivery_pick_time'])) {
        update_post_meta($order_id, '_delivery_pick_time', sanitize_text_field($_POST['delivery_pick_time']));
    }
    if (!empty($_POST['delivery_location'])) {
        update_post_meta($order_id, '_delivery_location', sanitize_text_field($_POST['delivery_location']));
    }
});

// 5. Display in admin
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $day = get_post_meta($order->get_id(), '_delivery_pick_day', true);
    $time = get_post_meta($order->get_id(), '_delivery_pick_time', true);
    if ($day || $time) {
        echo '<p><strong>' . __('Delivery Day', 'wc-delivery-pickday') . ':</strong> ' . esc_html($day) . ' ' . esc_html($time) . '</p>';
    }

    $location = get_post_meta($order->get_id(), '_delivery_location', true);
    if ($location) {
        echo '<p><strong>' . __('Delivery Location', 'wc-delivery-pickday') . ':</strong> ' . esc_html($location) . '</p>';
    }
});

// 6. Email notification
add_filter('woocommerce_email_order_meta_fields', function ($fields, $sent_to_admin, $order) {
    $day = get_post_meta($order->get_id(), '_delivery_pick_day', true);
    $time = get_post_meta($order->get_id(), '_delivery_pick_time', true);
    if ($day || $time) {
        $fields['delivery_pick_day'] = [
            'label' => __('Delivery Date & Time', 'wc-delivery-pickday'),
            'value' => esc_html($day . ' ' . $time),
        ];
    }

    $location = get_post_meta($order->get_id(), '_delivery_location', true);
    if ($location) {
        $fields['delivery_location'] = [
            'label' => __('Delivery Location', 'wc-delivery-pickday'),
            'value' => esc_html($location),
        ];
    }

    return $fields;
}, 10, 3);

// 7. Admin settings
add_action('admin_menu', function () {
    add_options_page(
        __('Delivery Pick Day Settings', 'wc-delivery-pickday'),
        __('Delivery Pick Day', 'wc-delivery-pickday'),
        'manage_options',
        'wc-delivery-pickday-settings',
        'wc_delivery_pick_day_settings_page'
    );
});

function wc_delivery_pick_day_settings_page()
{
?>
    <div class="wrap">
        <h1><?php _e('Delivery Pick Day Settings', 'wc-delivery-pickday'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wc_delivery_pick_day_options_group');
            do_settings_sections('wc-delivery-pickday-settings');
            submit_button();
            ?>
        </form>
    </div>
<?php
}

add_action('admin_init', function () {
    register_setting('wc_delivery_pick_day_options_group', 'wc_delivery_pick_day_options');

    add_settings_section('wc_delivery_pick_day_main_section', '', null, 'wc-delivery-pickday-settings');

    add_settings_field('field_title', __('Field Title', 'wc-delivery-pickday'), function () {
        $options = get_option('wc_delivery_pick_day_options');
        echo '<input type="text" name="wc_delivery_pick_day_options[field_title]" value="' . esc_attr($options['field_title'] ?? '') . '" class="regular-text">';
    }, 'wc-delivery-pickday-settings', 'wc_delivery_pick_day_main_section');

    add_settings_field('field_label', __('Field Label', 'wc-delivery-pickday'), function () {
        $options = get_option('wc_delivery_pick_day_options');
        echo '<input type="text" name="wc_delivery_pick_day_options[field_label]" value="' . esc_attr($options['field_label'] ?? '') . '" class="regular-text">';
    }, 'wc-delivery-pickday-settings', 'wc_delivery_pick_day_main_section');

    add_settings_field('blackout_dates', __('Blackout Dates (comma separated)', 'wc-delivery-pickday'), function () {
        $options = get_option('wc_delivery_pick_day_options');
        echo '<input type="text" name="wc_delivery_pick_day_options[blackout_dates]" value="' . esc_attr($options['blackout_dates'] ?? '') . '" class="regular-text" placeholder="2025-12-25,2025-01-01">';
    }, 'wc-delivery-pickday-settings', 'wc_delivery_pick_day_main_section');

    add_settings_field('no_delivery_days', __('No Delivery Days', 'wc-delivery-pickday'), function () {
        $options = get_option('wc_delivery_pick_day_options');
        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday'
        ];
        $selected = !empty($options['no_delivery_days']) ? (is_array($options['no_delivery_days']) ? $options['no_delivery_days'] : explode(',', $options['no_delivery_days'])) : [];
        foreach ($days as $num => $label) {
            echo '<label style="margin-right:10px;"><input type="checkbox" name="wc_delivery_pick_day_options[no_delivery_days][]" value="' . $num . '" ' . (in_array((string)$num, $selected) ? 'checked' : '') . '> ' . esc_html($label) . '</label>';
        }
    }, 'wc-delivery-pickday-settings', 'wc_delivery_pick_day_main_section');

    add_settings_field('delivery_times', __('Delivery Times (comma separated)', 'wc-delivery-pickday'), function () {
        $options = get_option('wc_delivery_pick_day_options');
        echo '<input type="text" name="wc_delivery_pick_day_options[delivery_times]" value="' . esc_attr($options['delivery_times'] ?? '') . '" class="regular-text">';
    }, 'wc-delivery-pickday-settings', 'wc_delivery_pick_day_main_section');

    add_settings_field('field_required', __('Field Required', 'wc-delivery-pickday'), function () {
        $options = get_option('wc_delivery_pick_day_options');
        echo '<label><input type="checkbox" name="wc_delivery_pick_day_options[field_required]" value="1" ' . (!empty($options['field_required']) ? 'checked' : '') . '> ' . __('Make the field required', 'wc-delivery-pickday') . '</label>';
    }, 'wc-delivery-pickday-settings', 'wc_delivery_pick_day_main_section');

    add_settings_field('accepted_locations', __('Accepted Locations (one per line)', 'wc-delivery-pickday'), function () {
        $options = get_option('wc_delivery_pick_day_options');
        echo '<textarea name="wc_delivery_pick_day_options[accepted_locations]" rows="5" class="regular-text">' . esc_textarea($options['accepted_locations'] ?? '') . '</textarea>';
    }, 'wc-delivery-pickday-settings', 'wc_delivery_pick_day_main_section');
});
