<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\PriceTracking\PriceHistoryRepositoryInterface;
use App\Domain\Price\PriceHistory;

final class InMemoryPriceHistoryRepository implements PriceHistoryRepositoryInterface
{
    /** @var list<PriceHistory> */
    public array $history = [];

    public function save(PriceHistory $history): void
    {
        $this->history[] = $history;
    }
}
