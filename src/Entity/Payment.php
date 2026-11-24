<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'payment')]
class Payment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private ?Order $order = null;
    #[ORM\Column(length: 255, nullable: true, unique: true)] private ?string $transactionReference = null;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)] private string $amount;
    #[ORM\Column(length: 3, options: ['default' => 'EUR'])] private string $currency = 'EUR';
    #[ORM\Column(length: 20, nullable: true)] private ?string $method = null;
    #[ORM\Column(length: 12, options: ['default' => 'PENDING'])] private string $status = 'PENDING';
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $paidAt = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getOrder(): ?Order { return $this->order; } public function setOrder(?Order $value): static { $this->order = $value; return $this; }
    public function getTransactionReference(): ?string { return $this->transactionReference; } public function setTransactionReference(?string $value): static { $this->transactionReference = $value; return $this; }
    public function getAmount(): string { return $this->amount; } public function setAmount(string $value): static { $this->amount = $value; return $this; }
    public function getCurrency(): string { return $this->currency; } public function setCurrency(string $value): static { $this->currency = $value; return $this; }
    public function getMethod(): ?string { return $this->method; } public function setMethod(?string $value): static { $this->method = $value; return $this; }
    public function getStatus(): string { return $this->status; } public function setStatus(string $value): static { $this->status = $value; return $this; }
    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; } public function setPaidAt(?\DateTimeImmutable $value): static { $this->paidAt = $value; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    public function __toString(): string { return 'Paiement ' . ($this->transactionReference ?? ('#' . $this->id)); }
}
