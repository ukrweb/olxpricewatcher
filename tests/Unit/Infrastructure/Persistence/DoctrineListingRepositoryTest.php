<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence;

use PHPUnit\Framework\TestCase;

final class DoctrineListingRepositoryTest extends TestCase
{
    public function testFindDueWithActiveSubscriptionsFiltersByActiveSubscription(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/src/Infrastructure/Persistence/DoctrineListingRepository.php',
        );

        self::assertStringContainsString("->innerJoin('l.subscriptions', 's')", $source);
        self::assertStringContainsString("->andWhere('s.status = :active')", $source);
        self::assertStringContainsString("->setParameter('active', 'active')", $source);
    }
}
