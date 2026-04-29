<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

use App\Domain\Listing\Listing;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(name: 'idx_subscriptions_email', columns: ['email'])]
#[ORM\Index(name: 'idx_subscriptions_status', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'uniq_subscription_listing_email', columns: ['listing_id', 'email'])]
#[ORM\UniqueConstraint(name: 'uniq_subscriptions_confirmation_token', columns: ['confirmation_token'])]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Listing $listing;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 32)]
    private string $status;

    #[ORM\Column(length: 128)]
    private string $confirmationToken;

    #[ORM\Column]
    private DateTimeImmutable $confirmationTokenExpiresAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastNotifiedPrice = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastNotifiedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        Listing $listing,
        string $email,
        string $confirmationToken,
        DateTimeImmutable $confirmationTokenExpiresAt,
        DateTimeImmutable $now,
    ) {
        $this->listing = $listing;
        $this->email = mb_strtolower($email);
        $this->status = SubscriptionStatus::Pending->value;
        $this->confirmationToken = $confirmationToken;
        $this->confirmationTokenExpiresAt = $confirmationTokenExpiresAt;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): Listing
    {
        return $this->listing;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getStatus(): SubscriptionStatus
    {
        return SubscriptionStatus::from($this->status);
    }

    public function getConfirmationToken(): string
    {
        return $this->confirmationToken;
    }

    public function getConfirmationTokenExpiresAt(): DateTimeImmutable
    {
        return $this->confirmationTokenExpiresAt;
    }

    public function isActive(): bool
    {
        return $this->getStatus() === SubscriptionStatus::Active;
    }

    public function refreshConfirmation(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        if ($this->getStatus() === SubscriptionStatus::Active) {
            return;
        }

        $this->status = SubscriptionStatus::Pending->value;
        $this->confirmationToken = $token;
        $this->confirmationTokenExpiresAt = $expiresAt;
        $this->touch($now);
    }

    public function confirm(DateTimeImmutable $now): void
    {
        $this->status = SubscriptionStatus::Active->value;
        $this->confirmedAt = $now;
        $this->touch($now);
    }

    public function markNotified(int $newPrice, DateTimeImmutable $now): void
    {
        $this->lastNotifiedPrice = $newPrice;
        $this->lastNotifiedAt = $now;
        $this->touch($now);
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
