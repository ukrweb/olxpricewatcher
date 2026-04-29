<?php

declare(strict_types=1);

namespace App\Shared;

final class NativeSleeper implements SleeperInterface
{
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
