<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Application\Notification\EmailRateLimiter;
use App\Application\Notification\NotifierInterface;
use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingRepositoryInterface;
use App\Domain\Listing\ListingUrlNormalizer;
use App\Domain\Listing\ListingStatus;
use App\Domain\Price\PriceFetchException;
use App\Domain\Price\PriceFetcherInterface;
use App\Domain\Subscription\ConfirmationTokenGeneratorInterface;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use App\Domain\Subscription\SubscriptionStatus;
use App\Shared\ClockInterface;
use DateMalformedStringException;
use InvalidArgumentException;
use Throwable;

final readonly class CreateSubscriptionHandler
{
    public function __construct(
        private ListingUrlNormalizer $urlNormalizer,
        private ListingRepositoryInterface $listingRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PriceFetcherInterface $priceFetcher,
        private ConfirmationTokenGeneratorInterface $tokenGenerator,
        private NotifierInterface $notifier,
        private EmailRateLimiter $emailRateLimiter,
        private ClockInterface $clock,
        private int $confirmationTtlHours,
        private int $checkIntervalSeconds,
    ) {
    }

    /**
     * Creates or reuses a subscription after validating the OLX URL can be tracked.
     *
     * @throws InvalidSubscriptionInputException When email or URL validation fails.
     * @throws ListingNotFoundException When OLX reports that the listing does not exist.
     * @throws ListingFetchFailedException When the initial OLX fetch fails.
     * @throws ListingCannotBeTrackedException|DateMalformedStringException When no current price can be extracted.
     */
    public function __invoke(CreateSubscriptionCommand $command): CreateSubscriptionResult
    {
        if (filter_var($command->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidSubscriptionInputException('A valid email address is required.');
        }

        try {
            $normalized = $this->urlNormalizer->normalize($command->url);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidSubscriptionInputException($exception->getMessage(), 0, $exception);
        }

        $email = mb_strtolower(trim($command->email));
        $now = $this->clock->now();
        $nextCheckAt = $now->modify(sprintf('+%d seconds', $this->checkIntervalSeconds));
        $listing = $this->listingRepository->findByNormalizedUrl($normalized->normalizedUrl);
        $subscription = $listing instanceof Listing
            ? $this->subscriptionRepository->findByListingAndEmail($listing, $email)
            : null;

        if ($subscription instanceof Subscription && $subscription->getStatus() === SubscriptionStatus::Active) {
            return new CreateSubscriptionResult($subscription, false, false, true, false);
        }

        $recipientThrottled = $this->emailRateLimiter->isConfirmationThrottled($email);
        if ($recipientThrottled) {
            return new CreateSubscriptionResult($subscription, false, false, false, true);
        }

        try {
            $priceResult = $this->priceFetcher->fetch($normalized->normalizedUrl);
        } catch (PriceFetchException $exception) {
            if ($exception->listingStatus === ListingStatus::NotFound) {
                throw new ListingNotFoundException($exception->getMessage(), 0, $exception);
            }

            throw new ListingFetchFailedException($exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            throw new ListingFetchFailedException('Unable to fetch OLX listing price.', 0, $exception);
        }

        if ($priceResult->price === null) {
            throw new ListingCannotBeTrackedException($priceResult->error ?? 'Unable to extract listing price.');
        }

        $expiresAt = $now->modify(sprintf('+%d hours', $this->confirmationTtlHours));
        $created = false;
        if (!$subscription instanceof Subscription) {
            if (!$listing instanceof Listing) {
                $listing = new Listing(
                    $normalized->originalUrl,
                    $normalized->normalizedUrl,
                    $normalized->externalId,
                    $now,
                );
            }

            $subscription = new Subscription($listing, $email, $this->tokenGenerator->generate(), $expiresAt, $now);
            $created = true;
        } else {
            $subscription->refreshConfirmation($this->tokenGenerator->generate(), $expiresAt, $now);
        }

        $listing->markChecked(
            $priceResult->price->amount,
            $priceResult->price->currency,
            $priceResult->title,
            $priceResult->externalId ?? $normalized->externalId,
            $now,
            $nextCheckAt,
        );
        $this->listingRepository->save($listing);

        $this->subscriptionRepository->save($subscription);
        $this->notifier->sendSubscriptionConfirmation($subscription);
        $subscription->markEmailSent($now);
        $this->subscriptionRepository->save($subscription);

        return new CreateSubscriptionResult(
            $subscription,
            $created,
            true,
            false,
            false,
        );
    }
}
