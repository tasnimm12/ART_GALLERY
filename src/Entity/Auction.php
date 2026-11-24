<?php

namespace App\Entity;

use App\Repository\AuctionRepository;
use App\Enum\AuctionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuctionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'auction')]
class Auction
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\OneToOne] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?Artwork $artwork = null;
    #[ORM\Column(length: 255)] private string $artworkTitle = 'Œuvre supprimée';
    #[ORM\Column(length: 150)] private string $artistName = 'Artiste inconnu';
    #[ORM\Column] private \DateTimeImmutable $startDate;
    #[ORM\Column] private \DateTimeImmutable $endDate;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)] private string $startingPrice = '0.00';
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $reservePrice = null;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '10.00'])] private string $minIncrement = '10.00';
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $currentPrice = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(name: 'winner_id', nullable: true, onDelete: 'SET NULL')] private ?User $winner = null;
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'SCHEDULED'], columnDefinition: "ENUM('SCHEDULED','ACTIVE','ENDED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED'")] private string $status = 'SCHEDULED';
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $closedAt = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getArtwork(): ?Artwork { return $this->artwork; }
    public function setArtwork(?Artwork $value): static
    {
        $this->artwork = $value;
        if ($value !== null) {
            $this->artworkTitle = $value->getTitle();
            $this->artistName = $value->getArtist()?->getName() ?? 'Artiste inconnu';
        }

        return $this;
    }
    public function getArtworkTitle(): string { return $this->artworkTitle; }
    public function setArtworkTitle(string $value): static { $this->artworkTitle = $value; return $this; }
    public function getArtistName(): string { return $this->artistName; }
    public function setArtistName(string $value): static { $this->artistName = $value; return $this; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; } public function setStartDate(\DateTimeImmutable $value): static { $this->startDate = $value; return $this; }
    public function getEndDate(): \DateTimeImmutable { return $this->endDate; } public function setEndDate(\DateTimeImmutable $value): static { $this->endDate = $value; return $this; }
    public function getStartingPrice(): string { return $this->startingPrice; } public function setStartingPrice(string $value): static { $this->startingPrice = $value; return $this; }
    public function getReservePrice(): ?string { return $this->reservePrice; } public function setReservePrice(?string $value): static { $this->reservePrice = $value; return $this; }
    public function getMinIncrement(): string { return $this->minIncrement; } public function setMinIncrement(string $value): static { $this->minIncrement = $value; return $this; }
    public function getCurrentPrice(): ?string { return $this->currentPrice; } public function setCurrentPrice(?string $value): static { $this->currentPrice = $value; return $this; }
    public function getWinner(): ?User { return $this->winner; } public function setWinner(?User $value): static { $this->winner = $value; return $this; }
    public function getStatus(): string { return $this->status; }

    public function getEffectiveStatus(): string
    {
        if ($this->status === AuctionStatus::CANCELLED->value) {
            return AuctionStatus::CANCELLED->value;
        }

        $now = new \DateTimeImmutable();

        if ($now < $this->startDate) {
            return AuctionStatus::SCHEDULED->value;
        }

        if ($now >= $this->startDate && $now <= $this->endDate) {
            return AuctionStatus::ACTIVE->value;
        }

        return AuctionStatus::ENDED->value;
    }

    public function updateStatus(): static
    {
        if ($this->status === AuctionStatus::CANCELLED->value) {
            return $this;
        }

        $this->status = $this->getEffectiveStatus();
        return $this;
    }

    public function synchronizeStatusFromDates(): static
    {
        return $this->updateStatus();
    }

    /**
     * Validate and set status using raw string (keeps backward compatibility)
     */
    public function setStatus(string $value): static {
        // Allow legacy 'CLOSED' during transition by mapping it to ENDED
        if ($value === 'CLOSED') {
            $value = AuctionStatus::ENDED->value;
        }

        if (!in_array($value, AuctionStatus::values(), true)) {
            throw new \InvalidArgumentException(sprintf('Invalid auction status "%s"', $value));
        }

        $this->status = $value;
        return $this;
    }

    /**
     * Return a typed enum for convenience
     */
    public function getStatusEnum(): AuctionStatus
    {
        // map legacy CLOSED to ENDED for safety
        $status = $this->status === 'CLOSED' ? AuctionStatus::ENDED->value : $this->status;
        return AuctionStatus::from($status);
    }

    public function setStatusEnum(AuctionStatus $status): static
    {
        $this->status = $status->value;
        return $this;
    }
    public function getClosedAt(): ?\DateTimeImmutable { return $this->closedAt; } public function setClosedAt(?\DateTimeImmutable $value): static { $this->closedAt = $value; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getMinimumNextBid(): float
    {
        if ($this->currentPrice === null || (float) $this->currentPrice <= 0) {
            return (float) $this->startingPrice;
        }

        return (float) $this->currentPrice + (float) $this->minIncrement;
    }

    public function isActive(): bool
    {
        if ($this->artwork === null || $this->closedAt !== null || $this->status === AuctionStatus::CANCELLED->value || $this->status === AuctionStatus::ENDED->value) {
            return false;
        }

        $now = new \DateTimeImmutable();
        return $now >= $this->startDate && $now <= $this->endDate;
    }

    public function isUpcoming(): bool
    {
        return $this->artwork !== null && $this->closedAt === null && (new \DateTimeImmutable()) < $this->startDate;
    }

    public function isEnded(): bool
    {
        return $this->closedAt !== null || $this->status === AuctionStatus::ENDED->value || (new \DateTimeImmutable()) > $this->endDate;
    }

    public function getTimeRemainingSeconds(): int
    {
        $now = new \DateTimeImmutable();
        if ($now > $this->endDate) {
            return 0;
        }

        return max(0, $this->endDate->getTimestamp() - $now->getTimestamp());
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function ensureStatusConsistency(): void
    {
        $this->updatedAt = new \DateTimeImmutable();

        if ($this->status !== AuctionStatus::CANCELLED->value) {
            $this->updateStatus();
        }

        if ($this->status === AuctionStatus::ACTIVE->value && $this->closedAt !== null) {
            $this->closedAt = null;
        }
    }

    public function __toString(): string
    {
        return 'Enchère #' . $this->id . ($this->artwork ? ' — ' . $this->artwork->getTitle() : '');
    }
}
