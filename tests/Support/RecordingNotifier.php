<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Notification\NotifierInterface;
use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;

final class RecordingNotifier implements NotifierInterface
{
    /** @var list<Subscription> */
    public array $confirmations = [];

    /** @var list<list<Subscription>> */
    public array $priceChangeRecipients = [];

    /** @var list<list<Subscription>> */
    public array $unavailableRecipients = [];

    public function sendSubscriptionConfirmation(Subscription $subscription): void
    {
        $this->confirmations[] = $subscription;
    }

    public function sendPriceChanged(
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
        array $subscriptions,
    ): void {
        $this->priceChangeRecipients[] = $subscriptions;
    }

    public function sendListingUnavailable(Listing $listing, array $subscriptions): void
    {
        $this->unavailableRecipients[] = $subscriptions;
    }
}
