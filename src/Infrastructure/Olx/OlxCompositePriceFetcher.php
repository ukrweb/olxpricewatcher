<?php

declare(strict_types=1);

namespace App\Infrastructure\Olx;

use App\Domain\Price\PriceFetcherInterface;
use App\Domain\Price\PriceFetchResult;

final readonly class OlxCompositePriceFetcher implements PriceFetcherInterface
{
    public function __construct(
        private OlxHttpClient $httpClient,
        private OlxJsonLdPriceExtractor $jsonLdExtractor,
        private OlxHtmlPriceExtractor $htmlExtractor,
    ) {
    }

    /**
     * Fetches OLX HTML and extracts a price using JSON-LD first, then visible HTML fallback.
     */
    public function fetch(string $url): PriceFetchResult
    {
        $html = $this->httpClient->get($url);

        return $this->jsonLdExtractor->extract($html)
            ?? $this->htmlExtractor->extract($html)
            ?? PriceFetchResult::notFound('none', 'Price was not found in JSON-LD or supported HTML selectors.');
    }
}
