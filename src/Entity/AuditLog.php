<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
class AuditLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?User $user = null;
    #[ORM\Column(length: 100)] private string $action;
    #[ORM\Column(length: 100)] private string $entityType;
    #[ORM\Column(nullable: true)] private ?int $entityId = null;
    #[ORM\Column(type: Types::JSON, nullable: true)] private ?array $details = null;
    #[ORM\Column(length: 45, nullable: true)] private ?string $ipAddress = null;
    #[ORM\Column(length: 500, nullable: true)] private ?string $userAgent = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; } public function setUser(?User $value): static { $this->user = $value; return $this; }
    public function getAction(): string { return $this->action; } public function setAction(string $value): static { $this->action = $value; return $this; }
    public function getEntityType(): string { return $this->entityType; } public function setEntityType(string $value): static { $this->entityType = $value; return $this; }
    public function getEntityId(): ?int { return $this->entityId; } public function setEntityId(?int $value): static { $this->entityId = $value; return $this; }
    public function getDetails(): ?array { return $this->details; } public function setDetails(?array $value): static { $this->details = $value; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; } public function setIpAddress(?string $value): static { $this->ipAddress = $value; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; } public function setUserAgent(?string $value): static { $this->userAgent = $value; return $this; }
}
