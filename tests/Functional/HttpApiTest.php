<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Listing\Listing;
use App\Domain\Listing\ListingRepositoryInterface;
use App\Domain\Listing\ListingStatus;
use App\Domain\Price\PriceFetchException;
use App\Domain\Price\PriceFetcherInterface;
use App\Domain\Subscription\Subscription;
use App\Domain\Subscription\SubscriptionRepositoryInterface;
use App\Tests\Support\ConfigurablePriceFetcher;
use App\Tests\Support\InMemoryListingRepository;
use App\Tests\Support\InMemorySubscriptionRepository;
use App\Tests\Support\MutableClock;
use App\Tests\Support\ThrowingListingRepository;
use App\Tests\Support\ThrowingSubscriptionRepository;
use DateMalformedStringException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HttpApiTest extends WebTestCase
{
    public function testHomePageReturnsProjectName(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('OLX Price Watcher', (string) $client->getResponse()->getContent());
    }

    public function testHealthReturnsOk(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testCreateSubscriptionWithInvalidJsonReturnsBadRequest(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/subscriptions', [], [], ['CONTENT_TYPE' => 'application/json'], '{bad json');

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            ['status' => 'error', 'message' => 'Invalid JSON body.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testCreateSubscriptionWithInvalidEmailReturnsUnprocessableEntity(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/subscriptions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['url' => 'https://m.olx.ua/d/uk/obyavlenie/valid-IDfun123.html', 'email' => 'bad']) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertStringNotContainsString('<html', mb_strtolower((string) $client->getResponse()->getContent()));
    }

    public function testCreateSubscriptionWithInvalidUrlReturnsJsonError(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/subscriptions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['url' => 'https://example.com/not-olx', 'email' => 'subscriber@example.com']) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            ['status' => 'error', 'message' => 'Only olx.ua listing URLs are supported.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testCreateSubscriptionWithValidPayloadReturnsCreated(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/subscriptions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'url' => 'https://m.olx.ua/d/uk/obyavlenie/valid-IDfun123.html',
                'email' => 'functional@example.com',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        self::assertSame(
            'pending_confirmation',
            json_decode((string) $client->getResponse()->getContent(), true)['status'] ?? null,
        );
    }

    public function testCreateSubscriptionAcceptsEmailStartingWithDigit(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/subscriptions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'url' => 'https://m.olx.ua/d/uk/obyavlenie/digit-email-IDdigit123.html',
                'email' => '1subscriber@example.com',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testCreateSubscriptionForUnknownOlxListingDoesNotExposeHtmlError(): void
    {
        $client = self::createClient();
        $priceFetcher = self::getContainer()->get(PriceFetcherInterface::class);
        self::assertInstanceOf(ConfigurablePriceFetcher::class, $priceFetcher);
        $priceFetcher->setResult(
            'https://www.olx.ua/d/uk/obyavlenie/deleted-listing-IDnotfound999.html',
            new PriceFetchException('Listing was not found.', ListingStatus::NotFound),
        );

        $client->request(
            'POST',
            '/api/subscriptions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'url' => 'https://m.olx.ua/d/uk/obyavlenie/deleted-listing-IDnotfound999.html',
                'email' => 'user2@example.com',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            ['status' => 'error', 'message' => 'Listing was not found.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
        self::assertStringNotContainsString('<html', mb_strtolower((string) $client->getResponse()->getContent()));
    }

    public function testCreateSubscriptionUnexpectedExceptionReturnsJsonServerError(): void
    {
        $client = self::createClient();
        self::getContainer()->set(ListingRepositoryInterface::class, new ThrowingListingRepository());

        $client->request(
            'POST',
            '/api/subscriptions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'url' => 'https://m.olx.ua/d/uk/obyavlenie/example-IDstorage500.html',
                'email' => 'user3@example.com',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(500);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            ['status' => 'error', 'message' => 'Unable to create subscription.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testDockerDatabaseConfigurationUsesDatabaseServiceHost(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $envExample = (string) file_get_contents($projectDir . '/.env.example');
        $dockerCompose = (string) file_get_contents($projectDir . '/docker-compose.yml');
        $composeDatabaseUrl = 'DATABASE_URL: "postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}'
            . '@${POSTGRES_HOST:-database}:5432/${POSTGRES_DB}?serverVersion=16&charset=utf8"';

        self::assertStringContainsString(
            'POSTGRES_HOST=database',
            $envExample,
        );
        self::assertStringContainsString(
            'PROJECT_NAME=',
            $envExample,
        );
        self::assertStringNotContainsString('DATABASE_URL=', $envExample);
        self::assertStringNotContainsString('COMPOSE_PROJECT_NAME=', $envExample);
        self::assertStringContainsString(
            $composeDatabaseUrl,
            $dockerCompose,
        );
    }

    public function testWorkerIntervalUsesConfiguredRangeInsteadOfFixedInterval(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $envExample = (string) file_get_contents($projectDir . '/.env.example');
        $dockerCompose = (string) file_get_contents($projectDir . '/docker-compose.yml');
        $workerScript = (string) file_get_contents($projectDir . '/docker/worker/run.sh');

        self::assertStringContainsString('OLX_CHECK_INTERVAL_FROM_SECONDS=300', $envExample);
        self::assertStringContainsString('OLX_CHECK_INTERVAL_TO_SECONDS=600', $envExample);
        self::assertStringContainsString('OLX_CHECK_INTERVAL_FROM_SECONDS', $dockerCompose);
        self::assertStringContainsString('OLX_CHECK_INTERVAL_TO_SECONDS', $dockerCompose);
        self::assertStringContainsString('Invalid worker interval: FROM must be <= TO', $workerScript);
        self::assertStringContainsString('awk -v min="$FROM" -v max="$TO"', $workerScript);
        self::assertStringNotContainsString('OLX_CHECK_INTERVAL_SECONDS', $envExample);
        self::assertStringNotContainsString('OLX_CHECK_INTERVAL_SECONDS', $workerScript);
    }

    public function testMigrationAddsUniqueConfirmationTokenIndex(): void
    {
        $migrationSql = '';
        foreach (glob(dirname(__DIR__, 2) . '/migrations/Version*.php') ?: [] as $migrationFile) {
            $migrationSql .= (string) file_get_contents($migrationFile);
        }

        self::assertStringContainsString(
            'CREATE UNIQUE INDEX uniq_subscriptions_confirmation_token',
            $migrationSql,
        );
    }

    public function testConfirmSubscriptionWithInvalidTokenReturnsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/subscriptions/confirm/invalid-token');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            ['status' => 'error', 'message' => 'Confirmation token was not found.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testConfirmSubscriptionValidTokenThenSecondCallReturnsAlreadyConfirmed(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $email = 'confirm-flow@example.com';

        $client->request(
            'POST',
            '/api/subscriptions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'url' => 'https://m.olx.ua/d/uk/obyavlenie/confirm-flow-IDconfirm123.html',
                'email' => $email,
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $subscription = $this->findSubscriptionByEmail($email);

        $client->request('GET', '/api/subscriptions/confirm/' . $subscription->getConfirmationToken());

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['status' => 'confirmed', 'message' => 'Subscription confirmed.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );

        $client->request('GET', '/api/subscriptions/confirm/' . $subscription->getConfirmationToken());

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['status' => 'already_confirmed', 'message' => 'Subscription is already confirmed.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testConfirmSubscriptionExpiredTokenReturnsGone(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $clock = self::getContainer()->get(MutableClock::class);
        self::assertInstanceOf(MutableClock::class, $clock);
        $now = $clock->now();
        $listingRepository = self::getContainer()->get(InMemoryListingRepository::class);
        self::assertInstanceOf(InMemoryListingRepository::class, $listingRepository);
        $subscriptionRepository = self::getContainer()->get(InMemorySubscriptionRepository::class);
        self::assertInstanceOf(InMemorySubscriptionRepository::class, $subscriptionRepository);
        $listing = new Listing(
            'https://olx.ua/d/uk/obyavlenie/expired-IDexpired123.html',
            'https://www.olx.ua/d/uk/obyavlenie/expired-IDexpired123.html',
            'expired123',
            $now,
        );
        $listingRepository->save($listing);
        $subscriptionRepository->save(new Subscription(
            $listing,
            'expired@example.com',
            'expired-token',
            $now->modify('-1 hour'),
            $now,
        ));

        $client->request('GET', '/api/subscriptions/confirm/expired-token');

        self::assertResponseStatusCodeSame(410);
        self::assertSame(
            ['status' => 'error', 'message' => 'Confirmation token has expired.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testConfirmSubscriptionUnexpectedExceptionReturnsJsonServerError(): void
    {
        $client = self::createClient();
        self::getContainer()->set(SubscriptionRepositoryInterface::class, new ThrowingSubscriptionRepository());

        $client->request('GET', '/api/subscriptions/confirm/storage-error-token');

        self::assertResponseStatusCodeSame(500);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            ['status' => 'error', 'message' => 'Unexpected server error.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testApiDocReturnsSwaggerUi(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('SwaggerUIBundle', (string) $client->getResponse()->getContent());
    }

    public function testOpenApiYamlReturnsSpec(): void
    {
        $client = self::createClient();

        $client->request('GET', '/openapi.yaml');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('openapi:', (string) $client->getResponse()->getContent());
    }

    private function findSubscriptionByEmail(string $email): Subscription
    {
        $subscriptionRepository = self::getContainer()->get(InMemorySubscriptionRepository::class);
        self::assertInstanceOf(InMemorySubscriptionRepository::class, $subscriptionRepository);

        foreach ($subscriptionRepository->subscriptions as $subscription) {
            if ($subscription->getEmail() === $email) {
                return $subscription;
            }
        }

        self::fail(sprintf('Subscription for %s was not found.', $email));
    }
}
