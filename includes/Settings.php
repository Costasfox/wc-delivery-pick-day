<?php
/**
 * Typed settings and administration UI.
 *
 * @package CostasCh\WCDelivery
 */

declare(strict_types=1);

namespace CostasCh\WCDelivery;

final class Settings
{
    public const OPTION = 'wc_delivery_pick_day_options';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'register']);
    }

    public static function defaults(): array
    {
        return [
            'field_title' => __('Delivery details', 'wc-delivery-pickday'),
            'field_label' => __('Delivery date', 'wc-delivery-pickday'),
            'delivery_times' => ['10:00', '12:00', '14:00', '16:00'],
            'blackout_dates' => [],
            'no_delivery_days' => [0],
            'accepted_locations' => [],
            'max_orders_per_day' => 10,
            'field_required' => true,
        ];
    }

    public static function all(): array
    {
        $stored = get_option(self::OPTION, []);
        return self::sanitize(array_replace(self::defaults(), is_array($stored) ? $stored : []));
    }

    public static function menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Delivery capacity', 'wc-delivery-pickday'),
            __('Delivery capacity', 'wc-delivery-pickday'),
            'manage_woocommerce',
            'wc-delivery-capacity',
            [self::class, 'page']
        );
    }

    public static function register(): void
    {
        register_setting('wc_delivery_capacity', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
        ]);

        add_settings_section('wc_delivery_main', __('Availability rules', 'wc-delivery-pickday'), static function (): void {
            echo '<p>' . esc_html__('These rules are validated on the server for both classic and block checkout.', 'wc-delivery-pickday') . '</p>';
        }, 'wc-delivery-capacity');

        $fields = [
            'field_title' => [__('Section title', 'wc-delivery-pickday'), 'text'],
            'field_label' => [__('Date field label', 'wc-delivery-pickday'), 'text'],
            'delivery_times' => [__('Delivery times', 'wc-delivery-pickday'), 'textarea'],
            'blackout_dates' => [__('Blackout dates', 'wc-delivery-pickday'), 'textarea'],
            'accepted_locations' => [__('Accepted locations', 'wc-delivery-pickday'), 'textarea'],
            'max_orders_per_day' => [__('Maximum orders per day', 'wc-delivery-pickday'), 'number'],
            'field_required' => [__('Required fields', 'wc-delivery-pickday'), 'checkbox'],
        ];

        foreach ($fields as $key => [$label, $type]) {
            add_settings_field($key, $label, static function () use ($key, $type): void {
                self::renderField($key, $type);
            }, 'wc-delivery-capacity', 'wc_delivery_main');
        }

        add_settings_field('no_delivery_days', __('Closed weekdays', 'wc-delivery-pickday'), [self::class, 'renderWeekdays'], 'wc-delivery-capacity', 'wc_delivery_main');
    }

    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $defaults = self::defaults();

        $times = self::sanitizeList($input['delivery_times'] ?? [], '/^(?:[01]\d|2[0-3]):[0-5]\d$/');
        if ($times === []) {
            $times = $defaults['delivery_times'];
        }

        $rawWeekdays = $input['no_delivery_days'] ?? [];
        $weekdays = is_array($rawWeekdays) ? $rawWeekdays : preg_split('/[\s,]+/', (string) $rawWeekdays);

        return [
            'field_title' => sanitize_text_field((string) ($input['field_title'] ?? $defaults['field_title'])),
            'field_label' => sanitize_text_field((string) ($input['field_label'] ?? $defaults['field_label'])),
            'delivery_times' => $times,
            'blackout_dates' => self::sanitizeList($input['blackout_dates'] ?? [], '/^\d{4}-\d{2}-\d{2}$/'),
            'accepted_locations' => self::sanitizeList($input['accepted_locations'] ?? []),
            'max_orders_per_day' => max(1, min(500, absint($input['max_orders_per_day'] ?? $defaults['max_orders_per_day']))),
            'field_required' => ! empty($input['field_required']),
            'no_delivery_days' => array_values(array_unique(array_filter(
                array_map('absint', $weekdays ?: []),
                static fn (int $day): bool => $day <= 6
            ))),
        ];
    }

    public static function page(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You are not allowed to manage delivery settings.', 'wc-delivery-pickday'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Delivery scheduling & capacity', 'wc-delivery-pickday'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wc_delivery_capacity'); ?>
                <?php do_settings_sections('wc-delivery-capacity'); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function renderField(string $key, string $type): void
    {
        $settings = self::all();
        $name = self::OPTION . '[' . $key . ']';
        $value = $settings[$key] ?? '';

        if ($type === 'checkbox') {
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked((bool) $value, true, false) . '> ' . esc_html__('Require date, time and location at checkout', 'wc-delivery-pickday') . '</label>';
            return;
        }

        if ($type === 'textarea') {
            $display = is_array($value) ? implode("\n", $value) : (string) $value;
            echo '<textarea class="large-text code" rows="5" name="' . esc_attr($name) . '">' . esc_textarea($display) . '</textarea>';
            echo '<p class="description">' . esc_html($key === 'delivery_times' ? __('One 24-hour time per line, for example 10:00.', 'wc-delivery-pickday') : __('One value per line.', 'wc-delivery-pickday')) . '</p>';
            return;
        }

        $attributes = $type === 'number' ? ' min="1" max="500" step="1"' : '';
        echo '<input class="regular-text" type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"' . $attributes . '>';
    }

    public static function renderWeekdays(): void
    {
        $selected = array_map('intval', self::all()['no_delivery_days']);
        $days = [
            0 => __('Sunday', 'wc-delivery-pickday'),
            1 => __('Monday', 'wc-delivery-pickday'),
            2 => __('Tuesday', 'wc-delivery-pickday'),
            3 => __('Wednesday', 'wc-delivery-pickday'),
            4 => __('Thursday', 'wc-delivery-pickday'),
            5 => __('Friday', 'wc-delivery-pickday'),
            6 => __('Saturday', 'wc-delivery-pickday'),
        ];
        foreach ($days as $number => $label) {
            echo '<label style="display:inline-block;margin:0 16px 8px 0"><input type="checkbox" name="' . esc_attr(self::OPTION) . '[no_delivery_days][]" value="' . esc_attr((string) $number) . '" ' . checked(in_array($number, $selected, true), true, false) . '> ' . esc_html($label) . '</label>';
        }
    }

    private static function sanitizeList(mixed $value, ?string $pattern = null): array
    {
        $values = is_array($value) ? $value : preg_split('/[\r\n,]+/', (string) $value);
        $values = array_map(static fn (mixed $item): string => sanitize_text_field(trim((string) $item)), $values ?: []);
        $values = array_filter($values, static fn (string $item): bool => $item !== '' && ($pattern === null || preg_match($pattern, $item) === 1));
        return array_values(array_unique($values));
    }
}
