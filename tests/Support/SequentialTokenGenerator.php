<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Subscription\ConfirmationTokenGeneratorInterface;

final class SequentialTokenGenerator implements ConfirmationTokenGeneratorInterface
{
    private int $index = 0;

    public function generate(): string
    {
        return 'test-token-' . ++$this->index;
    }
}
