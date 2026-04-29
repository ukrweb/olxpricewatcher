<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingRepositoryInterface;
use DateTimeImmutable;

final class InMemoryListingRepository implements ListingRepositoryInterface
{
    /** @var array<string, Listing> */
    public array $listings = [];

    /** @var list<Listing> */
    public array $dueListings = [];

    public function findByNormalizedUrl(string $normalizedUrl): ?Listing
    {
        return $this->listings[$normalizedUrl] ?? null;
    }

    public function findDueWithActiveSubscriptions(DateTimeImmutable $now): array
    {
        return $this->dueListings;
    }

    public function save(Listing $listing): void
    {
        $this->listings[$listing->getNormalizedUrl()] = $listing;
    }
}
