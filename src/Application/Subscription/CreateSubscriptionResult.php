<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Domain\Subscription\Subscription;

final readonly class CreateSubscriptionResult
{
    public function __construct(
        public ?Subscription $subscription,
        public bool $created,
        public bool $confirmationSent,
        public bool $alreadySubscribed,
        public bool $confirmationThrottled = false,
    ) {
    }
}
