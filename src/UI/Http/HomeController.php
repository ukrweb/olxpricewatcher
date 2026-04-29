<?php

declare(strict_types=1);

namespace App\UI\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response(
            <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OLX Price Watcher</title>
</head>
<body>
    <main>
        <h1>OLX Price Watcher</h1>
        <p>A small Symfony service for subscribing to OLX listing price changes by email.</p>
        <nav aria-label="Project links">
            <ul>
                <li><a href="/health">Health</a></li>
                <li><a href="/api/doc">Swagger UI</a></li>
                <li><a href="/openapi.yaml">OpenAPI YAML</a></li>
                <li><a href="http://localhost:8025">Mailpit</a></li>
            </ul>
        </nav>
        <p>See README.md and /doc for full documentation.</p>
    </main>
</body>
</html>
HTML,
            Response::HTTP_OK,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
