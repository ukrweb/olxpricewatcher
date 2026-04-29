<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Notification;

use App\Application\Notification\PriceChangedNotification;
use App\Domain\Listing\Listing;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PriceChangedNotificationTest extends TestCase
{
    public function testStoresNotificationData(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = new Listing(
            'https://olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'https://www.olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'abc123',
            $now,
        );

        $notification = new PriceChangedNotification($listing, 1200, 900, 'UAH');

        self::assertSame($listing, $notification->listing);
        self::assertSame(1200, $notification->oldPrice);
        self::assertSame(900, $notification->newPrice);
        self::assertSame('UAH', $notification->currency);
    }
}
