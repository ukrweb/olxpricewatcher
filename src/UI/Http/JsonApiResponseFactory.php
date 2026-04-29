<?php

declare(strict_types=1);

namespace App\UI\Http;

use App\UI\Http\Dto\ApiMessageResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonApiResponseFactory
{
    /**
     * Builds the standard API error response shape.
     */
    public function error(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse((new ApiMessageResponse('error', $message))->toArray(), $statusCode);
    }

    /**
     * Builds the standard API status/message response shape.
     */
    public function message(string $status, string $message, int $statusCode = 200): JsonResponse
    {
        return new JsonResponse((new ApiMessageResponse($status, $message))->toArray(), $statusCode);
    }
}
