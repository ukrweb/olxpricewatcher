<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Notification\NotificationMessage;
use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SymfonyEmailTemplateRenderer implements EmailTemplateRendererInterface
{
    private const DEFAULT_LOCALE = 'ua';

    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['ua', 'en'];

    private string $locale;

    public function __construct(
        private TranslatorInterface $translator,
        string $locale,
        private string $projectName,
        private int $confirmationTtlHours,
        private int $unavailableNotificationThreshold,
    ) {
        $this->locale = $this->normalizeLocale($locale);
    }

    public function renderConfirmation(Subscription $subscription, string $confirmationUrl): NotificationMessage
    {
        $listing = $subscription->getListing();

        return $this->message('confirmation', [
            '%title%' => $this->listingTitle($listing),
            '%url%' => $listing->getNormalizedUrl(),
            '%email%' => $subscription->getEmail(),
            '%confirmation_url%' => $confirmationUrl,
            '%ttl_hours%' => (string) $this->confirmationTtlHours,
            '%project_name%' => $this->projectName,
        ]);
    }

    public function renderPriceChanged(
        Subscription $subscription,
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
    ): NotificationMessage {
        return $this->message('price_changed', [
            '%title%' => $this->listingTitle($listing),
            '%url%' => $listing->getNormalizedUrl(),
            '%old_price%' => $oldPrice === null ? 'unknown' : sprintf('%d %s', $oldPrice, $currency),
            '%new_price%' => sprintf('%d %s', $newPrice, $currency),
            '%project_name%' => $this->projectName,
        ]);
    }

    public function renderListingUnavailable(Subscription $subscription, Listing $listing): NotificationMessage
    {
        return $this->message('listing_unavailable', [
            '%title%' => $this->listingTitle($listing),
            '%url%' => $listing->getNormalizedUrl(),
            '%project_name%' => $this->projectName,
            '%unavailable_threshold%' => (string) $this->unavailableNotificationThreshold,
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function message(string $prefix, array $parameters): NotificationMessage
    {
        return new NotificationMessage(
            $this->translator->trans($prefix . '.subject', $parameters, 'emails', $this->locale),
            $this->translator->trans($prefix . '.body', $parameters, 'emails', $this->locale),
        );
    }

    private function listingTitle(Listing $listing): string
    {
        return $listing->getTitle() ?? $listing->getNormalizedUrl();
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = mb_strtolower(trim($locale));

        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            return $locale;
        }

        return self::DEFAULT_LOCALE;
    }
}
