<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http;

use App\Application\Subscription\ConfirmSubscriptionHandler;
use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Tests\Support\InMemorySubscriptionRepository;
use App\Tests\Support\MutableClock;
use App\UI\Http\ApiDocController;
use App\UI\Http\ConfirmSubscriptionController;
use App\UI\Http\Dto\ApiMessageResponse;
use App\UI\Http\JsonApiResponseFactory;
use App\UI\Http\OpenApiController;
use App\UI\Http\StaticFileResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final class ControllerBranchTest extends TestCase
{
    public function testApiDocControllerReturnsServerErrorWhenSwaggerFileIsMissing(): void
    {
        $controller = new ApiDocController($this->staticFilesForProjectDir(sys_get_temp_dir() . '/missing-swagger'));

        $response = $controller();

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('Swagger UI not found', $response->getContent());
    }

    public function testOpenApiControllerReturnsServerErrorWhenYamlFileIsMissing(): void
    {
        $controller = new OpenApiController($this->staticFilesForProjectDir(sys_get_temp_dir() . '/missing-openapi'));

        $response = $controller();

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('OpenAPI YAML not found', $response->getContent());
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testConfirmSubscriptionControllerReturnsSuccessForValidToken(): void
    {
        $clock = new MutableClock();
        $now = $clock->now();
        $listing = new Listing(
            'https://olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'https://www.olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'abc123',
            $now,
        );
        $subscription = new Subscription($listing, 'subscriber@example.com', 'token', $now->modify('+1 hour'), $now);
        $repository = new InMemorySubscriptionRepository();
        $repository->save($subscription);
        $controller = new ConfirmSubscriptionController(
            new ConfirmSubscriptionHandler($repository, $clock),
            new JsonApiResponseFactory(),
        );

        $response = $controller('token');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['status' => 'confirmed', 'message' => 'Subscription confirmed.'],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testApiMessageResponseConvertsToArray(): void
    {
        self::assertSame(
            ['status' => 'ok', 'message' => 'Ready.'],
            (new ApiMessageResponse('ok', 'Ready.'))->toArray(),
        );
    }

    private function kernelForProjectDir(string $projectDir): KernelInterface
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        return $kernel;
    }

    private function staticFilesForProjectDir(string $projectDir): StaticFileResponseFactory
    {
        return new StaticFileResponseFactory($this->kernelForProjectDir($projectDir));
    }
}
