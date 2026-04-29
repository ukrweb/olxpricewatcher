<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use RuntimeException;

final class ThrowingSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function findByListingAndEmail(Listing $listing, string $email): ?Subscription
    {
        throw new RuntimeException('Storage is unavailable.');
    }

    public function findByConfirmationToken(string $token): ?Subscription
    {
        throw new RuntimeException('Storage is unavailable.');
    }

    public function findActiveByListing(Listing $listing): array
    {
        throw new RuntimeException('Storage is unavailable.');
    }

    public function save(Subscription $subscription): void
    {
        throw new RuntimeException('Storage is unavailable.');
    }
}
