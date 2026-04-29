<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\PriceTracking;

use App\Application\Notification\NotifierInterface;
use App\Application\PriceTracking\CheckPricesCommand;
use App\Application\PriceTracking\CheckPricesHandler;
use App\Application\PriceTracking\PriceChangeDetector;
use App\Application\PriceTracking\PriceHistoryRepositoryInterface;
use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingStatus;
use App\Domain\Listing\ListingRepositoryInterface;
use App\Domain\Price\PriceFetchException;
use App\Domain\Price\PriceFetchResult;
use App\Domain\Price\PriceFetcherInterface;
use App\Domain\Price\PriceHistory;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use App\Shared\ClockInterface;
use App\Shared\SleeperInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Random\RandomException;
use RuntimeException;

final class CheckPricesHandlerTest extends TestCase
{
    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testPriceChangeDetectionWritesHistoryAndNotifiesActiveSubscribers(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $listing->markChecked(1000, 'UAH', 'Chair', null, $clock->now(), $clock->now());
        $active = new Subscription(
            $listing,
            'active@example.com',
            'active',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $active->confirm($clock->now());
        $pending = new Subscription(
            $listing,
            'pending@example.com',
            'pending',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );

        $notifier = new RecordingNotifier();
        $history = new InMemoryPriceHistoryRepository();

        $handler = new CheckPricesHandler(
            new SingleListingRepository($listing),
            new SubscriptionRepository([$active, $pending]),
            $history,
            new StaticPriceFetcher(PriceFetchResult::found(900, 'UAH', 'Chair', 'json_ld')),
            new PriceChangeDetector(),
            $notifier,
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        self::assertSame(1, $handler(new CheckPricesCommand()));
        self::assertCount(1, $history->history);
        self::assertSame([1], $notifier->priceChangeSubscriberCounts);
        self::assertSame(900, $listing->getCurrentPrice());
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testNoNotificationWhenPriceDoesNotChange(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $listing->markChecked(1000, 'UAH', 'Chair', null, $clock->now(), $clock->now());
        $active = new Subscription(
            $listing,
            'active@example.com',
            'active',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $active->confirm($clock->now());

        $notifier = new RecordingNotifier();
        $history = new InMemoryPriceHistoryRepository();

        $handler = new CheckPricesHandler(
            new SingleListingRepository($listing),
            new SubscriptionRepository([$active]),
            $history,
            new StaticPriceFetcher(PriceFetchResult::found(1000, 'UAH', 'Chair', 'json_ld')),
            new PriceChangeDetector(),
            $notifier,
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        $handler(new CheckPricesCommand());

        self::assertCount(0, $history->history);
        self::assertSame([], $notifier->priceChangeSubscriberCounts);
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testChecksOnlyListingsReturnedByRepository(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $due = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $due->markChecked(1000, 'UAH', 'Due', null, $clock->now(), $clock->now());
        $notDue = new Listing('https://olx.ua/a-ID2.html', 'https://www.olx.ua/a-ID2.html', '2', $clock->now());
        $notDue->markChecked(2000, 'UAH', 'Not due', null, $clock->now(), $clock->now());

        $fetcher = new RecordingPriceFetcher([PriceFetchResult::found(900, 'UAH', 'Due', 'json_ld')]);
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$due]),
            new SubscriptionRepository([]),
            new InMemoryPriceHistoryRepository(),
            $fetcher,
            new PriceChangeDetector(),
            new RecordingNotifier(),
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        self::assertSame(1, $handler(new CheckPricesCommand()));
        self::assertSame(['https://www.olx.ua/a-ID1.html'], $fetcher->urls);
        self::assertSame(2000, $notDue->getCurrentPrice());
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testSleepsBetweenMultipleListingChecksWithoutSleepingAfterLastListing(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $first = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $second = new Listing('https://olx.ua/a-ID2.html', 'https://www.olx.ua/a-ID2.html', '2', $clock->now());
        $sleeper = new NullSleeper();
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$first, $second]),
            new SubscriptionRepository([]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([
                PriceFetchResult::found(1000, 'UAH', 'First', 'json_ld'),
                PriceFetchResult::found(2000, 'UAH', 'Second', 'json_ld'),
            ]),
            new PriceChangeDetector(),
            new RecordingNotifier(),
            $clock,
            $sleeper,
            new NullLogger(),
            300,
            20,
        );

        self::assertSame(2, $handler(new CheckPricesCommand()));
        self::assertCount(1, $sleeper->seconds);
        self::assertGreaterThanOrEqual(1, $sleeper->seconds[0]);
        self::assertLessThanOrEqual(5, $sleeper->seconds[0]);
    }


    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testParseErrorUpdatesListingAndContinues(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $first = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $first->markChecked(1000, 'UAH', 'First', null, $clock->now(), $clock->now());
        $second = new Listing('https://olx.ua/a-ID2.html', 'https://www.olx.ua/a-ID2.html', '2', $clock->now());
        $second->markChecked(2000, 'UAH', 'Second', null, $clock->now(), $clock->now());

        $history = new InMemoryPriceHistoryRepository();
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$first, $second]),
            new SubscriptionRepository([]),
            $history,
            new RecordingPriceFetcher([
                new PriceFetchException('parser failed'),
                PriceFetchResult::found(1900, 'UAH', 'Second', 'json_ld'),
            ]),
            new PriceChangeDetector(),
            new RecordingNotifier(),
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        self::assertSame(2, $handler(new CheckPricesCommand()));
        self::assertSame(ListingStatus::ParseError, $first->getStatus());
        self::assertSame(1900, $second->getCurrentPrice());
        self::assertCount(1, $history->history);
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testNotFoundErrorMapsToListingNotFoundStatus(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());

        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$listing]),
            new SubscriptionRepository([]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([new PriceFetchException('gone', ListingStatus::NotFound)]),
            new PriceChangeDetector(),
            new RecordingNotifier(),
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        $handler(new CheckPricesCommand());

        self::assertSame(ListingStatus::NotFound, $listing->getStatus());
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testNotFoundIncrementsNotFoundCounterOnly(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$listing]),
            new SubscriptionRepository([]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([new PriceFetchException('gone', ListingStatus::NotFound)]),
            new PriceChangeDetector(),
            new RecordingNotifier(),
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        $handler(new CheckPricesCommand());

        self::assertSame(1, $listing->getConsecutiveNotFoundCount());
        self::assertSame(0, $listing->getConsecutiveFetchErrorCount());
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testFetchErrorIncrementsFetchErrorCounterOnly(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$listing]),
            new SubscriptionRepository([]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([new PriceFetchException('timeout')]),
            new PriceChangeDetector(),
            new RecordingNotifier(),
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        $handler(new CheckPricesCommand());

        self::assertSame(0, $listing->getConsecutiveNotFoundCount());
        self::assertSame(1, $listing->getConsecutiveFetchErrorCount());
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testSuccessfulFetchResetsCountersAndUnavailableNotificationMarker(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $listing->markNotFound('gone', $clock->now(), $clock->now());
        $listing->markFetchError('timeout', $clock->now(), $clock->now());
        $listing->markUnavailableNotified($clock->now());
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$listing]),
            new SubscriptionRepository([]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([PriceFetchResult::found(1000, 'UAH', 'Chair', 'json_ld')]),
            new PriceChangeDetector(),
            new RecordingNotifier(),
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        $handler(new CheckPricesCommand());

        self::assertSame(0, $listing->getConsecutiveNotFoundCount());
        self::assertSame(0, $listing->getConsecutiveFetchErrorCount());
        self::assertNull($listing->getUnavailableNotifiedAt());
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testUnavailableEmailIsNotSentBelowThreshold(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $active = new Subscription(
            $listing,
            'active@example.com',
            'active',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $active->confirm($clock->now());
        $notifier = new RecordingNotifier();
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$listing]),
            new SubscriptionRepository([$active]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([new PriceFetchException('gone', ListingStatus::NotFound)]),
            new PriceChangeDetector(),
            $notifier,
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            2,
        );

        $handler(new CheckPricesCommand());

        self::assertSame([], $notifier->unavailableSubscriberCounts);
        self::assertNull($listing->getUnavailableNotifiedAt());
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testUnavailableEmailIsSentAtThresholdOnlyToActiveSubscribers(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $listing->markNotFound('gone once', $clock->now(), $clock->now());
        $active = new Subscription(
            $listing,
            'active@example.com',
            'active',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $active->confirm($clock->now());
        $pending = new Subscription(
            $listing,
            'pending@example.com',
            'pending',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $notifier = new RecordingNotifier();
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$listing]),
            new SubscriptionRepository([$active, $pending]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([new PriceFetchException('gone twice', ListingStatus::NotFound)]),
            new PriceChangeDetector(),
            $notifier,
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            2,
        );

        $handler(new CheckPricesCommand());

        self::assertSame([1], $notifier->unavailableSubscriberCounts);
        self::assertSame($clock->now(), $listing->getUnavailableNotifiedAt());
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testUnavailableEmailIsSentOnlyOnceWhileMarked(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $listing->markNotFound('gone once', $clock->now(), $clock->now());
        $listing->markUnavailableNotified($clock->now());
        $active = new Subscription(
            $listing,
            'active@example.com',
            'active',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $active->confirm($clock->now());
        $notifier = new RecordingNotifier();
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$listing]),
            new SubscriptionRepository([$active]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([new PriceFetchException('gone twice', ListingStatus::NotFound)]),
            new PriceChangeDetector(),
            $notifier,
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            2,
        );

        $handler(new CheckPricesCommand());

        self::assertSame([], $notifier->unavailableSubscriberCounts);
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testHttp410NotFoundAtThresholdNotifiesOnceAndSetsListingNotFound(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        for ($i = 0; $i < 19; $i++) {
            $listing->markNotFound('OLX listing returned HTTP 410.', $clock->now(), $clock->now());
        }
        $active = new Subscription(
            $listing,
            'active@example.com',
            'active',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $active->confirm($clock->now());
        $notifier = new RecordingNotifier();
        $repository = new MultipleListingRepository([$listing]);
        $subscriptions = new SubscriptionRepository([$active]);

        $handler = new CheckPricesHandler(
            $repository,
            $subscriptions,
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([
                new PriceFetchException('OLX listing returned HTTP 410.', ListingStatus::NotFound),
            ]),
            new PriceChangeDetector(),
            $notifier,
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        $handler(new CheckPricesCommand());

        self::assertSame(ListingStatus::NotFound, $listing->getStatus());
        self::assertSame(20, $listing->getConsecutiveNotFoundCount());
        self::assertSame(0, $listing->getConsecutiveFetchErrorCount());
        self::assertSame([1], $notifier->unavailableSubscriberCounts);
        self::assertSame($clock->now(), $listing->getUnavailableNotifiedAt());

        $secondHandler = new CheckPricesHandler(
            $repository,
            $subscriptions,
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([
                new PriceFetchException('OLX listing returned HTTP 410.', ListingStatus::NotFound),
            ]),
            new PriceChangeDetector(),
            $notifier,
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            20,
        );

        $secondHandler(new CheckPricesCommand());

        self::assertSame(21, $listing->getConsecutiveNotFoundCount());
        self::assertSame(0, $listing->getConsecutiveFetchErrorCount());
        self::assertSame([1], $notifier->unavailableSubscriberCounts);
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function testNoUnavailableEmailForNoPriceOrNetworkError(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $noPrice = new Listing('https://olx.ua/a-ID1.html', 'https://www.olx.ua/a-ID1.html', '1', $clock->now());
        $fetchError = new Listing('https://olx.ua/a-ID2.html', 'https://www.olx.ua/a-ID2.html', '2', $clock->now());
        $activeNoPrice = new Subscription(
            $noPrice,
            'active1@example.com',
            'active1',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $activeNoPrice->confirm($clock->now());
        $activeFetchError = new Subscription(
            $fetchError,
            'active2@example.com',
            'active2',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $activeFetchError->confirm($clock->now());
        $notifier = new RecordingNotifier();
        $handler = new CheckPricesHandler(
            new MultipleListingRepository([$noPrice, $fetchError]),
            new SubscriptionRepository([$activeNoPrice, $activeFetchError]),
            new InMemoryPriceHistoryRepository(),
            new RecordingPriceFetcher([
                PriceFetchResult::notFound('test', 'No price found.'),
                new PriceFetchException('timeout'),
            ]),
            new PriceChangeDetector(),
            $notifier,
            $clock,
            new NullSleeper(),
            new NullLogger(),
            300,
            1,
        );

        $handler(new CheckPricesCommand());

        self::assertSame([], $notifier->unavailableSubscriberCounts);
        self::assertSame(ListingStatus::NoPrice, $noPrice->getStatus());
        self::assertSame(ListingStatus::ParseError, $fetchError->getStatus());
    }
}

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final readonly class StaticPriceFetcher implements PriceFetcherInterface
{
    public function __construct(private PriceFetchResult $result)
    {
    }

    public function fetch(string $url): PriceFetchResult
    {
        return $this->result;
    }
}

final class RecordingPriceFetcher implements PriceFetcherInterface
{
    /** @var list<PriceFetchResult|PriceFetchException> */
    private array $results;

    /** @var list<string> */
    public array $urls = [];

    /** @param list<PriceFetchResult|PriceFetchException> $results */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function fetch(string $url): PriceFetchResult
    {
        $this->urls[] = $url;
        $result = array_shift($this->results);

        if ($result instanceof PriceFetchException) {
            throw $result;
        }

        if (!$result instanceof PriceFetchResult) {
            throw new RuntimeException('No fetch result configured.');
        }

        return $result;
    }
}

final readonly class SingleListingRepository implements ListingRepositoryInterface
{
    public function __construct(private Listing $listing)
    {
    }

    public function findByNormalizedUrl(string $normalizedUrl): ?Listing
    {
        return $this->listing->getNormalizedUrl() === $normalizedUrl ? $this->listing : null;
    }

    public function findDueWithActiveSubscriptions(DateTimeImmutable $now): array
    {
        return [$this->listing];
    }

    public function save(Listing $listing): void
    {
    }
}

final readonly class MultipleListingRepository implements ListingRepositoryInterface
{
    /** @param list<Listing> $listings */
    public function __construct(private array $listings)
    {
    }

    public function findByNormalizedUrl(string $normalizedUrl): ?Listing
    {
        foreach ($this->listings as $listing) {
            if ($listing->getNormalizedUrl() === $normalizedUrl) {
                return $listing;
            }
        }

        return null;
    }

    public function findDueWithActiveSubscriptions(DateTimeImmutable $now): array
    {
        return $this->listings;
    }

    public function save(Listing $listing): void
    {
    }
}

final readonly class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    /** @param list<Subscription> $subscriptions */
    public function __construct(private array $subscriptions)
    {
    }

    public function findByListingAndEmail(Listing $listing, string $email): ?Subscription
    {
        return null;
    }

    public function findByConfirmationToken(string $token): ?Subscription
    {
        return null;
    }

    public function findLatestEmailSentAtByEmail(string $email): ?DateTimeImmutable
    {
        return null;
    }

    public function findActiveByListing(Listing $listing): array
    {
        return array_values(array_filter(
            $this->subscriptions,
            static fn (Subscription $subscription): bool => $subscription->getListing() === $listing
                && $subscription->isActive(),
        ));
    }

    public function save(Subscription $subscription): void
    {
    }
}

final class InMemoryPriceHistoryRepository implements PriceHistoryRepositoryInterface
{
    /** @var list<PriceHistory> */
    public array $history = [];

    public function save(PriceHistory $history): void
    {
        $this->history[] = $history;
    }
}

final class RecordingNotifier implements NotifierInterface
{
    /** @var list<int> */
    public array $priceChangeSubscriberCounts = [];

    /** @var list<int> */
    public array $unavailableSubscriberCounts = [];

    public function sendSubscriptionConfirmation(Subscription $subscription): void
    {
    }

    public function sendPriceChanged(
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
        array $subscriptions,
    ): void {
        $this->priceChangeSubscriberCounts[] = count($subscriptions);
    }

    public function sendListingUnavailable(Listing $listing, array $subscriptions): void
    {
        $this->unavailableSubscriberCounts[] = count($subscriptions);
    }
}

final class NullSleeper implements SleeperInterface
{
    /** @var list<int> */
    public array $seconds = [];

    public function sleep(int $seconds): void
    {
        $this->seconds[] = $seconds;
    }
}
