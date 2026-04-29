<?php

declare(strict_types=1);

namespace App\Domain\Price;

use App\Domain\Listing\ListingStatus;
use RuntimeException;
use Throwable;

final class PriceFetchException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ListingStatus $listingStatus = ListingStatus::ParseError,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
