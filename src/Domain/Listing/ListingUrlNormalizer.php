<?php

declare(strict_types=1);

namespace App\Domain\Listing;

use InvalidArgumentException;

final class ListingUrlNormalizer
{
    /** @var list<string> */
    private const array ALLOWED_HOSTS = ['olx.ua', 'www.olx.ua', 'm.olx.ua'];

    public function normalize(string $url): NormalizedListingUrl
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['host'], $parts['path'])) {
            throw new InvalidArgumentException('A valid OLX listing URL is required.');
        }

        $host = mb_strtolower($parts['host']);
        if (!in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new InvalidArgumentException('Only olx.ua listing URLs are supported.');
        }

        $path = preg_replace('#/+#', '/', $parts['path']) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $normalizedUrl = 'https://www.olx.ua' . $path;

        return new NormalizedListingUrl($url, $normalizedUrl, $this->extractExternalId($path));
    }

    private function extractExternalId(string $path): ?string
    {
        if (preg_match('/-ID([A-Za-z0-9]+)\.html$/', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
