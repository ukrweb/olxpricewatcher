<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Listing;

use App\Domain\Listing\ListingUrlNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ListingUrlNormalizerTest extends TestCase
{
    public function testNormalizesBareOlxHostUrl(): void
    {
        $result = (new ListingUrlNormalizer())->normalize(
            'https://olx.ua/d/uk/obyavlenie/example-IDbare123.html',
        );

        self::assertSame('https://www.olx.ua/d/uk/obyavlenie/example-IDbare123.html', $result->normalizedUrl);
        self::assertSame('bare123', $result->externalId);
    }

    public function testNormalizesMobileOlxUrlAndExtractsExternalId(): void
    {
        $result = (new ListingUrlNormalizer())->normalize(
            'https://m.olx.ua/d/uk/obyavlenie/example-IDtrack123.html?utm_source=test',
        );

        self::assertSame('https://www.olx.ua/d/uk/obyavlenie/example-IDtrack123.html', $result->normalizedUrl);
        self::assertSame('track123', $result->externalId);
    }

    public function testWwwOlxUrlRemainsCanonicalAndQueryIsRemoved(): void
    {
        $result = (new ListingUrlNormalizer())->normalize(
            'https://www.olx.ua/d/uk/obyavlenie/example-IDxyz789.html?utm_source=test&foo=bar',
        );

        self::assertSame('https://www.olx.ua/d/uk/obyavlenie/example-IDxyz789.html', $result->normalizedUrl);
        self::assertSame('xyz789', $result->externalId);
    }

    public function testRemovesFragmentsAndCollapsesRepeatedSlashes(): void
    {
        $result = (new ListingUrlNormalizer())->normalize(
            'https://www.olx.ua//d//uk//obyavlenie/example-IDfragment123.html#photos',
        );

        self::assertSame('https://www.olx.ua/d/uk/obyavlenie/example-IDfragment123.html', $result->normalizedUrl);
        self::assertSame('fragment123', $result->externalId);
    }

    public function testUrlWithoutExternalIdIsAccepted(): void
    {
        $result = (new ListingUrlNormalizer())->normalize('https://www.olx.ua/d/uk/obyavlenie/example.html');

        self::assertSame('https://www.olx.ua/d/uk/obyavlenie/example.html', $result->normalizedUrl);
        self::assertNull($result->externalId);
    }

    public function testRejectsNonOlxUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ListingUrlNormalizer())->normalize('https://example.com/listing');
    }

    public function testRejectsInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ListingUrlNormalizer())->normalize('not a url');
    }
}
