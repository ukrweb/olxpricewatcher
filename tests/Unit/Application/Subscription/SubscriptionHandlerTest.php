<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Subscription;

use App\Application\Notification\EmailRateLimiter;
use App\Application\Notification\NotifierInterface;
use App\Application\Subscription\ConfirmSubscriptionCommand;
use App\Application\Subscription\ConfirmSubscriptionHandler;
use App\Application\Subscription\ConfirmationTokenExpiredException;
use App\Application\Subscription\CreateSubscriptionCommand;
use App\Application\Subscription\CreateSubscriptionHandler;
use App\Application\Subscription\CreateSubscriptionResult;
use App\Application\Subscription\ListingCannotBeTrackedException;
use App\Application\Subscription\ListingNotFoundException;
use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingRepositoryInterface;
use App\Domain\Listing\ListingUrlNormalizer;
use App\Domain\Listing\ListingStatus;
use App\Domain\Price\PriceFetchException;
use App\Domain\Price\PriceFetcherInterface;
use App\Domain\Price\PriceFetchResult;
use App\Domain\Subscription\ConfirmationTokenGeneratorInterface;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use App\Domain\Subscription\SubscriptionStatus;
use App\Shared\ClockInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class SubscriptionHandlerTest extends TestCase
{
    /**
     * @throws DateMalformedStringException
     */
    public function testCreateSubscriptionCreatesListingAndPendingSubscription(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $priceFetcher = new FakePriceFetcher([
            'https://www.olx.ua/d/uk/obyavlenie/example-IDtrack123.html' => PriceFetchResult::found(
                12345,
                'UAH',
                'Test listing',
                'test',
                'abc123',
            ),
        ]);

        $result = (new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            $priceFetcher,
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        ))(new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/example-IDtrack123.html',
            'subscriber@example.com',
        ));
        $subscription = self::requireSubscription($result);
        $listing = $subscription->getListing();

        self::assertCount(1, $listingRepository->listings);
        self::assertCount(1, $subscriptionRepository->subscriptions);
        self::assertSame(SubscriptionStatus::Pending, $subscription->getStatus());
        self::assertSame('token-1', $subscription->getConfirmationToken());
        self::assertSame(12345, $listing->getCurrentPrice());
        self::assertSame('UAH', $listing->getCurrency());
        self::assertSame('Test listing', $listing->getTitle());
        self::assertSame('abc123', $listing->getExternalId());
        self::assertSame(ListingStatus::Active, $listing->getStatus());
        self::assertSame($clock->now(), $listing->getLastCheckedAt());
        self::assertEquals($clock->now()->modify('+300 seconds'), $listing->getNextCheckAt());
        self::assertCount(1, $notifier->confirmations);
        self::assertSame($clock->now(), $subscription->getLastEmailSentAt());
    }

    public function testRejectsNotFoundWithoutSavingRecordsOrSendingEmail(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            new FakePriceFetcher([
                'https://www.olx.ua/d/uk/obyavlenie/missing-ID404.html' => new PriceFetchException(
                    'Listing was not found.',
                    ListingStatus::NotFound,
                ),
            ]),
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );

        $this->expectException(ListingNotFoundException::class);

        try {
            $handler(new CreateSubscriptionCommand(
                'https://m.olx.ua/d/uk/obyavlenie/missing-ID404.html',
                'subscriber@example.com',
            ));
        } catch (DateMalformedStringException $e) {
        } finally {
            self::assertCount(0, $listingRepository->listings);
            self::assertCount(0, $subscriptionRepository->subscriptions);
            self::assertCount(0, $notifier->confirmations);
        }
    }

    public function testRejectsNoPriceWithoutSavingRecordsOrSendingEmail(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            new FakePriceFetcher([
                'https://www.olx.ua/d/uk/obyavlenie/no-price-IDnoprice.html' => PriceFetchResult::notFound(
                    'test',
                    'Unable to extract listing price.',
                ),
            ]),
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );

        $this->expectException(ListingCannotBeTrackedException::class);

        try {
            $handler(new CreateSubscriptionCommand(
                'https://m.olx.ua/d/uk/obyavlenie/no-price-IDnoprice.html',
                'subscriber@example.com',
            ));
        } catch (DateMalformedStringException $e) {
        } finally {
            self::assertCount(0, $listingRepository->listings);
            self::assertCount(0, $subscriptionRepository->subscriptions);
            self::assertCount(0, $notifier->confirmations);
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testDuplicatePendingSubscriptionRefreshesTokenWithoutDuplicates(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $priceFetcher = new FakePriceFetcher([
            'https://www.olx.ua/d/uk/obyavlenie/example-IDtrack123.html' => PriceFetchResult::found(
                12345,
                'UAH',
                'Test listing',
                'test',
                'abc123',
            ),
        ]);
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            $priceFetcher,
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );

        $command = new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/example-IDtrack123.html',
            'User@Example.com',
        );
        $first = self::requireSubscription($handler($command));
        $clock->setNow(new DateTimeImmutable('2026-04-26 10:01:00'));
        $second = self::requireSubscription($handler($command));

        self::assertSame($first, $second);
        self::assertCount(1, $listingRepository->listings);
        self::assertCount(1, $subscriptionRepository->subscriptions);
        self::assertSame('token-2', $second->getConfirmationToken());
        self::assertCount(2, $notifier->confirmations);
        self::assertSame(2, $priceFetcher->calls);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testDuplicatePendingSubscriptionWithinRateLimitDoesNotSendEmailOrCreateDuplicate(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $priceFetcher = new FakePriceFetcher([
            'https://www.olx.ua/d/uk/obyavlenie/example-IDtrack123.html' => PriceFetchResult::found(
                12345,
                'UAH',
                'Test listing',
                'test',
                'abc123',
            ),
        ]);
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            $priceFetcher,
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );
        $command = new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/example-IDtrack123.html',
            'User@Example.com',
        );

        $first = $handler($command);
        $clock->setNow(new DateTimeImmutable('2026-04-26 10:00:59'));
        $second = $handler($command);
        $firstSubscription = self::requireSubscription($first);
        $secondSubscription = self::requireSubscription($second);

        self::assertSame($firstSubscription, $secondSubscription);
        self::assertFalse($second->confirmationSent);
        self::assertTrue($second->confirmationThrottled);
        self::assertSame('token-1', $secondSubscription->getConfirmationToken());
        self::assertCount(1, $subscriptionRepository->subscriptions);
        self::assertCount(1, $notifier->confirmations);
        self::assertSame(1, $priceFetcher->calls);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testDuplicatePendingSubscriptionAfterRateLimitSendsEmail(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $priceFetcher = new FakePriceFetcher([
            'https://www.olx.ua/d/uk/obyavlenie/example-IDtrack123.html' => PriceFetchResult::found(
                12345,
                'UAH',
                'Test listing',
                'test',
                'abc123',
            ),
        ]);
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            $priceFetcher,
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );
        $command = new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/example-IDtrack123.html',
            'User@Example.com',
        );

        $handler($command);
        $clock->setNow(new DateTimeImmutable('2026-04-26 10:01:00'));
        $result = $handler($command);

        self::assertTrue($result->confirmationSent);
        self::assertFalse($result->confirmationThrottled);
        $subscription = self::requireSubscription($result);
        self::assertSame('token-2', $subscription->getConfirmationToken());
        self::assertSame($clock->now(), $subscription->getLastEmailSentAt());
        self::assertCount(2, $notifier->confirmations);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testDuplicateActiveSubscriptionIsNotResetToPending(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $priceFetcher = new FakePriceFetcher([
            'https://www.olx.ua/d/uk/obyavlenie/example-IDtrack123.html' => PriceFetchResult::found(
                12345,
                'UAH',
                'Test listing',
                'test',
                'abc123',
            ),
        ]);
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            $priceFetcher,
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );

        $command = new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/example-IDtrack123.html',
            'subscriber@example.com',
        );
        $subscription = self::requireSubscription($handler($command));
        $subscription->confirm($clock->now());

        $result = $handler($command);
        $sameSubscription = self::requireSubscription($result);

        self::assertSame($subscription, $sameSubscription);
        self::assertSame(SubscriptionStatus::Active, $sameSubscription->getStatus());
        self::assertTrue($result->alreadySubscribed);
        self::assertCount(1, $notifier->confirmations);
        self::assertSame(1, $priceFetcher->calls);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSameEmailDifferentValidListingWithinRateLimitDoesNotCreateNewSubscription(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $priceFetcher = new FakePriceFetcher([
            'https://www.olx.ua/d/uk/obyavlenie/first-IDone123.html' => PriceFetchResult::found(
                100,
                'UAH',
                'First listing',
                'test',
                'one123',
            ),
            'https://www.olx.ua/d/uk/obyavlenie/second-IDtwo456.html' => PriceFetchResult::found(
                200,
                'UAH',
                'Second listing',
                'test',
                'two456',
            ),
        ]);
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            $priceFetcher,
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );

        $first = $handler(new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/first-IDone123.html',
            'User@Example.com',
        ));
        $second = $handler(new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/second-IDtwo456.html',
            'User@Example.com',
        ));

        $firstSubscription = self::requireSubscription($first);

        self::assertNull($second->subscription);
        self::assertSame('user@example.com', $firstSubscription->getEmail());
        self::assertFalse($second->confirmationSent);
        self::assertTrue($second->confirmationThrottled);
        self::assertFalse($second->created);
        self::assertCount(1, $listingRepository->listings);
        self::assertCount(1, $subscriptionRepository->subscriptions);
        self::assertCount(1, $notifier->confirmations);
        self::assertSame(1, $priceFetcher->calls);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testThrottledNewUrlDoesNotCallPriceFetcherOrCreateRecords(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $priceFetcher = new FakePriceFetcher([
            'https://www.olx.ua/d/uk/obyavlenie/first-IDone123.html' => PriceFetchResult::found(
                100,
                'UAH',
                'First listing',
                'test',
                'one123',
            ),
            'https://www.olx.ua/d/uk/obyavlenie/second-IDtwo456.html' => PriceFetchResult::found(
                200,
                'UAH',
                'Second listing',
                'test',
                'two456',
            ),
        ]);
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            $priceFetcher,
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );

        $handler(new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/first-IDone123.html',
            'user@example.com',
        ));
        $clock->setNow(new DateTimeImmutable('2026-04-26 10:00:30'));
        $result = $handler(new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/second-IDtwo456.html',
            'user@example.com',
        ));

        self::assertTrue($result->confirmationThrottled);
        self::assertNull($result->subscription);
        self::assertSame(1, $priceFetcher->calls);
        self::assertSame(['https://www.olx.ua/d/uk/obyavlenie/first-IDone123.html'], $priceFetcher->fetchedUrls);
        self::assertCount(1, $listingRepository->listings);
        self::assertCount(1, $subscriptionRepository->subscriptions);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testNonThrottledValidRequestCallsPriceFetcher(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $priceFetcher = new FakePriceFetcher([
            'https://www.olx.ua/d/uk/obyavlenie/example-IDtrack123.html' => PriceFetchResult::found(
                12345,
                'UAH',
                'Test listing',
                'test',
                'abc123',
            ),
        ]);

        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            $priceFetcher,
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );

        $result = $handler(new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/example-IDtrack123.html',
            'subscriber@example.com',
        ));

        self::assertTrue($result->confirmationSent);
        self::assertSame(1, $priceFetcher->calls);
        self::assertSame(['https://www.olx.ua/d/uk/obyavlenie/example-IDtrack123.html'], $priceFetcher->fetchedUrls);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testDifferentEmailIsNotThrottled(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listingRepository = new InMemoryListingRepository();
        $subscriptionRepository = new InMemorySubscriptionRepository();
        $notifier = new RecordingNotifier();
        $handler = new CreateSubscriptionHandler(
            new ListingUrlNormalizer(),
            $listingRepository,
            $subscriptionRepository,
            new FakePriceFetcher([
                'https://www.olx.ua/d/uk/obyavlenie/first-IDone123.html' => PriceFetchResult::found(
                    100,
                    'UAH',
                    'First listing',
                    'test',
                    'one123',
                ),
                'https://www.olx.ua/d/uk/obyavlenie/second-IDtwo456.html' => PriceFetchResult::found(
                    200,
                    'UAH',
                    'Second listing',
                    'test',
                    'two456',
                ),
            ]),
            new SequentialTokenGenerator(),
            $notifier,
            new EmailRateLimiter($subscriptionRepository, $clock, 60),
            $clock,
            24,
            300,
        );

        $first = $handler(new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/first-IDone123.html',
            'first@example.com',
        ));
        $second = $handler(new CreateSubscriptionCommand(
            'https://m.olx.ua/d/uk/obyavlenie/second-IDtwo456.html',
            'second@example.com',
        ));

        self::assertTrue($first->confirmationSent);
        self::assertTrue($second->confirmationSent);
        self::assertFalse($second->confirmationThrottled);
        self::assertCount(2, $subscriptionRepository->subscriptions);
        self::assertCount(2, $notifier->confirmations);
    }

    private static function requireSubscription(CreateSubscriptionResult $result): Subscription
    {
        if (!$result->subscription instanceof Subscription) {
            self::fail('Expected subscription result to contain a subscription.');
        }

        return $result->subscription;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSubscriptionConfirmation(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing(
            'https://olx.ua/a-ID1.html',
            'https://www.olx.ua/a-ID1.html',
            '1',
            $clock->now(),
        );
        $subscription = new Subscription(
            $listing,
            'subscriber@example.com',
            'token',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $repository = new InMemorySubscriptionRepository([$subscription]);

        $result = (new ConfirmSubscriptionHandler($repository, $clock))(new ConfirmSubscriptionCommand('token'));

        self::assertSame($subscription, $result->subscription);
        self::assertSame('confirmed', $result->status);
        self::assertSame(SubscriptionStatus::Active, $subscription->getStatus());
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testAlreadyActiveTokenReturnsAlreadyConfirmedWithoutChangingSubscription(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing(
            'https://olx.ua/a-ID1.html',
            'https://www.olx.ua/a-ID1.html',
            '1',
            $clock->now(),
        );
        $subscription = new Subscription(
            $listing,
            'subscriber@example.com',
            'token',
            $clock->now()->modify('+1 hour'),
            $clock->now(),
        );
        $subscription->confirm($clock->now());
        $originalExpiration = $subscription->getConfirmationTokenExpiresAt();

        $result = (new ConfirmSubscriptionHandler(new InMemorySubscriptionRepository([$subscription]), $clock))(
            new ConfirmSubscriptionCommand('token'),
        );

        self::assertSame('already_confirmed', $result->status);
        self::assertSame('token', $subscription->getConfirmationToken());
        self::assertSame($originalExpiration, $subscription->getConfirmationTokenExpiresAt());
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testExpiredTokenIsRejected(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-26 10:00:00'));
        $listing = new Listing(
            'https://olx.ua/a-ID1.html',
            'https://www.olx.ua/a-ID1.html',
            '1',
            $clock->now(),
        );
        $subscription = new Subscription(
            $listing,
            'subscriber@example.com',
            'token',
            $clock->now()->modify('-1 hour'),
            $clock->now(),
        );

        $this->expectException(ConfirmationTokenExpiredException::class);

        (new ConfirmSubscriptionHandler(new InMemorySubscriptionRepository([$subscription]), $clock))(
            new ConfirmSubscriptionCommand('token'),
        );
    }
}

final class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function setNow(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}

final class SequentialTokenGenerator implements ConfirmationTokenGeneratorInterface
{
    private int $index = 0;

    public function generate(): string
    {
        return 'token-' . ++$this->index;
    }
}

final class InMemoryListingRepository implements ListingRepositoryInterface
{
    /** @var array<string, Listing> */
    public array $listings = [];

    public function findByNormalizedUrl(string $normalizedUrl): ?Listing
    {
        return $this->listings[$normalizedUrl] ?? null;
    }

    public function findDueWithActiveSubscriptions(DateTimeImmutable $now): array
    {
        return array_values($this->listings);
    }

    public function save(Listing $listing): void
    {
        $this->listings[$listing->getNormalizedUrl()] = $listing;
    }
}

final class FakePriceFetcher implements PriceFetcherInterface
{
    public int $calls = 0;

    /** @var list<string> */
    public array $fetchedUrls = [];

    /** @param array<string, PriceFetchResult|Throwable> $results */
    public function __construct(private readonly array $results)
    {
    }

    /**
     * @throws Throwable
     */
    public function fetch(string $url): PriceFetchResult
    {
        $this->calls++;
        $this->fetchedUrls[] = $url;

        $result = $this->results[$url] ?? null;

        if ($result instanceof Throwable) {
            throw $result;
        }

        if (!$result instanceof PriceFetchResult) {
            throw new RuntimeException('No price fetch result configured.');
        }

        return $result;
    }
}

final class InMemorySubscriptionRepository implements SubscriptionRepositoryInterface
{
    /** @var list<Subscription> */
    public array $subscriptions;

    /** @param list<Subscription> $subscriptions */
    public function __construct(array $subscriptions = [])
    {
        $this->subscriptions = $subscriptions;
    }

    public function findByListingAndEmail(Listing $listing, string $email): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if (
                $subscription->getListing() === $listing
                && $subscription->getEmail() === mb_strtolower($email)
            ) {
                return $subscription;
            }
        }

        return null;
    }

    public function findByConfirmationToken(string $token): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->getConfirmationToken() === $token) {
                return $subscription;
            }
        }

        return null;
    }

    public function findLatestEmailSentAtByEmail(string $email): ?DateTimeImmutable
    {
        $normalizedEmail = mb_strtolower($email);
        $latest = null;

        foreach ($this->subscriptions as $subscription) {
            if ($subscription->getEmail() !== $normalizedEmail) {
                continue;
            }

            $sentAt = $subscription->getLastEmailSentAt();
            if ($sentAt === null) {
                continue;
            }

            if ($latest === null || $sentAt > $latest) {
                $latest = $sentAt;
            }
        }

        return $latest;
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
        if (!in_array($subscription, $this->subscriptions, true)) {
            $this->subscriptions[] = $subscription;
        }
    }
}

final class RecordingNotifier implements NotifierInterface
{
    /** @var list<Subscription> */
    public array $confirmations = [];

    /** @var list<array{listing: Listing, subscribers: int}> */
    public array $priceChanges = [];

    public function sendSubscriptionConfirmation(Subscription $subscription): void
    {
        $this->confirmations[] = $subscription;
    }

    public function sendPriceChanged(
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
        array $subscriptions,
    ): void {
        $this->priceChanges[] = ['listing' => $listing, 'subscribers' => count($subscriptions)];
    }

    public function sendListingUnavailable(Listing $listing, array $subscriptions): void
    {
    }
}
