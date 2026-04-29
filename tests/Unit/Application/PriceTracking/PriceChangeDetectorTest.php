<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\PriceTracking;

use App\Application\PriceTracking\PriceChangeDetector;
use PHPUnit\Framework\TestCase;

final class PriceChangeDetectorTest extends TestCase
{
    public function testNullOldPriceMeansChangedForInitialization(): void
    {
        self::assertTrue((new PriceChangeDetector())->hasChanged(null, 100));
    }

    public function testSamePriceIsNotChanged(): void
    {
        self::assertFalse((new PriceChangeDetector())->hasChanged(100, 100));
    }

    public function testDifferentPriceIsChanged(): void
    {
        self::assertTrue((new PriceChangeDetector())->hasChanged(100, 90));
    }
}
