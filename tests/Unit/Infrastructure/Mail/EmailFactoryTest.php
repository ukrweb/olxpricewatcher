<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Application\Notification\NotificationMessage;
use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Infrastructure\Mail\EmailFactory;
use App\Infrastructure\Mail\EmailTemplateRendererInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmailFactoryTest extends TestCase
{
    /**
     * @throws DateMalformedStringException
     */
    public function testDelegatesRenderingAndSetsSenderAndRecipient(): void
    {
        $renderer = new RecordingEmailTemplateRenderer();
        $factory = new EmailFactory($renderer, 'no-reply@example.com', 'http://localhost:8000/');
        $subscription = $this->subscription($this->listing());

        $email = $factory->confirmationEmail($subscription);

        self::assertSame('Rendered subject', $email->getSubject());
        self::assertSame("Rendered body\n", $email->getTextBody());
        self::assertSame('no-reply@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('subscriber@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('http://localhost:8000/api/subscriptions/confirm/confirm-token', $renderer->confirmationUrl);
    }

    private function listing(): Listing
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = new Listing(
            'https://m.olx.ua/d/uk/obyavlenie/test-IDmail123.html',
            'https://www.olx.ua/d/uk/obyavlenie/test-IDmail123.html',
            'mail123',
            $now,
        );
        $listing->markChecked(1000, 'UAH', 'Test chair', 'mail123', $now, $now);

        return $listing;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function subscription(Listing $listing): Subscription
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');

        return new Subscription($listing, 'subscriber@example.com', 'confirm-token', $now->modify('+24 hours'), $now);
    }
}

final class RecordingEmailTemplateRenderer implements EmailTemplateRendererInterface
{
    public ?string $confirmationUrl = null;

    public function renderConfirmation(Subscription $subscription, string $confirmationUrl): NotificationMessage
    {
        $this->confirmationUrl = $confirmationUrl;

        return new NotificationMessage('Rendered subject', "Rendered body\n");
    }

    public function renderPriceChanged(
        Subscription $subscription,
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
    ): NotificationMessage {
        return new NotificationMessage('Rendered price subject', "Rendered price body\n");
    }

    public function renderListingUnavailable(Subscription $subscription, Listing $listing): NotificationMessage
    {
        return new NotificationMessage('Rendered unavailable subject', "Rendered unavailable body\n");
    }
}
