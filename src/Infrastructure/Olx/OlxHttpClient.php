<?php

declare(strict_types=1);

namespace App\Infrastructure\Olx;

use App\Domain\Listing\ListingStatus;
use App\Domain\Price\PriceFetchException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final readonly class OlxHttpClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private int $timeoutSeconds,
        private string $userAgent,
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
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => $this->timeoutSeconds,
                'headers' => [
                    'User-Agent' => $this->userAgent,
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 404) {
                throw new PriceFetchException('OLX listing returned HTTP 404.', ListingStatus::NotFound);
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new PriceFetchException(sprintf('OLX listing returned HTTP %d.', $statusCode));
            }

            return $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new PriceFetchException(
                'Network error while fetching OLX listing: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
