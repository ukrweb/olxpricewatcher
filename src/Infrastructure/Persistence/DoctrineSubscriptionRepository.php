<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Finds a subscription for a listing/email pair.
     */
    public function findByListingAndEmail(Listing $listing, string $email): ?Subscription
    {
        return $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'listing' => $listing,
            'email' => mb_strtolower($email),
        ]);
    }

    /**
     * Finds a subscription by confirmation token regardless of status.
     */
    public function findByConfirmationToken(string $token): ?Subscription
    {
        return $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'confirmationToken' => $token,
        ]);
    }

    /**
     * Finds active subscriptions for a listing.
     *
     * @return list<Subscription>
     */
    public function findActiveByListing(Listing $listing): array
    {
        return $this->entityManager->getRepository(Subscription::class)->findBy([
            'listing' => $listing,
            'status' => 'active',
        ]);
    }

    /**
     * Persists subscription changes immediately.
     */
    public function save(Subscription $subscription): void
    {
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }
}
