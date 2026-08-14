<?php
/**
 * HPOS-compatible order capacity queries.
 *
 * @package CostasCh\WCDelivery
 */

declare(strict_types=1);

namespace CostasCh\WCDelivery;

final class OrderCapacity
{
    public const DATE_META = '_wc_delivery_date';
    public const LEGACY_DATE_META = '_delivery_pick_day';

    public function hasCapacity(string $date, int $limit): bool
    {
        return $this->countForDate($date, $limit) < $limit;
    }

    public function countForDate(string $date, int $limit = 500): int
    {
        $statuses = array_values(array_unique(array_merge(wc_get_is_paid_statuses(), ['on-hold', 'pending'])));
        $orders = wc_get_orders([
            'status' => $statuses,
            'limit' => max(1, $limit),
            'return' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => self::DATE_META,
                    'value' => $date,
                    'compare' => '=',
                ],
                [
                    'key' => self::LEGACY_DATE_META,
                    'value' => $date,
                    'compare' => '=',
                ],
            ],
        ]);

        return is_array($orders) ? count($orders) : 0;
    }
}
