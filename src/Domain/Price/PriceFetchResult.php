<?php

declare(strict_types=1);

namespace App\Domain\Price;

final class PriceFetchResult
{
    public function __construct(
        public ?Price $price,
        public ?string $title,
        public string $source,
        public ?string $externalId = null,
        public ?string $error = null,
    ) {
    }

    public static function found(
        int $amount,
        string $currency,
        ?string $title,
        string $source,
        ?string $externalId = null,
    ): self {
        return new self(new Price($amount, $currency), $title, $source, $externalId);
    }

    public static function notFound(string $source, ?string $error = null): self
    {
        return new self(null, null, $source, null, $error);
    }
}
