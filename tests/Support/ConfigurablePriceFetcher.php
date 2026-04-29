<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Price\PriceFetcherInterface;
use App\Domain\Price\PriceFetchResult;
use Throwable;

final class ConfigurablePriceFetcher implements PriceFetcherInterface
{
    /** @var array<string, PriceFetchResult|Throwable> */
    private array $results = [];

    /**
     * @throws Throwable
     */
    public function fetch(string $url): PriceFetchResult
    {
        $result = $this->results[$url] ?? PriceFetchResult::found(
            1000,
            'UAH',
            'Functional test listing',
            'test',
        );

        if ($result instanceof Throwable) {
            throw $result;
        }

        return $result;
    }

    public function setResult(string $url, PriceFetchResult|Throwable $result): void
    {
        $this->results[$url] = $result;
    }

    public function clear(): void
    {
        $this->results = [];
    }
}
