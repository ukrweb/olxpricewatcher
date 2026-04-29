<?php

declare(strict_types=1);

namespace App\UI\Http\Dto;

/**
 * Small JSON response shape used by subscription and health endpoints.
 */
final class ApiMessageResponse
{
    public function __construct(
        public string $status,
        public string $message,
    ) {
    }

    /** @return array{status: string, message: string} */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
