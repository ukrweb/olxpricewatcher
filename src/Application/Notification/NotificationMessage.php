<?php

declare(strict_types=1);

namespace App\Application\Notification;

final readonly class NotificationMessage
{
    public function __construct(
        public string $subject,
        public string $body,
    ) {
    }
}
