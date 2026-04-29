<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

use App\Domain\Listing\Listing;

interface SubscriptionRepositoryInterface
{
    /**
     * Finds one subscription for a listing and normalized email address.
     */
    public function findByListingAndEmail(Listing $listing, string $email): ?Subscription;

    /**
     * Finds a subscription by confirmation token regardless of status.
     */
    public function findByConfirmationToken(string $token): ?Subscription;

    /**
     * Finds active subscriptions for the given listing.
     *
     * @return list<Subscription>
     */
    public function findActiveByListing(Listing $listing): array;

    /**
     * Persists subscription changes.
     */
    public function save(Subscription $subscription): void;
}
