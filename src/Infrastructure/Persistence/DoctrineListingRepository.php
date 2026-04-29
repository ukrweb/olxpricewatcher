<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineListingRepository implements ListingRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Finds one listing by canonical normalized URL.
     */
    public function findByNormalizedUrl(string $normalizedUrl): ?Listing
    {
        return $this->entityManager->getRepository(Listing::class)->findOneBy(['normalizedUrl' => $normalizedUrl]);
    }

    /**
     * Finds listings that are due and have at least one active subscription.
     *
     * @return list<Listing>
     */
    public function findDueWithActiveSubscriptions(DateTimeImmutable $now): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('DISTINCT l')
            ->from(Listing::class, 'l')
            ->innerJoin('l.subscriptions', 's')
            ->andWhere('s.status = :active')
            ->andWhere('l.status != :disabled')
            ->andWhere('l.nextCheckAt IS NULL OR l.nextCheckAt <= :now')
            ->setParameter('active', 'active')
            ->setParameter('disabled', 'disabled')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Persists listing changes immediately.
     */
    public function save(Listing $listing): void
    {
        $this->entityManager->persist($listing);
        $this->entityManager->flush();
    }
}
