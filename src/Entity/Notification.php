<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification')]
class Notification
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?User $user = null;
    #[ORM\Column(length: 50)] private string $type;
    #[ORM\Column(length: 255)] private string $title;
    #[ORM\Column(type: Types::TEXT)] private string $message;
    #[ORM\Column(options: ['default' => false])] private bool $isRead = false;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $readAt = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; } public function setUser(?User $value): static { $this->user = $value; return $this; }
    public function getType(): string { return $this->type; } public function setType(string $value): static { $this->type = $value; return $this; }
    public function getTitle(): string { return $this->title; } public function setTitle(string $value): static { $this->title = $value; return $this; }
    public function getMessage(): string { return $this->message; } public function setMessage(string $value): static { $this->message = $value; return $this; }
    public function isRead(): bool { return $this->isRead; } public function setIsRead(bool $value): static { $this->isRead = $value; return $this; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; } public function setReadAt(?\DateTimeImmutable $value): static { $this->readAt = $value; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
