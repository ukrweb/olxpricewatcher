<?php

declare(strict_types=1);

namespace App\Infrastructure\Olx;

use App\Domain\Listing\ListingStatus;
use App\Domain\Price\PriceFetchException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OlxHttpClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private int $timeoutSeconds,
        private string $userAgent,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Fetches public OLX HTML and maps HTTP/network failures to price-fetch exceptions.
     *
     * @param string $url
     * @return string
     * @throws ClientExceptionInterface
     * @throws PriceFetchException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function get(string $url): string
    {
        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => $this->timeoutSeconds,
                'headers' => [
                    'User-Agent' => $this->userAgent,
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $this->logger->info('OLX HTTP response received.', [
                'url' => $url,
                'status_code' => $statusCode,
                'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
            ]);

            if (in_array($statusCode, [404, 410], true)) {
                throw new PriceFetchException(
                    sprintf('OLX listing returned HTTP %d.', $statusCode),
                    ListingStatus::NotFound,
                );
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new PriceFetchException(sprintf('OLX listing returned HTTP %d.', $statusCode));
            }

            return $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('OLX HTTP transport failure.', [
                'url' => $url,
                'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw new PriceFetchException(
                'Network error while fetching OLX listing: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
