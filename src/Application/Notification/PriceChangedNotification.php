<?php

declare(strict_types=1);

namespace App\Application\Notification;

use App\Domain\Listing\Listing;

final class PriceChangedNotification
{
    public function __construct(
        public Listing $listing,
        public ?int $oldPrice,
        public int $newPrice,
        public string $currency,
    ) {
    }
}
