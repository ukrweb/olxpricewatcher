<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

use App\Domain\Listing\Listing;
use DateTimeImmutable;

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
     * Finds the latest successful email send timestamp for a normalized recipient address.
     */
    public function findLatestEmailSentAtByEmail(string $email): ?DateTimeImmutable;

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
