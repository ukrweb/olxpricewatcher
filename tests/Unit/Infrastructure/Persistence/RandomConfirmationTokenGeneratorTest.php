<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Persistence\RandomConfirmationTokenGenerator;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class RandomConfirmationTokenGeneratorTest extends TestCase
{
    /**
     * @throws RandomException
     */
    public function testGeneratesHexToken(): void
    {
        $token = (new RandomConfirmationTokenGenerator())->generate();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }
}
