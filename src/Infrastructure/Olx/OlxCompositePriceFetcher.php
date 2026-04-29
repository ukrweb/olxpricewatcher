<?php

declare(strict_types=1);

namespace App\Infrastructure\Olx;

use App\Domain\Price\PriceFetcherInterface;
use App\Domain\Price\PriceFetchResult;
use Psr\Log\LoggerInterface;

final readonly class OlxCompositePriceFetcher implements PriceFetcherInterface
{
    public function __construct(
        private OlxHttpClient $httpClient,
        private OlxPrerenderedStatePriceExtractor $prerenderedStateExtractor,
        private OlxJsonLdPriceExtractor $jsonLdExtractor,
        private OlxHtmlPriceExtractor $htmlExtractor,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Fetches OLX HTML and extracts a price using prerendered state, JSON-LD, then visible HTML fallback.
     */
    public function fetch(string $url): PriceFetchResult
    {
        $html = $this->httpClient->get($url);

        $result = $this->prerenderedStateExtractor->extract($html)
            ?? $this->jsonLdExtractor->extract($html)
            ?? $this->htmlExtractor->extract($html);

        if ($result instanceof PriceFetchResult) {
            $this->logger->info('OLX price extracted.', [
                'url' => $url,
                'source' => $result->source,
            ]);

            return $result;
        }

        $this->logger->warning('OLX price was not found by any extractor.', ['url' => $url]);

        return PriceFetchResult::notFound(
            'none',
            'Price was not found in PRERENDERED_STATE, JSON-LD, or supported HTML selectors.',
        );
    }
}
