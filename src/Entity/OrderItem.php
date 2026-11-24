<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'items')] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Order $order = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?Artwork $artwork = null;
    #[ORM\Column(length: 255)] private string $artworkTitle = 'Œuvre supprimée';
    #[ORM\Column(length: 150)] private string $artistName = 'Artiste inconnu';
    #[ORM\Column(options: ['default' => 1])] private int $quantity = 1;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)] private string $unitPrice;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)] private string $totalPrice;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getOrder(): ?Order { return $this->order; } public function setOrder(?Order $value): static { $this->order = $value; return $this; }
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
    public function getQuantity(): int { return $this->quantity; } public function setQuantity(int $value): static { $this->quantity = $value; return $this; }
    public function getUnitPrice(): string { return $this->unitPrice; } public function setUnitPrice(string $value): static { $this->unitPrice = $value; return $this; }
    public function getTotalPrice(): string { return $this->totalPrice; } public function setTotalPrice(string $value): static { $this->totalPrice = $value; return $this; }
}
