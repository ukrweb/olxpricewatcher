<?php

declare(strict_types=1);

namespace App\Domain\Price;

use App\Domain\Listing\Listing;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_history')]
class PriceHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Listing $listing;

    #[ORM\Column(nullable: true)]
    private ?int $oldPrice;

    #[ORM\Column]
    private int $newPrice;

    #[ORM\Column(length: 16)]
    private string $currency;

    #[ORM\Column(length: 64)]
    private string $source;

    #[ORM\Column]
    private DateTimeImmutable $detectedAt;

    public function __construct(
        Listing $listing,
        ?int $oldPrice,
        int $newPrice,
        string $currency,
        string $source,
        DateTimeImmutable $detectedAt,
    ) {
        $this->listing = $listing;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
        $this->currency = $currency;
        $this->source = $source;
        $this->detectedAt = $detectedAt;
    }
}
