<?php

declare(strict_types=1);

namespace App\Application\Subscription;

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

        $now = $this->clock->now();
        $nextCheckAt = $now->modify(sprintf('+%d seconds', $this->checkIntervalSeconds));

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

        $listing = $this->listingRepository->findByNormalizedUrl($normalized->normalizedUrl);
        if (!$listing instanceof Listing) {
            $listing = new Listing($normalized->originalUrl, $normalized->normalizedUrl, $normalized->externalId, $now);
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

        $email = mb_strtolower(trim($command->email));
        $expiresAt = $now->modify(sprintf('+%d hours', $this->confirmationTtlHours));
        $subscription = $this->subscriptionRepository->findByListingAndEmail($listing, $email);

        $sendConfirmation = true;
        $alreadySubscribed = false;
        $created = false;
        if (!$subscription instanceof Subscription) {
            $subscription = new Subscription($listing, $email, $this->tokenGenerator->generate(), $expiresAt, $now);
            $created = true;
        } elseif ($subscription->getStatus() === SubscriptionStatus::Active) {
            $sendConfirmation = false;
            $alreadySubscribed = true;
        } else {
            $subscription->refreshConfirmation($this->tokenGenerator->generate(), $expiresAt, $now);
        }

        $this->subscriptionRepository->save($subscription);
        if ($sendConfirmation) {
            $this->notifier->sendSubscriptionConfirmation($subscription);
        }

        return new CreateSubscriptionResult($subscription, $created, $sendConfirmation, $alreadySubscribed);
    }
}
