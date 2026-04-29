<?php

declare(strict_types=1);

namespace App\Application\Subscription;

final class ConfirmSubscriptionCommand
{
    public function __construct(public string $token)
    {
    }
}
