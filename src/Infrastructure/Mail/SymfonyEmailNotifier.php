<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Notification\NotifierInterface;
use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

final readonly class SymfonyEmailNotifier implements NotifierInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailFactory $emailFactory,
    ) {
    }

    /**
     * Sends the subscription confirmation email.
     * @throws TransportExceptionInterface
     */
    public function sendSubscriptionConfirmation(Subscription $subscription): void
    {
        $this->mailer->send($this->emailFactory->confirmationEmail($subscription));
    }

    /**
     * Sends one price-change email to each active subscription.
     *
     * @param list<Subscription> $subscriptions Active recipients for this listing.
     * @throws TransportExceptionInterface
     */
    public function sendPriceChanged(
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
        array $subscriptions,
    ): void {
        foreach ($subscriptions as $subscription) {
            $this->mailer->send($this->emailFactory->priceChangedEmail(
                $subscription,
                $listing,
                $oldPrice,
                $newPrice,
                $currency,
            ));
        }
    }

    /**
     * Sends one unavailable-listing email to each active subscription.
     *
     * @param list<Subscription> $subscriptions Active recipients for this listing.
     * @throws TransportExceptionInterface
     */
    public function sendListingUnavailable(Listing $listing, array $subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            $this->mailer->send($this->emailFactory->listingUnavailableEmail($subscription, $listing));
        }
    }
}
