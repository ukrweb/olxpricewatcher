<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use Symfony\Component\Mime\Email;

final readonly class EmailFactory
{
    public function __construct(
        private string $projectName,
        private string $mailFrom,
        private string $appBaseUrl,
        private int $confirmationTtlHours,
        private int $unavailableNotificationThreshold,
    ) {
    }

    /**
     * Creates a Ukrainian confirmation email with an absolute API confirmation link.
     */
    public function confirmationEmail(Subscription $subscription): Email
    {
        $confirmationUrl = sprintf(
            '%s/api/subscriptions/confirm/%s',
            rtrim($this->appBaseUrl, '/'),
            $subscription->getConfirmationToken(),
        );

        return (new Email())
            ->from($this->mailFrom)
            ->to($subscription->getEmail())
            ->subject(sprintf('Підтвердіть підписку в %s', $this->projectName))
            ->text(sprintf(
                "Вітаємо,\n\n"
                . "Хтось запросив підписку на відстеження ціни в %s для цього оголошення OLX:\n\n"
                . "Оголошення: %s\n"
                . "URL: %s\n\n"
                . "Email-адреса: %s\n\n"
                . "Якщо це були ви, підтвердіть підписку за посиланням нижче:\n\n"
                . "%s\n\n"
                . "Після підтвердження %s відстежуватиме ціну оголошення "
                . "та повідомлятиме вас email-листом, коли ціна зміниться.\n\n"
                . "Якщо ви не запитували цю підписку, просто проігноруйте цей лист. "
                . "Підписка залишиться неактивною, і ви не отримуватимете повідомлення про зміну ціни.\n\n"
                . "Це посилання для підтвердження діє %d годин.\n\n"
                . "--\n"
                . "%s\n",
                $this->projectName,
                $this->listingTitle($subscription->getListing()),
                $subscription->getListing()->getNormalizedUrl(),
                $subscription->getEmail(),
                $confirmationUrl,
                $this->projectName,
                $this->confirmationTtlHours,
                $this->projectName,
            ));
    }

    /**
     * Creates a Ukrainian price-change email for one subscriber.
     */
    public function priceChangedEmail(
        Subscription $subscription,
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
    ): Email {
        return (new Email())
            ->from($this->mailFrom)
            ->to($subscription->getEmail())
            ->subject(sprintf('Зміна ціни OLX: %s', $listing->getTitle() ?? $listing->getNormalizedUrl()))
            ->text(sprintf(
                "Вітаємо,\n\n"
                . "Ви підписані на відстеження ціни цього оголошення OLX через %s:\n\n"
                . "Оголошення: %s\n"
                . "URL: %s\n\n"
                . "Ціна оголошення змінилася:\n\n"
                . "Стара ціна: %s %s\n"
                . "Нова ціна: %d %s\n\n"
                . "Відкрити оголошення можна тут:\n"
                . "%s\n\n"
                . "Якщо ви не підписувалися на це відстеження, просто проігноруйте цей лист.\n\n"
                . "--\n"
                . "%s\n",
                $this->projectName,
                $this->listingTitle($listing),
                $listing->getNormalizedUrl(),
                $oldPrice === null ? 'unknown' : (string) $oldPrice,
                $currency,
                $newPrice,
                $currency,
                $listing->getNormalizedUrl(),
                $this->projectName,
            ));
    }

    /**
     * Creates a Ukrainian notification for a listing that stayed unavailable after repeated checks.
     */
    public function listingUnavailableEmail(Subscription $subscription, Listing $listing): Email
    {
        return (new Email())
            ->from($this->mailFrom)
            ->to($subscription->getEmail())
            ->subject('Оголошення OLX більше не доступне')
            ->text(sprintf(
                "Вітаємо,\n\n"
                . "Ви підписані на відстеження цього оголошення OLX через %s:\n\n"
                . "Оголошення: %s\n"
                . "URL: %s\n\n"
                . "Система не змогла знайти це оголошення на OLX після %d послідовних перевірок.\n"
                . "Можливо, його продали, видалили або воно тимчасово недоступне.\n\n"
                . "Поки оголошення недоступне, нові повідомлення про зміну ціни не надсилатимуться.\n"
                . "Якщо оголошення знову стане доступним, відстеження може відновитися автоматично.\n\n"
                . "Якщо ви не підписувалися на це відстеження, просто проігноруйте цей лист.\n\n"
                . "--\n"
                . "%s\n",
                $this->projectName,
                $this->listingTitle($listing),
                $listing->getNormalizedUrl(),
                $this->unavailableNotificationThreshold,
                $this->projectName,
            ));
    }

    private function listingTitle(Listing $listing): string
    {
        return $listing->getTitle() ?? 'Оголошення без назви';
    }
}
