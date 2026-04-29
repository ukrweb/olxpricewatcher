<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\SleeperInterface;

final class NoOpSleeper implements SleeperInterface
{
    /** @var list<int> */
    public array $seconds = [];

    public function sleep(int $seconds): void
    {
        $this->seconds[] = $seconds;
    }
}
