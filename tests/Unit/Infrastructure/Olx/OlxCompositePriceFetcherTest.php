<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Olx;

use App\Infrastructure\Olx\OlxCompositePriceFetcher;
use App\Infrastructure\Olx\OlxHtmlPriceExtractor;
use App\Infrastructure\Olx\OlxHttpClient;
use App\Infrastructure\Olx\OlxJsonLdPriceExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OlxCompositePriceFetcherTest extends TestCase
{
    public function testUsesJsonLdBeforeHtmlFallback(): void
    {
        $html = <<<'HTML'
<script type="application/ld+json">
{"@type":"Product","name":"Json item","offers":{"price":"100","priceCurrency":"UAH"}}
</script>
<div data-testid="ad-price">200 грн.</div>
HTML;

        $fetcher = $this->fetcherForHtml($html);

        $result = $fetcher->fetch('https://www.olx.ua/item-IDabc123.html');

        self::assertNotNull($result->price);
        self::assertSame(100, $result->price->amount);
        self::assertSame('json_ld', $result->source);
    }

    public function testFallsBackToHtmlWhenJsonLdHasNoPrice(): void
    {
        $html = <<<'HTML'
<script type="application/ld+json">{"@type":"Product","name":"No price"}</script>
<title>Html item</title>
<div data-testid="ad-price">2 300 грн.</div>
HTML;

        $fetcher = $this->fetcherForHtml($html);

        $result = $fetcher->fetch('https://www.olx.ua/item-IDabc123.html');

        self::assertNotNull($result->price);
        self::assertSame(2300, $result->price->amount);
        self::assertSame('html', $result->source);
    }

    public function testReturnsNotFoundResultWhenNoExtractorFindsPrice(): void
    {
        $fetcher = $this->fetcherForHtml('<html><title>No price</title></html>');

        $result = $fetcher->fetch('https://www.olx.ua/item-IDabc123.html');

        self::assertNull($result->price);
        self::assertSame('none', $result->source);
        self::assertSame('Price was not found in JSON-LD or supported HTML selectors.', $result->error);
    }

    private function fetcherForHtml(string $html): OlxCompositePriceFetcher
    {
        return new OlxCompositePriceFetcher(
            new OlxHttpClient(new MockHttpClient(new MockResponse($html)), 5, 'test-agent'),
            new OlxJsonLdPriceExtractor(),
            new OlxHtmlPriceExtractor(),
        );
    }
}
