<?php

namespace App\Entity;

use App\Repository\BidRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BidRepository::class)]
#[ORM\Table(name: 'bid')]
class Bid
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Auction $auction = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private ?User $user = null;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)] private string $amount;
    #[ORM\Column(length: 12, options: ['default' => 'VALID'])] private string $status = 'VALID';
    #[ORM\Column] private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAuction(): ?Auction { return $this->auction; } public function setAuction(?Auction $value): static { $this->auction = $value; return $this; }
    public function getUser(): ?User { return $this->user; } public function setUser(?User $value): static { $this->user = $value; return $this; }
    public function getAmount(): string { return $this->amount; } public function setAmount(string $value): static { $this->amount = $value; return $this; }
    public function getStatus(): string { return $this->status; } public function setStatus(string $value): static { $this->status = $value; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
