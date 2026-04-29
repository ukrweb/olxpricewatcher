<?php

declare(strict_types=1);

namespace App\Application\PriceTracking;

final class PriceChangeDetector
{
    public function hasChanged(?int $oldPrice, int $newPrice): bool
    {
        return $oldPrice !== $newPrice;
    }
}
