<?php

declare(strict_types=1);

namespace App\UI\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class OpenApiController
{
    public function __construct(private StaticFileResponseFactory $staticFiles)
    {
    }

    #[Route('/openapi.yaml', name: 'openapi_yaml', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->staticFiles->response(
            'public/openapi.yaml',
            'application/yaml; charset=UTF-8',
            'OpenAPI YAML not found',
        );
    }
}
