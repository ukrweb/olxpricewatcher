<?php

declare(strict_types=1);

namespace App\UI\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ApiDocController
{
    public function __construct(private StaticFileResponseFactory $staticFiles)
    {
    }

    #[Route('/api/doc', name: 'api_doc', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->staticFiles->response(
            'public/swagger.html',
            'text/html; charset=UTF-8',
            'Swagger UI not found',
        );
    }
}
