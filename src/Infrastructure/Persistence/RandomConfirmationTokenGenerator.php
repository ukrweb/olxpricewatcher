<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Subscription\ConfirmationTokenGeneratorInterface;
use Random\RandomException;

final class RandomConfirmationTokenGenerator implements ConfirmationTokenGeneratorInterface
{
    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
