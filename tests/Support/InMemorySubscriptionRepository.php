<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;

final class InMemorySubscriptionRepository implements SubscriptionRepositoryInterface
{
    /** @var list<Subscription> */
    public array $subscriptions = [];

    public function findByListingAndEmail(Listing $listing, string $email): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if (
                $subscription->getListing() === $listing
                && $subscription->getEmail() === mb_strtolower($email)
            ) {
                return $subscription;
            }
        }

        return null;
    }

    public function findByConfirmationToken(string $token): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->getConfirmationToken() === $token) {
                return $subscription;
            }
        }

        return null;
    }

    public function findActiveByListing(Listing $listing): array
    {
        return array_values(array_filter(
            $this->subscriptions,
            static fn (Subscription $subscription): bool => $subscription->getListing() === $listing
                && $subscription->isActive(),
        ));
    }

    public function save(Subscription $subscription): void
    {
        if (!in_array($subscription, $this->subscriptions, true)) {
            $this->subscriptions[] = $subscription;
        }
    }
}
