<?php
/**
 * Plugin Name: WooCommerce Delivery Scheduling & Capacity
 * Plugin URI:  https://github.com/Costasfox/wc-delivery-pick-day
 * Description: Adds validated delivery dates, time windows, service areas, and daily capacity controls to WooCommerce checkout.
 * Version:     3.0.0
 * Author:      CostasCh
 * Author URI:  https://costasch.xyz
 * Text Domain: wc-delivery-pickday
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * WC requires at least: 8.9
 * WC tested up to: 10.7
 * License: GPL-2.0-or-later
 *
 * @package CostasCh\WCDelivery
 */

declare(strict_types=1);

namespace CostasCh\WCDelivery;

if (! defined('ABSPATH')) {
    exit;
}

define('WC_DELIVERY_VERSION', '3.0.0');
define('WC_DELIVERY_FILE', __FILE__);
define('WC_DELIVERY_DIR', plugin_dir_path(__FILE__));
define('WC_DELIVERY_URL', plugin_dir_url(__FILE__));

require_once WC_DELIVERY_DIR . 'includes/AvailabilityRules.php';
require_once WC_DELIVERY_DIR . 'includes/Settings.php';
require_once WC_DELIVERY_DIR . 'includes/OrderCapacity.php';
require_once WC_DELIVERY_DIR . 'includes/Checkout.php';
require_once WC_DELIVERY_DIR . 'includes/OrderMeta.php';
require_once WC_DELIVERY_DIR . 'includes/Plugin.php';

add_action('before_woocommerce_init', static function (): void {
    if (! class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WC_DELIVERY_FILE, true);
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', WC_DELIVERY_FILE, true);
});

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>' . esc_html__('WooCommerce Delivery Scheduling & Capacity requires WooCommerce to be installed and active.', 'wc-delivery-pickday') . '</p></div>';
        });
        return;
    }

    Plugin::boot();
});
