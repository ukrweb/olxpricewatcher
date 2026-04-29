<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\PriceTracking\PriceHistoryRepositoryInterface;
use App\Domain\Price\PriceHistory;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePriceHistoryRepository implements PriceHistoryRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(PriceHistory $history): void
    {
        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
