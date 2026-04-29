<?php

declare(strict_types=1);

namespace App\Infrastructure\Olx;

use App\Domain\Price\PriceFetchResult;

final class OlxHtmlPriceExtractor
{
    /** @var list<string> */
    private const array PRICE_PATTERNS = [
        '/data-testid=["\']ad-price-container["\'][^>]*>(.*?)<\/[^>]+>/is',
        '/data-testid=["\']ad-price["\'][^>]*>(.*?)<\/[^>]+>/is',
        '/class=["\'][^"\']*pricelabel__value[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/is',
        '/class=["\'][^"\']*price-wrapper[^"\']*["\'][\s\S]{0,500}?<h3[^>]*>(.*?)<\/h3>/is',
    ];

    public function extract(string $html): ?PriceFetchResult
    {
        foreach (self::PRICE_PATTERNS as $pattern) {
            if (preg_match($pattern, $html, $matches) !== 1) {
                continue;
            }

            $text = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $price = $this->parsePrice($text);
            if ($price === null) {
                continue;
            }

            return PriceFetchResult::found($price, $this->parseCurrency($text), $this->extractTitle($html), 'html');
        }

        return null;
    }

    private function parsePrice(string $text): ?int
    {
        $digits = preg_replace('/\D/u', '', $text);

        return $digits === '' ? null : (int) $digits;
    }

    private function parseCurrency(string $text): string
    {
        $upper = mb_strtoupper($text);

        return match (true) {
            str_contains($upper, 'USD') || str_contains($text, '$') => 'USD',
            str_contains($upper, 'EUR') || str_contains($text, '€') => 'EUR',
            default => 'UAH',
        };
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $title === '' ? null : $title;
    }
}
