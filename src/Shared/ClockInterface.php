<?php

declare(strict_types=1);

namespace App\Shared;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
