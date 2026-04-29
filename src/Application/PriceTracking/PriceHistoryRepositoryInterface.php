<?php

declare(strict_types=1);

namespace App\Application\PriceTracking;

use App\Domain\Price\PriceHistory;

interface PriceHistoryRepositoryInterface
{
    public function save(PriceHistory $history): void;
}
