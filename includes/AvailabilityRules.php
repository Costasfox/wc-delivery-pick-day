<?php
/**
 * Pure delivery-domain validation rules.
 *
 * @package CostasCh\WCDelivery
 */

declare(strict_types=1);

namespace CostasCh\WCDelivery;

use DateTimeImmutable;
use DateTimeZone;

final class AvailabilityRules
{
    public static function dateError(
        string $value,
        array $blackoutDates,
        array $disabledWeekdays,
        ?DateTimeImmutable $today = null
    ): ?string {
        $timezone = $today?->getTimezone() ?: new DateTimeZone('UTC');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if (! $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            return 'invalid_format';
        }

        $today = ($today ?: new DateTimeImmutable('today', $timezone))->setTime(0, 0);
        if ($date < $today) {
            return 'past_date';
        }

        if (in_array($value, $blackoutDates, true)) {
            return 'blackout_date';
        }

        if (in_array((int) $date->format('w'), array_map('intval', $disabledWeekdays), true)) {
            return 'disabled_weekday';
        }

        return null;
    }

    public static function timeIsAllowed(string $value, array $allowedTimes): bool
    {
        return $value !== '' && in_array($value, $allowedTimes, true);
    }

    public static function locationIsAllowed(string $value, array $allowedLocations): bool
    {
        return $value !== '' && in_array($value, $allowedLocations, true);
    }
}
