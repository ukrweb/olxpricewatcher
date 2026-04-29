<?php

declare(strict_types=1);

namespace App\UI\Http;

use App\Application\Subscription\ConfirmSubscriptionCommand;
use App\Application\Subscription\ConfirmSubscriptionHandler;
use App\Application\Subscription\ConfirmationTokenExpiredException;
use App\Application\Subscription\ConfirmationTokenNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class ConfirmSubscriptionController
{
    public function __construct(
        private ConfirmSubscriptionHandler $handler,
        private JsonApiResponseFactory $responses,
    ) {
    }

    /**
     * Confirms a pending subscription by token.
     *
     * Path parameter: `token` is the stored confirmation token. Responses: `200` when activated or already
     * confirmed, `404` for unknown tokens, and `410` for expired pending tokens.
     */
    #[Route('/api/subscriptions/confirm/{token}', name: 'api_subscriptions_confirm', methods: ['GET'])]
    public function __invoke(string $token): JsonResponse
    {
        try {
            $result = ($this->handler)(new ConfirmSubscriptionCommand($token));
        } catch (ConfirmationTokenNotFoundException $exception) {
            return $this->responses->error($exception->getMessage(), 404);
        } catch (ConfirmationTokenExpiredException $exception) {
            return $this->responses->error($exception->getMessage(), 410);
        } catch (Throwable) {
            return $this->responses->error('Unexpected server error.', 500);
        }

        return $this->responses->message($result->status, $result->message);
    }
}
