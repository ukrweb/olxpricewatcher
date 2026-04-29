<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\ClockInterface;
use DateTimeImmutable;

final class MutableClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct()
    {
        $this->now = new DateTimeImmutable('2026-04-26 10:00:00');
    }

    public function setNow(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
