<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Console;

use App\Application\PriceTracking\CheckPricesHandler;
use App\Application\PriceTracking\PriceChangeDetector;
use App\Tests\Support\ConfigurablePriceFetcher;
use App\Tests\Support\InMemoryListingRepository;
use App\Tests\Support\InMemoryPriceHistoryRepository;
use App\Tests\Support\InMemorySubscriptionRepository;
use App\Tests\Support\MutableClock;
use App\Tests\Support\NoOpSleeper;
use App\Tests\Support\RecordingNotifier;
use App\UI\Console\CheckPricesConsoleCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckPricesConsoleCommandTest extends TestCase
{
    public function testExecutePrintsProcessedListingCount(): void
    {
        $handler = new CheckPricesHandler(
            new InMemoryListingRepository(),
            new InMemorySubscriptionRepository(),
            new InMemoryPriceHistoryRepository(),
            new ConfigurablePriceFetcher(),
            new PriceChangeDetector(),
            new RecordingNotifier(),
            new MutableClock(),
            new NoOpSleeper(),
            new NullLogger(),
            300,
            20,
        );
        $tester = new CommandTester(new CheckPricesConsoleCommand($handler));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Processed 0 listing(s).', $tester->getDisplay());
    }
}
