<?php

declare(strict_types=1);

namespace App\Application\Notification;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;

interface NotifierInterface
{
    public function sendSubscriptionConfirmation(Subscription $subscription): void;

    /** @param list<Subscription> $subscriptions */
    public function sendPriceChanged(
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
        array $subscriptions,
    ): void;

    /** @param list<Subscription> $subscriptions */
    public function sendListingUnavailable(Listing $listing, array $subscriptions): void;
}
