<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use App\Domain\Subscription\SubscriptionStatus;
use App\Shared\ClockInterface;

final readonly class ConfirmSubscriptionHandler
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Confirms a pending subscription or reports that the token was already confirmed.
     *
     * @throws ConfirmationTokenNotFoundException When no subscription has the provided token.
     * @throws ConfirmationTokenExpiredException When the pending confirmation token expired.
     */
    public function __invoke(ConfirmSubscriptionCommand $command): ConfirmSubscriptionResult
    {
        $subscription = $this->subscriptionRepository->findByConfirmationToken($command->token);
        if (!$subscription instanceof Subscription) {
            throw new ConfirmationTokenNotFoundException('Confirmation token was not found.');
        }

        if ($subscription->getStatus() === SubscriptionStatus::Active) {
            return new ConfirmSubscriptionResult(
                $subscription,
                'already_confirmed',
                'Subscription is already confirmed.',
            );
        }

        $now = $this->clock->now();
        if ($subscription->getConfirmationTokenExpiresAt() < $now) {
            throw new ConfirmationTokenExpiredException('Confirmation token has expired.');
        }

        $subscription->confirm($now);
        $this->subscriptionRepository->save($subscription);

        return new ConfirmSubscriptionResult($subscription, 'confirmed', 'Subscription confirmed.');
    }
}
