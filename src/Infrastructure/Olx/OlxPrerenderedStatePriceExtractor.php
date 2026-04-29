<?php

declare(strict_types=1);

namespace App\Infrastructure\Olx;

use App\Domain\Price\PriceFetchResult;

final class OlxPrerenderedStatePriceExtractor
{
    public function extract(string $html): ?PriceFetchResult
    {
        $rawState = $this->extractRawState($html);
        if ($rawState === null) {
            return null;
        }

        $state = $this->decodeState($rawState);
        if (!is_array($state)) {
            return null;
        }

        $ad = $state['ad']['ad'] ?? null;
        if (!is_array($ad)) {
            return null;
        }

        $regularPrice = $ad['price']['regularPrice'] ?? null;
        if (!is_array($regularPrice)) {
            return null;
        }

        $amount = $this->parseAmount($regularPrice['value'] ?? null);
        if ($amount === null) {
            $amount = $this->parseDisplayAmount($regularPrice['displayValue'] ?? null);
        }

        if ($amount === null) {
            return null;
        }

        return PriceFetchResult::found(
            $amount,
            $this->parseCurrency($regularPrice),
            isset($ad['title']) ? trim((string) $ad['title']) : null,
            'prerendered_state',
        );
    }

    private function extractRawState(string $html): ?string
    {
        $position = strpos($html, 'window.__PRERENDERED_STATE__');
        if ($position === false) {
            return null;
        }

        $assignmentPosition = strpos($html, '=', $position);
        if ($assignmentPosition === false) {
            return null;
        }

        $start = $assignmentPosition + 1;
        while (isset($html[$start]) && ctype_space($html[$start])) {
            $start++;
        }

        $firstCharacter = $html[$start] ?? '';
        if ($firstCharacter === '{') {
            return $this->extractBalancedJsonObject($html, $start);
        }

        if ($firstCharacter === '"' || $firstCharacter === "'") {
            return $this->extractQuotedString($html, $start, $firstCharacter);
        }

        return null;
    }

    private function extractBalancedJsonObject(string $html, int $start): ?string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($html);

        for ($i = $start; $i < $length; $i++) {
            $character = $html[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
                continue;
            }

            if ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    private function extractQuotedString(string $html, int $start, string $quote): ?string
    {
        $escaped = false;
        $length = strlen($html);

        for ($i = $start + 1; $i < $length; $i++) {
            $character = $html[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($character === '\\') {
                $escaped = true;
                continue;
            }

            if ($character === $quote) {
                return substr($html, $start, $i - $start + 1);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeState(string $rawState): ?array
    {
        $decoded = json_decode(html_entity_decode(trim($rawState), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function parseAmount(mixed $rawAmount): ?int
    {
        if (is_int($rawAmount)) {
            return $rawAmount > 0 ? $rawAmount : null;
        }

        if (is_float($rawAmount)) {
            return $rawAmount > 0 ? (int) round($rawAmount) : null;
        }

        if (!is_string($rawAmount)) {
            return null;
        }

        $digits = preg_replace('/\D/u', '', $rawAmount);

        return $digits === '' ? null : (int) $digits;
    }

    private function parseDisplayAmount(mixed $rawAmount): ?int
    {
        if (!is_string($rawAmount)) {
            return null;
        }

        $value = trim($rawAmount);
        if (!preg_match('/^\d[\d\s.,]*(?:\s*(?:uah|грн\.?|usd|\$|eur|\x{20AC}))?$/iu', $value)) {
            return null;
        }

        return $this->parseAmount($value);
    }

    /**
     * @param array<string, mixed> $regularPrice
     */
    private function parseCurrency(array $regularPrice): string
    {
        if (isset($regularPrice['currencyCode']) && is_string($regularPrice['currencyCode'])) {
            return strtoupper($regularPrice['currencyCode']);
        }

        $displayValue = isset($regularPrice['displayValue']) ? (string) $regularPrice['displayValue'] : '';
        $upper = mb_strtoupper($displayValue);

        return match (true) {
            str_contains($upper, 'USD') || str_contains($displayValue, '$') => 'USD',
            str_contains($upper, 'EUR') || str_contains($displayValue, "\u{20AC}") => 'EUR',
            default => 'UAH',
        };
    }
}
