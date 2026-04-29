<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Listing;

use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingStatus;
use DateMalformedStringException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ListingTest extends TestCase
{
    /**
     * @throws DateMalformedStringException
     */
    public function testMarkNotFoundIncrementsNotFoundCounterOnly(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = $this->listing($now);

        $listing->markNotFound('HTTP 404', $now, $now->modify('+5 minutes'));

        self::assertSame(ListingStatus::NotFound, $listing->getStatus());
        self::assertSame(1, $listing->getConsecutiveNotFoundCount());
        self::assertSame(0, $listing->getConsecutiveFetchErrorCount());
        self::assertSame($now, $listing->getLastCheckedAt());
        self::assertEquals($now->modify('+5 minutes'), $listing->getNextCheckAt());
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testMarkFetchErrorIncrementsFetchErrorCounterOnly(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = $this->listing($now);
        $listing->markNotFound('HTTP 404', $now, $now);

        $listing->markFetchError('timeout', $now, $now->modify('+5 minutes'));

        self::assertSame(ListingStatus::ParseError, $listing->getStatus());
        self::assertSame(0, $listing->getConsecutiveNotFoundCount());
        self::assertSame(1, $listing->getConsecutiveFetchErrorCount());
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testMarkNoPriceSetsNoPriceStatusAndFetchCounter(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = $this->listing($now);

        $listing->markChecked(null, null, 'Listing title', 'abc123', $now, $now->modify('+5 minutes'));

        self::assertSame(ListingStatus::NoPrice, $listing->getStatus());
        self::assertSame(0, $listing->getConsecutiveNotFoundCount());
        self::assertSame(1, $listing->getConsecutiveFetchErrorCount());
        self::assertNull($listing->getCurrentPrice());
        self::assertSame('Listing title', $listing->getTitle());
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSuccessfulUpdateResetsCountersAndClearsUnavailableNotificationMarker(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = $this->listing($now);
        $listing->markNotFound('gone', $now, $now);
        $listing->markFetchError('timeout', $now, $now);
        $listing->markUnavailableNotified($now);

        $listing->markChecked(1200, 'UAH', 'Updated title', 'updated123', $now, $now->modify('+5 minutes'));

        self::assertSame(ListingStatus::Active, $listing->getStatus());
        self::assertSame(0, $listing->getConsecutiveNotFoundCount());
        self::assertSame(0, $listing->getConsecutiveFetchErrorCount());
        self::assertNull($listing->getUnavailableNotifiedAt());
        self::assertSame(1200, $listing->getCurrentPrice());
        self::assertSame('UAH', $listing->getCurrency());
    }

    public function testMarkUnavailableNotifiedStoresTimestamp(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = $this->listing($now);

        $listing->markUnavailableNotified($now);

        self::assertSame($now, $listing->getUnavailableNotifiedAt());
    }

    private function listing(DateTimeImmutable $now): Listing
    {
        return new Listing(
            'https://olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'https://www.olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'abc123',
            $now,
        );
    }
}
