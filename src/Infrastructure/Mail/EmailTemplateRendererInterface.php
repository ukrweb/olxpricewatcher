<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Notification\NotificationMessage;
use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;

interface EmailTemplateRendererInterface
{
    public function renderConfirmation(Subscription $subscription, string $confirmationUrl): NotificationMessage;

    public function renderPriceChanged(
        Subscription $subscription,
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
    ): NotificationMessage;

    public function renderListingUnavailable(Subscription $subscription, Listing $listing): NotificationMessage;
}
