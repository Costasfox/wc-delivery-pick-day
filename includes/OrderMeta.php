<?php
/**
 * Order administration and email presentation.
 *
 * @package CostasCh\WCDelivery
 */

declare(strict_types=1);

namespace CostasCh\WCDelivery;

final class OrderMeta
{
    public static function boot(): void
    {
        add_action('woocommerce_admin_order_data_after_shipping_address', [self::class, 'admin']);
        add_filter('woocommerce_email_order_meta_fields', [self::class, 'email'], 10, 3);
    }

    public static function admin(object $order): void
    {
        $values = self::values($order);
        if ($values['date'] === '' && $values['time'] === '' && $values['location'] === '') {
            return;
        }
        echo '<div class="wc-delivery-order-meta"><h3>' . esc_html__('Delivery schedule', 'wc-delivery-pickday') . '</h3>';
        echo '<p><strong>' . esc_html__('Date and time:', 'wc-delivery-pickday') . '</strong> ' . esc_html(trim($values['date'] . ' ' . $values['time'])) . '</p>';
        echo '<p><strong>' . esc_html__('Location:', 'wc-delivery-pickday') . '</strong> ' . esc_html($values['location']) . '</p></div>';
    }

    public static function email(array $fields, bool $sentToAdmin, object $order): array
    {
        unset($sentToAdmin);
        $values = self::values($order);
        if ($values['date'] !== '' || $values['time'] !== '') {
            $fields['wc_delivery_schedule'] = [
                'label' => __('Delivery date & time', 'wc-delivery-pickday'),
                'value' => trim($values['date'] . ' ' . $values['time']),
            ];
        }
        if ($values['location'] !== '') {
            $fields['wc_delivery_location'] = [
                'label' => __('Delivery location', 'wc-delivery-pickday'),
                'value' => $values['location'],
            ];
        }
        return $fields;
    }

    private static function values(object $order): array
    {
        $date = (string) $order->get_meta(OrderCapacity::DATE_META);
        $time = (string) $order->get_meta(Checkout::TIME_META);
        $location = (string) $order->get_meta(Checkout::LOCATION_META);

        return [
            'date' => $date !== '' ? $date : (string) $order->get_meta(OrderCapacity::LEGACY_DATE_META),
            'time' => $time !== '' ? $time : (string) $order->get_meta(Checkout::LEGACY_TIME_META),
            'location' => $location !== '' ? $location : (string) $order->get_meta(Checkout::LEGACY_LOCATION_META),
        ];
    }
}
