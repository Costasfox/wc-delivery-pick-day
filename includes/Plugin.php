<?php
/**
 * Plugin composition root.
 *
 * @package CostasCh\WCDelivery
 */

declare(strict_types=1);

namespace CostasCh\WCDelivery;

final class Plugin
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        load_plugin_textdomain('wc-delivery-pickday', false, dirname(plugin_basename(WC_DELIVERY_FILE)) . '/languages');
        Settings::boot();
        (new Checkout(new OrderCapacity()))->boot();
        OrderMeta::boot();
    }
}
