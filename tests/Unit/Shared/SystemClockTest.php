<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testReturnsCurrentDateTime(): void
    {
        self::assertInstanceOf(DateTimeImmutable::class, (new SystemClock())->now());
    }
}
