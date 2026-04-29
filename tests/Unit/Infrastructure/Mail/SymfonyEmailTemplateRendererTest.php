<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Infrastructure\Mail\SymfonyEmailTemplateRenderer;
use DateMalformedStringException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

final class SymfonyEmailTemplateRendererTest extends TestCase
{
    /**
     * @throws DateMalformedStringException
     */
    public function testUkrainianConfirmationEmailSubjectAndBody(): void
    {
        $subscription = $this->subscription($this->listing());
        $message = $this->renderer('ua')->renderConfirmation($subscription, 'http://app/confirm/token');

        self::assertSame('Підтвердіть підписку в TestProject', $message->subject);
        self::assertStringContainsString('в TestProject для цього оголошення OLX', $message->body);
        self::assertStringContainsString('Оголошення: Test chair', $message->body);
        self::assertStringContainsString('Email-адреса: subscriber@example.com', $message->body);
        self::assertStringContainsString('http://app/confirm/token', $message->body);
        self::assertStringContainsString('діє 24 годин', $message->body);
        self::assertStringContainsString("--\nTestProject", $message->body);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testEnglishConfirmationEmailSubjectAndBody(): void
    {
        $subscription = $this->subscription($this->listing());
        $message = $this->renderer('en')->renderConfirmation($subscription, 'http://app/confirm/token');

        self::assertSame('Confirm your TestProject subscription', $message->subject);
        self::assertStringContainsString('in TestProject for this OLX listing', $message->body);
        self::assertStringContainsString('Listing: Test chair', $message->body);
        self::assertStringContainsString('Email address: subscriber@example.com', $message->body);
        self::assertStringContainsString('http://app/confirm/token', $message->body);
        self::assertStringContainsString('valid for 24 hours', $message->body);
        self::assertStringContainsString("--\nTestProject", $message->body);
    }

    public function testUkrainianPriceChangedEmailSubjectAndBody(): void
    {
        $listing = $this->listing();
        $message = $this->renderer('ua')->renderPriceChanged(
            $this->subscription($listing),
            $listing,
            1000,
            900,
            'UAH',
        );

        self::assertSame('Зміна ціни OLX: Test chair', $message->subject);
        self::assertStringContainsString('через TestProject', $message->body);
        self::assertStringContainsString('Оголошення: Test chair', $message->body);
        self::assertStringContainsString('Стара ціна: 1000 UAH', $message->body);
        self::assertStringContainsString('Нова ціна: 900 UAH', $message->body);
        self::assertStringContainsString("--\nTestProject", $message->body);
    }

    public function testEnglishPriceChangedEmailSubjectAndBody(): void
    {
        $listing = $this->listing();
        $message = $this->renderer('en')->renderPriceChanged(
            $this->subscription($listing),
            $listing,
            1000,
            900,
            'UAH',
        );

        self::assertSame('OLX price changed: Test chair', $message->subject);
        self::assertStringContainsString('through TestProject', $message->body);
        self::assertStringContainsString('Listing: Test chair', $message->body);
        self::assertStringContainsString('Old price: 1000 UAH', $message->body);
        self::assertStringContainsString('New price: 900 UAH', $message->body);
        self::assertStringContainsString("--\nTestProject", $message->body);
    }

    public function testUkrainianUnavailableEmailSubjectAndBody(): void
    {
        $listing = $this->listing();
        $message = $this->renderer('ua')->renderListingUnavailable($this->subscription($listing), $listing);

        self::assertSame('Оголошення OLX більше не доступне', $message->subject);
        self::assertStringContainsString('Оголошення: Test chair', $message->body);
        self::assertStringContainsString('через TestProject', $message->body);
        self::assertStringContainsString('після 20 послідовних перевірок', $message->body);
        self::assertStringContainsString('Поки оголошення недоступне', $message->body);
    }

    public function testEnglishUnavailableEmailSubjectAndBody(): void
    {
        $listing = $this->listing();
        $message = $this->renderer('en')->renderListingUnavailable($this->subscription($listing), $listing);

        self::assertSame('OLX listing is no longer available', $message->subject);
        self::assertStringContainsString('Listing: Test chair', $message->body);
        self::assertStringContainsString('through TestProject', $message->body);
        self::assertStringContainsString('after 20 consecutive checks', $message->body);
        self::assertStringContainsString('While the listing is unavailable', $message->body);
    }

    public function testUnsupportedLocaleFallsBackToUkrainian(): void
    {
        $subscription = $this->subscription($this->listing());
        $message = $this->renderer('pl')->renderConfirmation($subscription, 'http://app/confirm/token');

        self::assertSame('Підтвердіть підписку в TestProject', $message->subject);
        self::assertStringContainsString('Оголошення: Test chair', $message->body);
    }

    private function renderer(string $locale): SymfonyEmailTemplateRenderer
    {
        $translator = new Translator('ua');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__ . '/../../../../translations/emails.ua.yaml', 'ua', 'emails');
        $translator->addResource('yaml', __DIR__ . '/../../../../translations/emails.en.yaml', 'en', 'emails');

        return new SymfonyEmailTemplateRenderer($translator, $locale, 'TestProject', 24, 20);
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
