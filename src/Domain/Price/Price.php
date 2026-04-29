<?php

declare(strict_types=1);

namespace App\Domain\Price;

final class Price
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {
    }
}
