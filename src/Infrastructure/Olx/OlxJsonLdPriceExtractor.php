<?php

declare(strict_types=1);

namespace App\Infrastructure\Olx;

use App\Domain\Price\PriceFetchResult;

final class OlxJsonLdPriceExtractor
{
    public function extract(string $html): ?PriceFetchResult
    {
        preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $matches,
        );

        foreach ($matches[1] as $json) {
            $decoded = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (!is_array($decoded)) {
                continue;
            }

            $items = isset($decoded['@graph']) && is_array($decoded['@graph']) ? $decoded['@graph'] : [$decoded];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $offers = $item['offers'] ?? null;
                if (is_array($offers) && array_key_exists('price', $offers)) {
                    $price = $this->parsePrice((string) $offers['price']);
                    if ($price === null) {
                        continue;
                    }

                    return PriceFetchResult::found(
                        $price,
                        strtoupper((string) ($offers['priceCurrency'] ?? 'UAH')),
                        isset($item['name']) ? (string) $item['name'] : null,
                        'json_ld',
                    );
                }
            }
        }

        return null;
    }

    private function parsePrice(string $rawPrice): ?int
    {
        $digits = preg_replace('/\D/u', '', $rawPrice);

        return $digits === '' ? null : (int) $digits;
    }
}
