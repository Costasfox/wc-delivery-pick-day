<?php

declare(strict_types=1);

namespace CostasCh\WCDelivery\Tests\Unit;

use CostasCh\WCDelivery\AvailabilityRules;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class AvailabilityRulesTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2026-08-14', new DateTimeZone('Europe/Athens'));
    }

    public function testAcceptsAnAvailableIsoDate(): void
    {
        self::assertNull(AvailabilityRules::dateError('2026-08-17', [], [0], $this->today));
    }

    public function testRejectsInvalidAndPastDates(): void
    {
        self::assertSame('invalid_format', AvailabilityRules::dateError('17-08-2026', [], [], $this->today));
        self::assertSame('past_date', AvailabilityRules::dateError('2026-08-13', [], [], $this->today));
    }

    public function testRejectsBlackoutsAndClosedWeekdays(): void
    {
        self::assertSame('blackout_date', AvailabilityRules::dateError('2026-08-17', ['2026-08-17'], [], $this->today));
        self::assertSame('disabled_weekday', AvailabilityRules::dateError('2026-08-16', [], [0], $this->today));
    }

    public function testUsesStrictAllowlistsForTimesAndLocations(): void
    {
        self::assertTrue(AvailabilityRules::timeIsAllowed('12:00', ['10:00', '12:00']));
        self::assertFalse(AvailabilityRules::timeIsAllowed('12:30', ['10:00', '12:00']));
        self::assertTrue(AvailabilityRules::locationIsAllowed('Kalamaria', ['Thessaloniki', 'Kalamaria']));
        self::assertFalse(AvailabilityRules::locationIsAllowed('Other', ['Thessaloniki', 'Kalamaria']));
    }
}
