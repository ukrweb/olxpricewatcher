<?php

declare(strict_types=1);

namespace App\Application\Notification;

use App\Domain\Subscription\SubscriptionRepositoryInterface;
use App\Shared\ClockInterface;

final readonly class EmailRateLimiter
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private ClockInterface $clock,
        private int $emailRateLimitSeconds,
    ) {
    }

    public function isConfirmationThrottled(string $email): bool
    {
        $latestEmailSentAt = $this->subscriptionRepository->findLatestEmailSentAtByEmail(mb_strtolower(trim($email)));
        if ($latestEmailSentAt === null) {
            return false;
        }

        return $this->clock->now()->getTimestamp() - $latestEmailSentAt->getTimestamp()
            < $this->emailRateLimitSeconds;
    }
}
