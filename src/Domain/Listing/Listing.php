<?php

declare(strict_types=1);

namespace App\Domain\Listing;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'listings')]
#[ORM\Index(name: 'idx_listings_status', columns: ['status'])]
#[ORM\Index(name: 'idx_listings_next_check_at', columns: ['next_check_at'])]
#[ORM\Index(name: 'idx_listings_status_next_check_at', columns: ['status', 'next_check_at'])]
#[ORM\UniqueConstraint(name: 'uniq_listing_normalized_url', columns: ['normalized_url'])]
class Listing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 32, options: ['default' => 'olx'])]
    private string $source = 'olx';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'text')]
    private string $originalUrl;

    #[ORM\Column(type: 'text')]
    private string $normalizedUrl;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?int $currentPrice = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(length: 32)]
    private string $status;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastCheckedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextCheckAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $consecutiveNotFoundCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $consecutiveFetchErrorCount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $unavailableNotifiedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, \App\Domain\Subscription\Subscription> */
    #[ORM\OneToMany(
        mappedBy: 'listing',
        targetEntity: \App\Domain\Subscription\Subscription::class,
    )]
    private Collection $subscriptions;

    public function __construct(
        string $originalUrl,
        string $normalizedUrl,
        ?string $externalId,
        \DateTimeImmutable $now,
    ) {
        $this->originalUrl = $originalUrl;
        $this->normalizedUrl = $normalizedUrl;
        $this->externalId = $externalId;
        $this->status = ListingStatus::New->value;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->subscriptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    public function getNormalizedUrl(): string
    {
        return $this->normalizedUrl;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId, \DateTimeImmutable $now): void
    {
        $this->externalId = $externalId;
        $this->touch($now);
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getCurrentPrice(): ?int
    {
        return $this->currentPrice;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getStatus(): ListingStatus
    {
        return ListingStatus::from($this->status);
    }

    public function getLastCheckedAt(): ?\DateTimeImmutable
    {
        return $this->lastCheckedAt;
    }

    public function getNextCheckAt(): ?\DateTimeImmutable
    {
        return $this->nextCheckAt;
    }

    public function getConsecutiveNotFoundCount(): int
    {
        return $this->consecutiveNotFoundCount;
    }

    public function getConsecutiveFetchErrorCount(): int
    {
        return $this->consecutiveFetchErrorCount;
    }

    public function getUnavailableNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->unavailableNotifiedAt;
    }

    public function markUnavailableNotified(\DateTimeImmutable $notifiedAt): void
    {
        $this->unavailableNotifiedAt = $notifiedAt;
        $this->touch($notifiedAt);
    }

    public function markChecked(
        ?int $price,
        ?string $currency,
        ?string $title,
        ?string $externalId,
        \DateTimeImmutable $checkedAt,
        \DateTimeImmutable $nextCheckAt,
    ): void {
        $this->currentPrice = $price;
        $this->currency = $currency;
        $this->title = $title ?? $this->title;
        $this->externalId = $externalId ?? $this->externalId;
        $this->status = $price === null ? ListingStatus::NoPrice->value : ListingStatus::Active->value;
        $this->consecutiveNotFoundCount = 0;
        if ($price === null) {
            $this->consecutiveFetchErrorCount++;
        } else {
            $this->consecutiveFetchErrorCount = 0;
            $this->unavailableNotifiedAt = null;
        }
        $this->lastCheckedAt = $checkedAt;
        $this->nextCheckAt = $nextCheckAt;
        $this->lastError = null;
        $this->touch($checkedAt);
    }

    public function markFetchError(string $error, \DateTimeImmutable $checkedAt, \DateTimeImmutable $nextCheckAt): void
    {
        $this->status = ListingStatus::ParseError->value;
        $this->consecutiveFetchErrorCount++;
        $this->consecutiveNotFoundCount = 0;
        $this->lastError = mb_substr($error, 0, 2000);
        $this->lastCheckedAt = $checkedAt;
        $this->nextCheckAt = $nextCheckAt;
        $this->touch($checkedAt);
    }

    public function markNotFound(string $error, \DateTimeImmutable $checkedAt, \DateTimeImmutable $nextCheckAt): void
    {
        $this->status = ListingStatus::NotFound->value;
        $this->consecutiveNotFoundCount++;
        $this->consecutiveFetchErrorCount = 0;
        $this->lastError = mb_substr($error, 0, 2000);
        $this->lastCheckedAt = $checkedAt;
        $this->nextCheckAt = $nextCheckAt;
        $this->touch($checkedAt);
    }

    private function touch(\DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
