<?php

declare(strict_types=1);

namespace App\Application\PriceTracking;

use App\Application\Notification\NotificationFailedException;
use App\Application\Notification\NotifierInterface;
use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingRepositoryInterface;
use App\Domain\Listing\ListingStatus;
use App\Domain\Price\PriceFetchException;
use App\Domain\Price\PriceFetcherInterface;
use App\Domain\Price\PriceHistory;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use App\Shared\ClockInterface;
use App\Shared\SleeperInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Throwable;

final readonly class CheckPricesHandler
{
    public function __construct(
        private ListingRepositoryInterface $listingRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PriceHistoryRepositoryInterface $priceHistoryRepository,
        private PriceFetcherInterface $priceFetcher,
        private PriceChangeDetector $priceChangeDetector,
        private NotifierInterface $notifier,
        private ClockInterface $clock,
        private SleeperInterface $sleeper,
        private LoggerInterface $logger,
        private int $checkIntervalSeconds,
        private int $unavailableNotificationThreshold,
    ) {
    }

    /**
     * Checks due listings, records price changes, and notifies active subscribers.
     *
     * Returns the number of listings processed in this worker cycle.
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function __invoke(CheckPricesCommand $command): int
    {
        $processed = 0;
        $now = $this->clock->now();
        $listings = $this->listingRepository->findDueWithActiveSubscriptions($now);
        $lastIndex = count($listings) - 1;

        foreach ($listings as $index => $listing) {
            $nextCheckAt = $now->modify(sprintf('+%d seconds', $this->checkIntervalSeconds));
            $this->logger->info('Checking listing price.', [
                'listing_id' => $listing->getId(),
                'url' => $listing->getNormalizedUrl(),
                'status' => $listing->getStatus()->value,
            ]);

            try {
                $result = $this->priceFetcher->fetch($listing->getNormalizedUrl());
                if ($result->price === null) {
                    $listing->markChecked(null, null, $result->title, $result->externalId, $now, $nextCheckAt);
                    $this->logger->warning('Listing price was not found during worker check.', [
                        'listing_id' => $listing->getId(),
                        'url' => $listing->getNormalizedUrl(),
                        'status' => $listing->getStatus()->value,
                    ]);
                    $this->listingRepository->save($listing);
                    $processed++;
                    $this->sleepBetweenListings($index, $lastIndex);
                    continue;
                }

                $oldPrice = $listing->getCurrentPrice();
                if ($this->priceChangeDetector->hasChanged($oldPrice, $result->price->amount)) {
                    $history = new PriceHistory(
                        $listing,
                        $oldPrice,
                        $result->price->amount,
                        $result->price->currency,
                        $result->source,
                        $now,
                    );
                    $this->priceHistoryRepository->save($history);

                    $activeSubscriptions = $this->subscriptionRepository->findActiveByListing($listing);
                    if ($oldPrice !== null && $activeSubscriptions !== []) {
                        try {
                            $this->notifier->sendPriceChanged(
                                $listing,
                                $oldPrice,
                                $result->price->amount,
                                $result->price->currency,
                                $activeSubscriptions,
                            );

                            foreach ($activeSubscriptions as $subscription) {
                                $subscription->markNotified($result->price->amount, $now);
                                $this->subscriptionRepository->save($subscription);
                            }
                        } catch (NotificationFailedException $exception) {
                            $this->logger->error('Price-change notification failed.', [
                                'listing_id' => $listing->getId(),
                                'url' => $listing->getNormalizedUrl(),
                                'exception_class' => $exception::class,
                                'exception_message' => $exception->getMessage(),
                            ]);
                        }
                    }
                }

                $listing->markChecked(
                    $result->price->amount,
                    $result->price->currency,
                    $result->title,
                    $result->externalId,
                    $now,
                    $nextCheckAt,
                );
                $this->logger->info('Listing check completed.', [
                    'listing_id' => $listing->getId(),
                    'url' => $listing->getNormalizedUrl(),
                    'status' => $listing->getStatus()->value,
                    'not_found_count' => $listing->getConsecutiveNotFoundCount(),
                    'fetch_error_count' => $listing->getConsecutiveFetchErrorCount(),
                ]);
                $this->listingRepository->save($listing);
                $processed++;
            } catch (PriceFetchException $exception) {
                if ($exception->listingStatus === ListingStatus::NotFound) {
                    $listing->markNotFound($exception->getMessage(), $now, $nextCheckAt);
                    $this->notifyUnavailableIfNeeded($listing, $now);
                } else {
                    $listing->markFetchError($exception->getMessage(), $now, $nextCheckAt);
                }
                $this->logger->warning('Listing check failed with price fetch exception.', [
                    'listing_id' => $listing->getId(),
                    'url' => $listing->getNormalizedUrl(),
                    'status' => $listing->getStatus()->value,
                    'not_found_count' => $listing->getConsecutiveNotFoundCount(),
                    'fetch_error_count' => $listing->getConsecutiveFetchErrorCount(),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);
                $this->listingRepository->save($listing);
                $processed++;
            } catch (Throwable $exception) {
                $listing->markFetchError($exception->getMessage(), $now, $nextCheckAt);
                $this->logger->error('Unexpected listing check failure.', [
                    'listing_id' => $listing->getId(),
                    'url' => $listing->getNormalizedUrl(),
                    'status' => $listing->getStatus()->value,
                    'not_found_count' => $listing->getConsecutiveNotFoundCount(),
                    'fetch_error_count' => $listing->getConsecutiveFetchErrorCount(),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);
                $this->listingRepository->save($listing);
                $processed++;
            }

            $this->sleepBetweenListings($index, $lastIndex);
        }

        return $processed;
    }

    /**
     * @throws RandomException
     */
    private function sleepBetweenListings(int $index, int $lastIndex): void
    {
        if ($index >= $lastIndex) {
            return;
        }

        $this->sleeper->sleep(random_int(1, 5));
    }

    private function notifyUnavailableIfNeeded(
        Listing $listing,
        DateTimeImmutable $now,
    ): void {
        if ($listing->getUnavailableNotifiedAt() instanceof DateTimeImmutable) {
            return;
        }

        if ($listing->getConsecutiveNotFoundCount() < $this->unavailableNotificationThreshold) {
            return;
        }

        $activeSubscriptions = $this->subscriptionRepository->findActiveByListing($listing);
        if ($activeSubscriptions === []) {
            return;
        }

        try {
            $this->notifier->sendListingUnavailable($listing, $activeSubscriptions);
            $listing->markUnavailableNotified($now);
        } catch (NotificationFailedException $exception) {
            $this->logger->error('Unavailable-listing notification failed.', [
                'listing_id' => $listing->getId(),
                'url' => $listing->getNormalizedUrl(),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        }
    }
}
