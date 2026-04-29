<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Domain\Listing\Listing;
use App\Domain\Subscription\Subscription;
use App\Infrastructure\Mail\EmailFactory;
use App\Infrastructure\Mail\SymfonyEmailNotifier;
use DateMalformedStringException;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class SymfonyEmailNotifierTest extends TestCase
{
    /**
     * @throws TransportExceptionInterface|DateMalformedStringException
     */
    public function testSendsConfirmationEmail(): void
    {
        $mailer = new RecordingMailer();
        $listing = $this->listing();
        $subscription = $this->subscription($listing);
        $notifier = new SymfonyEmailNotifier($mailer, $this->emailFactory());

        $notifier->sendSubscriptionConfirmation($subscription);

        self::assertCount(1, $mailer->messages);
        self::assertSame('Підтвердіть підписку в TestProject', $mailer->messages[0]->getSubject());
    }

    /**
     * @throws TransportExceptionInterface|DateMalformedStringException
     */
    public function testSendsPriceChangedEmailToEachSubscriber(): void
    {
        $mailer = new RecordingMailer();
        $listing = $this->listing();
        $first = $this->subscription($listing, 'first@example.com');
        $second = $this->subscription($listing, 'second@example.com');
        $notifier = new SymfonyEmailNotifier($mailer, $this->emailFactory());

        $notifier->sendPriceChanged($listing, 1200, 900, 'UAH', [$first, $second]);

        self::assertCount(2, $mailer->messages);
        self::assertSame('Зміна ціни OLX: Test listing', $mailer->messages[0]->getSubject());
        self::assertSame('Зміна ціни OLX: Test listing', $mailer->messages[1]->getSubject());
    }

    /**
     * @throws TransportExceptionInterface|DateMalformedStringException
     */
    public function testSendsUnavailableEmailToEachSubscriber(): void
    {
        $mailer = new RecordingMailer();
        $listing = $this->listing();
        $subscription = $this->subscription($listing);
        $notifier = new SymfonyEmailNotifier($mailer, $this->emailFactory());

        $notifier->sendListingUnavailable($listing, [$subscription]);

        self::assertCount(1, $mailer->messages);
        self::assertSame('Оголошення OLX більше не доступне', $mailer->messages[0]->getSubject());
    }

    private function listing(): Listing
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');
        $listing = new Listing(
            'https://olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'https://www.olx.ua/d/uk/obyavlenie/example-IDabc123.html',
            'abc123',
            $now,
        );
        $listing->markChecked(1200, 'UAH', 'Test listing', 'abc123', $now, $now);

        return $listing;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function subscription(Listing $listing, string $email = 'subscriber@example.com'): Subscription
    {
        $now = new DateTimeImmutable('2026-04-27 10:00:00');

        return new Subscription($listing, $email, 'token', $now->modify('+1 hour'), $now);
    }

    private function emailFactory(): EmailFactory
    {
        return new EmailFactory('TestProject', 'no-reply@example.com', 'http://localhost:8000', 24, 20);
    }
}

final class RecordingMailer implements MailerInterface
{
    /** @var list<Email> */
    public array $messages = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if (!$message instanceof Email) {
            throw new LogicException('Expected Email message.');
        }

        $this->messages[] = $message;
    }
}
