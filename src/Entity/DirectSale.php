<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'direct_sale')]
class DirectSale
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\OneToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Artwork $artwork = null;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)] private string $price;
    #[ORM\Column(length: 3, options: ['default' => 'EUR'])] private string $currency = 'EUR';
    #[ORM\Column(length: 12, options: ['default' => 'AVAILABLE'])] private string $status = 'AVAILABLE';
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getArtwork(): ?Artwork { return $this->artwork; } public function setArtwork(?Artwork $value): static { $this->artwork = $value; return $this; }
    public function getPrice(): string { return $this->price; } public function setPrice(string $value): static { $this->price = $value; return $this; }
    public function getCurrency(): string { return $this->currency; } public function setCurrency(string $value): static { $this->currency = $value; return $this; }
    public function getStatus(): string { return $this->status; } public function setStatus(string $value): static { $this->status = $value; return $this; }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
