<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Subscription;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionStatus;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testConfirmSetsActiveStatus(): void
    {
        $now = new \DateTimeImmutable('2026-04-27 10:00:00');
        $subscription = $this->subscription($now);

        $subscription->confirm($now);

        self::assertSame(SubscriptionStatus::Active, $subscription->getStatus());
        self::assertTrue($subscription->isActive());
    }

    public function testRefreshConfirmationChangesTokenAndExpirationForPendingSubscription(): void
    {
        $now = new \DateTimeImmutable('2026-04-27 10:00:00');
        $subscription = $this->subscription($now);
        $newExpiration = $now->modify('+2 hours');

        $subscription->refreshConfirmation('new-token', $newExpiration, $now);

        self::assertSame('new-token', $subscription->getConfirmationToken());
        self::assertSame($newExpiration, $subscription->getConfirmationTokenExpiresAt());
        self::assertSame(SubscriptionStatus::Pending, $subscription->getStatus());
    }

    public function testRefreshConfirmationDoesNotResetActiveSubscription(): void
    {
        $now = new \DateTimeImmutable('2026-04-27 10:00:00');
        $subscription = $this->subscription($now);
        $subscription->confirm($now);

        $subscription->refreshConfirmation('new-token', $now->modify('+2 hours'), $now);

        self::assertSame('token', $subscription->getConfirmationToken());
        self::assertSame(SubscriptionStatus::Active, $subscription->getStatus());
    }

    public function testMarkNotifiedStoresLastNotifiedPrice(): void
    {
        $now = new \DateTimeImmutable('2026-04-27 10:00:00');
        $subscription = $this->subscription($now);

        $subscription->markNotified(1500, $now);

        $reflection = new \ReflectionClass($subscription);
        $lastNotifiedPrice = $reflection->getProperty('lastNotifiedPrice');
        $lastNotifiedAt = $reflection->getProperty('lastNotifiedAt');

        self::assertSame(1500, $lastNotifiedPrice->getValue($subscription));
        self::assertSame($now, $lastNotifiedAt->getValue($subscription));
    }

    public function testEmailRateLimitUsesLastEmailSentAt(): void
    {
        $now = new \DateTimeImmutable('2026-04-27 10:00:00');
        $subscription = $this->subscription($now);

        self::assertTrue($subscription->canSendEmail($now, 60));

        $subscription->markEmailSent($now);

        self::assertSame($now, $subscription->getLastEmailSentAt());
        self::assertFalse($subscription->canSendEmail($now->modify('+59 seconds'), 60));
        self::assertTrue($subscription->canSendEmail($now->modify('+60 seconds'), 60));
    }

    private function subscription(\DateTimeImmutable $now): Subscription
    {
        $listing = new Listing(
            'https://olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'https://www.olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'abc123',
            $now,
        );

        return new Subscription($listing, 'Subscriber@Example.com', 'token', $now->modify('+1 hour'), $now);
    }
}
