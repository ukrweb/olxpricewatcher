<?php

declare(strict_types=1);

namespace App\Domain\Listing;

final class NormalizedListingUrl
{
    public function __construct(
        public string $originalUrl,
        public string $normalizedUrl,
        public ?string $externalId,
    ) {
    }
}
