<?php

declare(strict_types=1);

namespace App\Domain\Listing;

use DateTimeImmutable;

interface ListingRepositoryInterface
{
    public function findByNormalizedUrl(string $normalizedUrl): ?Listing;

    /** @return list<Listing> */
    public function findDueWithActiveSubscriptions(DateTimeImmutable $now): array;

    public function save(Listing $listing): void;
}
