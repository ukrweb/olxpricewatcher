<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Infrastructure\Mail\EmailFactory;
use DateMalformedStringException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmailFactoryTest extends TestCase
{
    /**
     * @throws DateMalformedStringException
     */
    public function testConfirmationEmailContainsClearSubscriptionContext(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = $this->listing($now);
        $subscription = new Subscription(
            $listing,
            'subscriber@example.com',
            'confirm-token',
            $now->modify('+24 hours'),
            $now,
        );

        $email = $this->factory()->confirmationEmail($subscription);
        $body = (string) $email->getTextBody();

        self::assertSame('Підтвердіть підписку в TestProject', $email->getSubject());
        self::assertStringContainsString('TestProject', $body);
        self::assertStringContainsString('Оголошення: Test chair', $body);
        self::assertStringContainsString('URL: https://www.olx.ua/d/uk/obyavlenie/test-IDmail123.html', $body);
        self::assertStringContainsString('Email-адреса: subscriber@example.com', $body);
        self::assertStringContainsString('http://localhost:8000/api/subscriptions/confirm/confirm-token', $body);
        self::assertStringContainsString('просто проігноруйте цей лист', $body);
        self::assertStringContainsString('діє 24 годин', $body);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testPriceChangedEmailContainsReadablePriceChangeContext(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = $this->listing($now);
        $subscription = new Subscription($listing, 'subscriber@example.com', 'token', $now->modify('+1 hour'), $now);

        $email = $this->factory()->priceChangedEmail($subscription, $listing, 1000, 900, 'UAH');
        $body = (string) $email->getTextBody();

        self::assertSame('Зміна ціни OLX: Test chair', $email->getSubject());
        self::assertStringContainsString('TestProject', $body);
        self::assertStringContainsString('Оголошення: Test chair', $body);
        self::assertStringContainsString('URL: https://www.olx.ua/d/uk/obyavlenie/test-IDmail123.html', $body);
        self::assertStringContainsString('Стара ціна: 1000 UAH', $body);
        self::assertStringContainsString('Нова ціна: 900 UAH', $body);
        self::assertStringContainsString('Якщо ви не підписувалися на це відстеження', $body);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testUnavailableEmailContainsThresholdAndIgnoreText(): void
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = $this->listing($now);
        $subscription = new Subscription($listing, 'subscriber@example.com', 'token', $now->modify('+1 hour'), $now);

        $email = $this->factory()->listingUnavailableEmail($subscription, $listing);
        $body = (string) $email->getTextBody();

        self::assertSame('Оголошення OLX більше не доступне', $email->getSubject());
        self::assertStringContainsString('TestProject', $body);
        self::assertStringContainsString('Оголошення: Test chair', $body);
        self::assertStringContainsString('URL: https://www.olx.ua/d/uk/obyavlenie/test-IDmail123.html', $body);
        self::assertStringContainsString('після 20 послідовних перевірок', $body);
        self::assertStringContainsString('Якщо ви не підписувалися на це відстеження', $body);
    }

    private function factory(): EmailFactory
    {
        return new EmailFactory('TestProject', 'no-reply@example.com', 'http://localhost:8000', 24, 20);
    }

    private function listing(DateTimeImmutable $now): Listing
    {
        $listing = new Listing(
            'https://m.olx.ua/d/uk/obyavlenie/test-IDmail123.html',
            'https://www.olx.ua/d/uk/obyavlenie/test-IDmail123.html',
            'mail123',
            $now,
        );
        $listing->markChecked(1000, 'UAH', 'Test chair', 'mail123', $now, $now);

        return $listing;
    }
}
