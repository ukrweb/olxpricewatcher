<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence;

use App\Domain\Listing\Listing;
use App\Domain\Price\PriceHistory;
use App\Domain\Subscription\Subscription;
use App\Infrastructure\Persistence\DoctrineListingRepository;
use App\Infrastructure\Persistence\DoctrinePriceHistoryRepository;
use App\Infrastructure\Persistence\DoctrineSubscriptionRepository;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class DoctrineRepositoryAdapterTest extends TestCase
{
    public function testListingRepositoryFindsByNormalizedUrl(): void
    {
        $listing = $this->listing();
        $objectRepository = $this->createMock(EntityRepository::class);
        $objectRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['normalizedUrl' => $listing->getNormalizedUrl()])
            ->willReturn($listing);
        $entityManager = $this->createEntityManagerWithRepository(Listing::class, $objectRepository);

        $repository = new DoctrineListingRepository($entityManager);

        self::assertSame($listing, $repository->findByNormalizedUrl($listing->getNormalizedUrl()));
    }

    public function testListingRepositoryPersistsAndFlushes(): void
    {
        $listing = $this->listing();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($listing);
        $entityManager->expects(self::once())->method('flush');

        (new DoctrineListingRepository($entityManager))->save($listing);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSubscriptionRepositoryFindsByListingAndLowercaseEmail(): void
    {
        $listing = $this->listing();
        $subscription = $this->subscription($listing);
        $objectRepository = $this->createMock(EntityRepository::class);
        $objectRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['listing' => $listing, 'email' => 'subscriber@example.com'])
            ->willReturn($subscription);
        $entityManager = $this->createEntityManagerWithRepository(Subscription::class, $objectRepository);

        $repository = new DoctrineSubscriptionRepository($entityManager);

        self::assertSame($subscription, $repository->findByListingAndEmail($listing, 'Subscriber@Example.com'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSubscriptionRepositoryFindsByConfirmationTokenRegardlessOfStatus(): void
    {
        $listing = $this->listing();
        $subscription = $this->subscription($listing);
        $objectRepository = $this->createMock(EntityRepository::class);
        $objectRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['confirmationToken' => 'token'])
            ->willReturn($subscription);
        $entityManager = $this->createEntityManagerWithRepository(Subscription::class, $objectRepository);

        $repository = new DoctrineSubscriptionRepository($entityManager);

        self::assertSame($subscription, $repository->findByConfirmationToken('token'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSubscriptionRepositoryFindsActiveByListing(): void
    {
        $listing = $this->listing();
        $subscription = $this->subscription($listing);
        $objectRepository = $this->createMock(EntityRepository::class);
        $objectRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['listing' => $listing, 'status' => 'active'])
            ->willReturn([$subscription]);
        $entityManager = $this->createEntityManagerWithRepository(Subscription::class, $objectRepository);

        $repository = new DoctrineSubscriptionRepository($entityManager);

        self::assertSame([$subscription], $repository->findActiveByListing($listing));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSubscriptionRepositoryPersistsAndFlushes(): void
    {
        $subscription = $this->subscription($this->listing());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($subscription);
        $entityManager->expects(self::once())->method('flush');

        (new DoctrineSubscriptionRepository($entityManager))->save($subscription);
    }

    public function testPriceHistoryRepositoryPersistsAndFlushes(): void
    {
        $listing = $this->listing();
        $history = new PriceHistory($listing, 1200, 900, 'UAH', 'test', new DateTimeImmutable());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($history);
        $entityManager->expects(self::once())->method('flush');

        (new DoctrinePriceHistoryRepository($entityManager))->save($history);
    }

    /**
     * @param class-string $className
     * @param EntityRepository<object> $objectRepository
     */
    private function createEntityManagerWithRepository(
        string $className,
        EntityRepository $objectRepository,
    ): EntityManagerInterface {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with($className)
            ->willReturn($objectRepository);

        return $entityManager;
    }

    private function listing(): Listing
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');

        return new Listing(
            'https://olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'https://www.olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'abc123',
            $now,
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    private function subscription(Listing $listing): Subscription
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');

        return new Subscription($listing, 'subscriber@example.com', 'token', $now->modify('+1 hour'), $now);
    }
}
