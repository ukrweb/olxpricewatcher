<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

interface ConfirmationTokenGeneratorInterface
{
    public function generate(): string;
}
