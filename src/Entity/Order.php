<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private ?User $user = null;
    #[ORM\Column(length: 50, unique: true)] private string $orderNumber;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)] private string $totalAmount;
    #[ORM\Column(length: 3, options: ['default' => 'EUR'])] private string $currency = 'EUR';
    #[ORM\Column(length: 15)] private string $source;
    #[ORM\Column(length: 20, options: ['default' => 'PENDING'])] private string $status = 'PENDING';
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)] private ?string $latitude = null;
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)] private ?string $longitude = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    /** @var Collection<int, OrderItem> */ #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, orphanRemoval: true)] private Collection $items;
    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); $this->items = new ArrayCollection(); }
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; } public function setUser(?User $value): static { $this->user = $value; return $this; }
    public function getOrderNumber(): string { return $this->orderNumber; } public function setOrderNumber(string $value): static { $this->orderNumber = $value; return $this; }
    public function getTotalAmount(): string { return $this->totalAmount; } public function setTotalAmount(string $value): static { $this->totalAmount = $value; return $this; }
    public function getCurrency(): string { return $this->currency; } public function setCurrency(string $value): static { $this->currency = $value; return $this; }
    public function getSource(): string { return $this->source; } public function setSource(string $value): static { $this->source = $value; return $this; }
    public function getStatus(): string { return $this->status; } public function setStatus(string $value): static { $this->status = $value; return $this; }
    public function getLatitude(): ?string { return $this->latitude; } public function setLatitude(?string $value): static { $this->latitude = $value; return $this; }
    public function getLongitude(): ?string { return $this->longitude; } public function setLongitude(?string $value): static { $this->longitude = $value; return $this; }
    /** @return Collection<int, OrderItem> */ public function getItems(): Collection { return $this->items; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    public function __toString(): string { return '#' . $this->orderNumber; }
}
