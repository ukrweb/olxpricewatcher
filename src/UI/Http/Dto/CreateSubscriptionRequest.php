<?php

declare(strict_types=1);

namespace App\UI\Http\Dto;

/**
 * Request body for creating a price-change subscription.
 *
 * `url` must be an OLX listing URL. `email` must be a valid email address.
 */
final class CreateSubscriptionRequest
{
    public function __construct(
        public string $url,
        public string $email,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            (string) ($payload['url'] ?? ''),
            (string) ($payload['email'] ?? ''),
        );
    }
}
