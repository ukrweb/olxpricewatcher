<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use DateTimeImmutable;
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

    public function findLatestEmailSentAtByEmail(string $email): ?DateTimeImmutable
    {
        $latestEmailSentAt = $this->entityManager->createQueryBuilder()
            ->select('MAX(s.lastEmailSentAt)')
            ->from(Subscription::class, 's')
            ->andWhere('s.email = :email')
            ->andWhere('s.lastEmailSentAt IS NOT NULL')
            ->setParameter('email', mb_strtolower($email))
            ->getQuery()
            ->getSingleScalarResult();

        if (!is_string($latestEmailSentAt) || $latestEmailSentAt === '') {
            return null;
        }

        return new DateTimeImmutable($latestEmailSentAt);
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
