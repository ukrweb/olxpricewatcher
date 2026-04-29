<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Domain\Subscription\Subscription;

final readonly class ConfirmSubscriptionResult
{
    public function __construct(
        public Subscription $subscription,
        public string $status,
        public string $message,
    ) {
    }
}
