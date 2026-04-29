<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';
}
