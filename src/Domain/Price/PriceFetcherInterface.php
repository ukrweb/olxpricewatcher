<?php

declare(strict_types=1);

namespace App\Domain\Price;

interface PriceFetcherInterface
{
    public function fetch(string $url): PriceFetchResult;
}
