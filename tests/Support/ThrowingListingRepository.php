<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;

final class ThrowingListingRepository implements ListingRepositoryInterface
{
    public function findByNormalizedUrl(string $normalizedUrl): ?Listing
    {
        throw new RuntimeException('Storage is unavailable.');
    }

    public function findDueWithActiveSubscriptions(DateTimeImmutable $now): array
    {
        throw new RuntimeException('Storage is unavailable.');
    }

    public function save(Listing $listing): void
    {
        throw new RuntimeException('Storage is unavailable.');
    }
}
