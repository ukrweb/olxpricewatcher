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

    /** @var list<string> */
    public array $fetchedUrls = [];

    public int $calls = 0;

    /**
     * @throws Throwable
     */
    public function fetch(string $url): PriceFetchResult
    {
        ++$this->calls;
        $this->fetchedUrls[] = $url;

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
        $this->fetchedUrls = [];
        $this->calls = 0;
    }
}
