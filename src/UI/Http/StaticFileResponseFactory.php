<?php

declare(strict_types=1);

namespace App\UI\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class StaticFileResponseFactory
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    /**
     * Returns a project-root relative static file response or a plain 500 error when it is missing.
     */
    public function response(
        string $relativePath,
        string $contentType,
        string $missingMessage,
    ): Response {
        $file = $this->kernel->getProjectDir() . '/' . ltrim($relativePath, '/');

        if (!is_file($file)) {
            return new Response($missingMessage, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response(
            (string) file_get_contents($file),
            Response::HTTP_OK,
            ['Content-Type' => $contentType],
        );
    }
}
