<?php

declare(strict_types=1);

namespace App\UI\Http;

use App\Application\Notification\NotificationFailedException;
use App\Application\Subscription\CreateSubscriptionCommand;
use App\Application\Subscription\CreateSubscriptionHandler;
use App\Application\Subscription\InvalidSubscriptionInputException;
use App\Application\Subscription\ListingCannotBeTrackedException;
use App\Application\Subscription\ListingFetchFailedException;
use App\Application\Subscription\ListingNotFoundException;
use App\UI\Http\Dto\CreateSubscriptionRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class CreateSubscriptionController
{
    public function __construct(
        private CreateSubscriptionHandler $handler,
        private JsonApiResponseFactory $responses,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Creates a pending subscription.
     *
     * Request body: `{ "url": "https://m.olx.ua/...", "email": "subscriber@example.com" }`.
     * Responses: `201` when created, `200` for existing subscriptions, `429` when throttled,
     * and JSON errors for invalid/untrackable input.
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
        } catch (NotificationFailedException $exception) {
            $previous = $exception->getPrevious();
            $this->logger->error('Subscription confirmation email failed.', [
                'stage' => 'send_confirmation_email',
                'url' => $subscriptionRequest->url,
                'email' => $subscriptionRequest->email,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'previous_exception_class' => $previous instanceof Throwable ? $previous::class : null,
                'previous_exception_message' => '[redacted]',
            ]);

            return $this->responses->error('Unable to send confirmation email.', 502);
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected subscription creation failure.', [
                'stage' => 'create_subscription',
                'url' => $subscriptionRequest->url,
                'email' => $subscriptionRequest->email,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return $this->responses->error('Unable to create subscription.', 500);
        }

        if ($result->alreadySubscribed) {
            return $this->responses->message('already_subscribed', 'Subscription is already active.');
        }

        if ($result->confirmationThrottled) {
            return $this->responses->message(
                'confirmation_throttled',
                'A confirmation email was recently sent. Please wait before requesting another one.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return $this->responses->message(
            'pending_confirmation',
            $result->created ? 'Subscription created. Please confirm your email.' : 'Confirmation email resent.',
            $result->created ? 201 : 200,
        );
    }
}
