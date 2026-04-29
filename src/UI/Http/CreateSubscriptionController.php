<?php

declare(strict_types=1);

namespace App\UI\Http;

use App\Application\Subscription\CreateSubscriptionCommand;
use App\Application\Subscription\CreateSubscriptionHandler;
use App\Application\Subscription\InvalidSubscriptionInputException;
use App\Application\Subscription\ListingCannotBeTrackedException;
use App\Application\Subscription\ListingFetchFailedException;
use App\Application\Subscription\ListingNotFoundException;
use App\UI\Http\Dto\CreateSubscriptionRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class CreateSubscriptionController
{
    public function __construct(
        private CreateSubscriptionHandler $handler,
        private JsonApiResponseFactory $responses,
    ) {
    }

    /**
     * Creates a pending subscription.
     *
     * Request body: `{ "url": "https://m.olx.ua/...", "email": "subscriber@example.com" }`.
     * Responses: `201` when created, `200` for existing subscriptions, and JSON errors for invalid/untrackable input.
     */
    #[Route('/api/subscriptions', name: 'api_subscriptions_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->responses->error('Invalid JSON body.', 400);
        }

        $subscriptionRequest = CreateSubscriptionRequest::fromArray($payload);

        try {
            $result = ($this->handler)(new CreateSubscriptionCommand(
                $subscriptionRequest->url,
                $subscriptionRequest->email,
            ));
        } catch (InvalidSubscriptionInputException $exception) {
            return $this->responses->error($exception->getMessage(), 422);
        } catch (ListingNotFoundException $exception) {
            return $this->responses->error($exception->getMessage(), 404);
        } catch (ListingFetchFailedException $exception) {
            return $this->responses->error($exception->getMessage(), 502);
        } catch (ListingCannotBeTrackedException $exception) {
            return $this->responses->error($exception->getMessage(), 422);
        } catch (Throwable) {
            return $this->responses->error('Unable to create subscription.', 500);
        }

        if ($result->alreadySubscribed) {
            return $this->responses->message('already_subscribed', 'Subscription is already active.');
        }

        return $this->responses->message(
            'pending_confirmation',
            $result->created ? 'Subscription created. Please confirm your email.' : 'Confirmation email resent.',
            $result->created ? 201 : 200,
        );
    }
}
