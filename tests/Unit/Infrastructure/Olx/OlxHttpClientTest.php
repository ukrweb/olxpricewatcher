<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Olx;

use App\Domain\Listing\ListingStatus;
use App\Domain\Price\PriceFetchException;
use App\Infrastructure\Olx\OlxHttpClient;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class OlxHttpClientTest extends TestCase
{
    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function testReturnsResponseBodyAndSendsConfiguredHeaders(): void
    {
        $httpClient = new MockHttpClient(function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            self::assertSame('GET', $method);
            self::assertSame('https://www.olx.ua/listing-IDabc123.html', $url);
            self::assertSame(7.0, $options['timeout']);
            $headers = implode("\n", $options['headers']);
            self::assertStringContainsString('User-Agent: test-agent', $headers);
            self::assertStringContainsString('Accept: text/html,application/xhtml+xml', $headers);

            return new MockResponse('<html>ok</html>', ['http_code' => 200]);
        });

        $client = new OlxHttpClient($httpClient, 7, 'test-agent', new NullLogger());

        self::assertSame('<html>ok</html>', $client->get('https://www.olx.ua/listing-IDabc123.html'));
    }

    public function testHttp404MapsToListingNotFound(): void
    {
        $client = new OlxHttpClient(
            new MockHttpClient(new MockResponse('missing', ['http_code' => 404])),
            5,
            'test-agent',
            new NullLogger(),
        );

        try {
            $client->get('https://www.olx.ua/missing-IDabc123.html');
            self::fail('Expected PriceFetchException.');
        } catch (PriceFetchException $exception) {
            self::assertSame(ListingStatus::NotFound, $exception->listingStatus);
            self::assertSame('OLX listing returned HTTP 404.', $exception->getMessage());
        } catch (ClientExceptionInterface $e) {
        } catch (RedirectionExceptionInterface $e) {
        } catch (ServerExceptionInterface $e) {
        }
    }

    public function testHttp410MapsToListingNotFound(): void
    {
        $client = new OlxHttpClient(
            new MockHttpClient(new MockResponse('gone', ['http_code' => 410])),
            5,
            'test-agent',
            new NullLogger(),
        );

        try {
            $client->get('https://www.olx.ua/gone-IDabc123.html');
            self::fail('Expected PriceFetchException.');
        } catch (PriceFetchException $exception) {
            self::assertSame(ListingStatus::NotFound, $exception->listingStatus);
            self::assertSame('OLX listing returned HTTP 410.', $exception->getMessage());
        } catch (ClientExceptionInterface $e) {
        } catch (RedirectionExceptionInterface $e) {
        } catch (ServerExceptionInterface $e) {
        }
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function testNonSuccessfulHttpStatusThrowsFetchException(): void
    {
        $client = new OlxHttpClient(
            new MockHttpClient(new MockResponse('error', ['http_code' => 503])),
            5,
            'test-agent',
            new NullLogger(),
        );

        $this->expectException(PriceFetchException::class);
        $this->expectExceptionMessage('OLX listing returned HTTP 503.');

        $client->get('https://www.olx.ua/error-IDabc123.html');
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function testTransportExceptionMapsToFetchException(): void
    {
        $httpClient = new class implements HttpClientInterface {
            /** @param array<string, mixed> $options */
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                throw new class ('timeout') extends RuntimeException implements TransportExceptionInterface {
                };
            }

            public function stream(
                ResponseInterface|iterable $responses,
                ?float $timeout = null,
            ): ResponseStreamInterface {
                throw new LogicException('Not used.');
            }

            /** @param array<string, mixed> $options */
            public function withOptions(array $options): static
            {
                return $this;
            }
        };

        $client = new OlxHttpClient($httpClient, 5, 'test-agent', new NullLogger());

        $this->expectException(PriceFetchException::class);
        $this->expectExceptionMessage('Network error while fetching OLX listing: timeout');

        $client->get('https://www.olx.ua/timeout-IDabc123.html');
    }
}
