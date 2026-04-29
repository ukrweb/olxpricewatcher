<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use Symfony\Component\Mime\Email;

final readonly class EmailFactory
{
    public function __construct(
        private EmailTemplateRendererInterface $templateRenderer,
        private string $mailFrom,
        private string $appBaseUrl,
    ) {
    }

    public function confirmationEmail(Subscription $subscription): Email
    {
        $confirmationUrl = sprintf(
            '%s/api/subscriptions/confirm/%s',
            rtrim($this->appBaseUrl, '/'),
            $subscription->getConfirmationToken(),
        );
        $message = $this->templateRenderer->renderConfirmation($subscription, $confirmationUrl);

        return (new Email())
            ->from($this->mailFrom)
            ->to($subscription->getEmail())
            ->subject($message->subject)
            ->text($message->body);
    }

    public function priceChangedEmail(
        Subscription $subscription,
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
    ): Email {
        $message = $this->templateRenderer->renderPriceChanged(
            $subscription,
            $listing,
            $oldPrice,
            $newPrice,
            $currency,
        );

        return (new Email())
            ->from($this->mailFrom)
            ->to($subscription->getEmail())
            ->subject($message->subject)
            ->text($message->body);
    }

    public function listingUnavailableEmail(Subscription $subscription, Listing $listing): Email
    {
        $message = $this->templateRenderer->renderListingUnavailable($subscription, $listing);

        return (new Email())
            ->from($this->mailFrom)
            ->to($subscription->getEmail())
            ->subject($message->subject)
            ->text($message->body);
    }
}
