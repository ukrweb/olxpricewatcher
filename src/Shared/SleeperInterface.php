<?php

declare(strict_types=1);

namespace App\Shared;

interface SleeperInterface
{
    /**
     * Pauses execution for the given number of seconds.
     */
    public function sleep(int $seconds): void;
}
