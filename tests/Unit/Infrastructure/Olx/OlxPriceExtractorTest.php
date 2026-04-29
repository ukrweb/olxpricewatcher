<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Olx;

use App\Infrastructure\Olx\OlxHtmlPriceExtractor;
use App\Infrastructure\Olx\OlxJsonLdPriceExtractor;
use App\Infrastructure\Olx\OlxPrerenderedStatePriceExtractor;
use PHPUnit\Framework\TestCase;

final class OlxPriceExtractorTest extends TestCase
{
    public function testPrerenderedStatePriceExtraction(): void
    {
        $html = <<<'HTML'
<script>
window.__PRERENDERED_STATE__ = {
  "ad": {"ad": {"title": "Acer keyboard cover", "price": {"regularPrice": {"value": 540, "currencyCode": "UAH"}}}}
};
</script>
HTML;

        $result = (new OlxPrerenderedStatePriceExtractor())->extract($html);

        self::assertNotNull($result);
        self::assertNotNull($result->price);
        self::assertSame(540, $result->price->amount);
        self::assertSame('UAH', $result->price->currency);
        self::assertSame('Acer keyboard cover', $result->title);
        self::assertSame('prerendered_state', $result->source);
    }

    public function testPrerenderedStateSupportsEscapedJsonString(): void
    {
        $json = json_encode([
            'ad' => [
                'ad' => [
                    'title' => 'Escaped listing',
                    'price' => [
                        'regularPrice' => [
                            'value' => '1 250',
                            'currencyCode' => 'usd',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $html = sprintf(
            '<script>window.__PRERENDERED_STATE__ = %s;</script>',
            json_encode($json, JSON_THROW_ON_ERROR),
        );

        $result = (new OlxPrerenderedStatePriceExtractor())->extract($html);

        self::assertNotNull($result);
        self::assertNotNull($result->price);
        self::assertSame(1250, $result->price->amount);
        self::assertSame('USD', $result->price->currency);
        self::assertSame('Escaped listing', $result->title);
    }

    public function testPrerenderedStateReturnsNullForInvalidJson(): void
    {
        $html = '<script>window.__PRERENDERED_STATE__ = {"ad": {"ad": bad json};</script>';

        self::assertNull((new OlxPrerenderedStatePriceExtractor())->extract($html));
    }

    public function testPrerenderedStateReturnsNullWhenPriceIsMissing(): void
    {
        $html = '<script>window.__PRERENDERED_STATE__ = {"ad":{"ad":{"title":"No price"}}};</script>';

        self::assertNull((new OlxPrerenderedStatePriceExtractor())->extract($html));
    }

    public function testPrerenderedStateUsesDisplayValueFallbackWhenSafe(): void
    {
        $html = <<<'HTML'
<script>
window.__PRERENDERED_STATE__ = {
  "ad": {"ad": {"title": "Display price", "price": {"regularPrice": {"displayValue": "2 300 грн."}}}}
};
</script>
HTML;

        $result = (new OlxPrerenderedStatePriceExtractor())->extract($html);

        self::assertNotNull($result);
        self::assertNotNull($result->price);
        self::assertSame(2300, $result->price->amount);
        self::assertSame('UAH', $result->price->currency);
    }

    public function testPrerenderedStateIgnoresUnsafeDisplayValueFallback(): void
    {
        $html = <<<'HTML'
<script>
window.__PRERENDERED_STATE__ = {
  "ad": {"ad": {"title": "Unsafe display", "price": {"regularPrice": {"displayValue": "item code 2300"}}}}
};
</script>
HTML;

        self::assertNull((new OlxPrerenderedStatePriceExtractor())->extract($html));
    }

    public function testJsonLdPriceExtraction(): void
    {
        $html = <<<'HTML'
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"Product","name":"Phone","offers":{"price":"12 500","priceCurrency":"UAH"}}
</script>
HTML;

        $result = (new OlxJsonLdPriceExtractor())->extract($html);

        self::assertNotNull($result);
        self::assertNotNull($result->price);
        self::assertSame(12500, $result->price->amount);
        self::assertSame('UAH', $result->price->currency);
        self::assertSame('Phone', $result->title);
        self::assertSame('json_ld', $result->source);
    }

    public function testJsonLdGraphPriceExtraction(): void
    {
        $html = <<<'HTML'
<script type="application/ld+json">
{"@graph":[{"@type":"BreadcrumbList"},{"@type":"Product","name":"Bike","offers":{"price":700,"priceCurrency":"USD"}}]}
</script>
HTML;

        $result = (new OlxJsonLdPriceExtractor())->extract($html);

        self::assertNotNull($result);
        self::assertNotNull($result->price);
        self::assertSame(700, $result->price->amount);
        self::assertSame('USD', $result->price->currency);
    }

    public function testJsonLdUsesLaterBlockWhenFirstBlockHasNoOffers(): void
    {
        $html = <<<'HTML'
<script type="application/ld+json">{"@type":"BreadcrumbList"}</script>
<script type="application/ld+json">{"@type":"Product","name":"Lamp","offers":{"price":"1 250"}}</script>
HTML;

        $result = (new OlxJsonLdPriceExtractor())->extract($html);

        self::assertNotNull($result);
        self::assertNotNull($result->price);
        self::assertSame(1250, $result->price->amount);
        self::assertSame('UAH', $result->price->currency);
    }

    public function testJsonLdIgnoresMissingOffersAndInvalidJson(): void
    {
        $extractor = new OlxJsonLdPriceExtractor();

        self::assertNull($extractor->extract('<script type="application/ld+json">{"name":"No offers"}</script>'));
        self::assertNull($extractor->extract('<script type="application/ld+json">{bad json</script>'));
    }

    public function testHtmlFallbackExtraction(): void
    {
        $html = '<title>Desk</title><div data-testid="ad-price-container"><h3>3 100 грн.</h3></div>';

        $result = (new OlxHtmlPriceExtractor())->extract($html);

        self::assertNotNull($result);
        self::assertNotNull($result->price);
        self::assertSame(3100, $result->price->amount);
        self::assertSame('UAH', $result->price->currency);
        self::assertSame('html', $result->source);
    }

    public function testHtmlCurrencyExtraction(): void
    {
        $extractor = new OlxHtmlPriceExtractor();

        self::assertSame('UAH', $this->currencyFromHtml($extractor, '500 UAH'));
        self::assertSame('USD', $this->currencyFromHtml($extractor, '$50'));
        self::assertSame('USD', $this->currencyFromHtml($extractor, '50 USD'));
        self::assertSame('EUR', $this->currencyFromHtml($extractor, '50 €'));
        self::assertSame('EUR', $this->currencyFromHtml($extractor, '50 EUR'));
    }

    public function testNoPriceFound(): void
    {
        self::assertNull((new OlxPrerenderedStatePriceExtractor())->extract('<html></html>'));
        self::assertNull((new OlxJsonLdPriceExtractor())->extract('<html></html>'));
        self::assertNull((new OlxHtmlPriceExtractor())->extract('<html><title>No price</title></html>'));
    }

    private function currencyFromHtml(OlxHtmlPriceExtractor $extractor, string $priceText): string
    {
        $result = $extractor->extract(sprintf('<div data-testid="ad-price">%s</div>', $priceText));

        self::assertNotNull($result);
        self::assertNotNull($result->price);

        return $result->price->currency;
    }
}
