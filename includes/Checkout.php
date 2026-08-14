<?php
/**
 * Classic and block checkout integration.
 *
 * @package CostasCh\WCDelivery
 */

declare(strict_types=1);

namespace CostasCh\WCDelivery;

use DateTimeImmutable;
use DateTimeZone;
use WP_Error;

final class Checkout
{
    public const BLOCK_DATE = 'costasch-delivery/date';
    public const BLOCK_TIME = 'costasch-delivery/time';
    public const BLOCK_LOCATION = 'costasch-delivery/location';
    public const TIME_META = '_wc_delivery_time';
    public const LOCATION_META = '_wc_delivery_location';

    public function __construct(private readonly OrderCapacity $capacity)
    {
    }

    public function boot(): void
    {
        add_action('woocommerce_after_order_notes', [$this, 'renderClassic']);
        add_action('woocommerce_checkout_process', [$this, 'validateClassic']);
        add_action('woocommerce_checkout_create_order', [$this, 'saveClassic'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('woocommerce_init', [$this, 'registerBlockFields']);
        add_action('woocommerce_set_additional_field_value', [$this, 'mirrorBlockValue'], 10, 4);
    }

    public function renderClassic(mixed $checkout): void
    {
        unset($checkout);
        $settings = Settings::all();
        $required = (bool) $settings['field_required'];

        echo '<div id="wc-delivery-fields"><h3>' . esc_html($settings['field_title']) . '</h3>';
        woocommerce_form_field('wc_delivery_date', [
            'type' => 'text',
            'label' => $settings['field_label'],
            'required' => $required,
            'class' => ['form-row-wide'],
            'input_class' => ['wc-delivery-date'],
            'custom_attributes' => ['autocomplete' => 'off', 'inputmode' => 'numeric'],
        ], $this->posted('wc_delivery_date'));
        woocommerce_form_field('wc_delivery_time', [
            'type' => 'select',
            'label' => __('Delivery time', 'wc-delivery-pickday'),
            'required' => $required,
            'class' => ['form-row-wide'],
            'options' => ['' => __('Select a time', 'wc-delivery-pickday')] + array_combine($settings['delivery_times'], $settings['delivery_times']),
        ], $this->posted('wc_delivery_time'));
        if ($settings['accepted_locations'] !== []) {
            woocommerce_form_field('wc_delivery_location', [
                'type' => 'select',
                'label' => __('Delivery location', 'wc-delivery-pickday'),
                'required' => $required,
                'class' => ['form-row-wide'],
                'options' => ['' => __('Select a location', 'wc-delivery-pickday')] + array_combine($settings['accepted_locations'], $settings['accepted_locations']),
            ], $this->posted('wc_delivery_location'));
        }
        echo '</div>';
    }

    public function validateClassic(): void
    {
        $settings = Settings::all();
        $values = [
            'date' => $this->posted('wc_delivery_date'),
            'time' => $this->posted('wc_delivery_time'),
            'location' => $this->posted('wc_delivery_location'),
        ];

        foreach ($this->errors($values, $settings) as $message) {
            wc_add_notice($message, 'error');
        }
    }

    public function saveClassic(object $order, array $data): void
    {
        unset($data);
        $order->update_meta_data(OrderCapacity::DATE_META, $this->posted('wc_delivery_date'));
        $order->update_meta_data(self::TIME_META, $this->posted('wc_delivery_time'));
        $order->update_meta_data(self::LOCATION_META, $this->posted('wc_delivery_location'));
    }

    public function registerBlockFields(): void
    {
        if (! function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        $settings = Settings::all();
        $required = (bool) $settings['field_required'];
        woocommerce_register_additional_checkout_field([
            'id' => self::BLOCK_DATE,
            'label' => $settings['field_label'],
            'location' => 'order',
            'type' => 'text',
            'required' => $required,
            'attributes' => ['autocomplete' => 'off', 'pattern' => '\\d{4}-\\d{2}-\\d{2}', 'title' => __('Use YYYY-MM-DD.', 'wc-delivery-pickday')],
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => fn (mixed $value): ?WP_Error => $this->blockError('date', (string) $value),
        ]);
        woocommerce_register_additional_checkout_field([
            'id' => self::BLOCK_TIME,
            'label' => __('Delivery time', 'wc-delivery-pickday'),
            'location' => 'order',
            'type' => 'select',
            'required' => $required,
            'options' => array_map(static fn (string $time): array => ['value' => $time, 'label' => $time], $settings['delivery_times']),
            'validate_callback' => fn (mixed $value): ?WP_Error => $this->blockError('time', (string) $value),
        ]);
        if ($settings['accepted_locations'] !== []) {
            woocommerce_register_additional_checkout_field([
                'id' => self::BLOCK_LOCATION,
                'label' => __('Delivery location', 'wc-delivery-pickday'),
                'location' => 'order',
                'type' => 'select',
                'required' => $required,
                'options' => array_map(static fn (string $location): array => ['value' => $location, 'label' => $location], $settings['accepted_locations']),
                'validate_callback' => fn (mixed $value): ?WP_Error => $this->blockError('location', (string) $value),
            ]);
        }
    }

    public function mirrorBlockValue(string $key, mixed $value, string $group, object $wcObject): void
    {
        if ($group !== 'other' || ! method_exists($wcObject, 'update_meta_data')) {
            return;
        }
        $map = [
            self::BLOCK_DATE => OrderCapacity::DATE_META,
            self::BLOCK_TIME => self::TIME_META,
            self::BLOCK_LOCATION => self::LOCATION_META,
        ];
        if (isset($map[$key])) {
            $wcObject->update_meta_data($map[$key], sanitize_text_field((string) $value), true);
        }
    }

    public function assets(): void
    {
        if (! is_checkout() || is_order_received_page()) {
            return;
        }
        $settings = Settings::all();
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css', [], '4.6.13');
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js', [], '4.6.13', true);
        wp_enqueue_script('wc-delivery-checkout', WC_DELIVERY_URL . 'assets/checkout.js', ['flatpickr'], WC_DELIVERY_VERSION, true);
        wp_add_inline_script('wc-delivery-checkout', 'window.WCDeliveryConfig=' . wp_json_encode([
            'blackoutDates' => $settings['blackout_dates'],
            'disabledWeekdays' => array_map('intval', $settings['no_delivery_days']),
        ]) . ';', 'before');
    }

    private function blockError(string $field, string $value): ?WP_Error
    {
        $settings = Settings::all();
        if (! $settings['field_required'] && $value === '') {
            return null;
        }
        $errors = $this->errors([$field => $value], $settings, false);
        return $errors === [] ? null : new WP_Error('wc_delivery_' . $field, $errors[0]);
    }

    private function errors(array $values, array $settings, bool $validateRequired = true): array
    {
        $errors = [];
        $required = (bool) $settings['field_required'];
        $date = (string) ($values['date'] ?? '');
        $time = (string) ($values['time'] ?? '');
        $location = (string) ($values['location'] ?? '');

        if (array_key_exists('date', $values)) {
            if ($date === '' && $required && $validateRequired) {
                $errors[] = __('Please choose a delivery date.', 'wc-delivery-pickday');
            } elseif ($date !== '') {
                $timezone = new DateTimeZone(wp_timezone_string() ?: 'UTC');
                $error = AvailabilityRules::dateError($date, $settings['blackout_dates'], $settings['no_delivery_days'], new DateTimeImmutable('today', $timezone));
                if ($error !== null) {
                    $errors[] = __('The selected delivery date is not available.', 'wc-delivery-pickday');
                } elseif (! $this->capacity->hasCapacity($date, (int) $settings['max_orders_per_day'])) {
                    $errors[] = __('The selected delivery date has reached capacity.', 'wc-delivery-pickday');
                }
            }
        }
        if (array_key_exists('time', $values) && (($time === '' && $required && $validateRequired) || ($time !== '' && ! AvailabilityRules::timeIsAllowed($time, $settings['delivery_times'])))) {
            $errors[] = __('Please choose an available delivery time.', 'wc-delivery-pickday');
        }
        if ($settings['accepted_locations'] !== [] && array_key_exists('location', $values) && (($location === '' && $required && $validateRequired) || ($location !== '' && ! AvailabilityRules::locationIsAllowed($location, $settings['accepted_locations'])))) {
            $errors[] = __('Please choose an accepted delivery location.', 'wc-delivery-pickday');
        }
        return $errors;
    }

    private function posted(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash((string) $_POST[$key])) : '';
    }
}
