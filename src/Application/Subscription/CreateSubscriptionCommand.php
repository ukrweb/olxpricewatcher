<?php

declare(strict_types=1);

namespace App\Application\Subscription;

final class CreateSubscriptionCommand
{
    public function __construct(
        public string $url,
        public string $email,
    ) {
    }
}
