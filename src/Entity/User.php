<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 255, unique: true)] private string $email;
    #[ORM\Column(name: 'password_hash', length: 255)] private string $password;
    #[ORM\Column(length: 100)] private string $firstName;
    #[ORM\Column(length: 100)] private string $lastName;
    #[ORM\Column(length: 30, nullable: true)] private ?string $phone = null;
    #[ORM\Column(length: 10, options: ['default' => 'ACTIVE'])] private string $status = 'ACTIVE';
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $emailVerifiedAt = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'ROLE_USER'])]
    private string $role = 'ROLE_USER';
    /** @var Collection<int, Favorite> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Favorite::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $favorites;

    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); $this->role = 'ROLE_USER'; $this->favorites = new ArrayCollection(); }
    public function getId(): ?int { return $this->id; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getEmail(): string { return $this->email; } public function setEmail(string $value): static { $this->email = $value; return $this; }
    public function getPassword(): string { return $this->password; } public function setPassword(string $value): static { $this->password = $value; return $this; }
    public function eraseCredentials(): void {}
    /** @return list<string> */
    public function getRoles(): array { return [$this->role]; }

    /** @param list<string> $roles - compatibility: set first role */
    public function setRoles(array $roles): static { $this->role = $roles[0] ?? 'ROLE_USER'; return $this; }

    public function addRole(string $role): static { $this->role = $role; return $this; }

    public function removeRole(string $role): static { if ($this->role === $role) $this->role = 'ROLE_USER'; return $this; }
    public function getFirstName(): string { return $this->firstName; } public function setFirstName(string $value): static { $this->firstName = $value; return $this; }
    public function getLastName(): string { return $this->lastName; } public function setLastName(string $value): static { $this->lastName = $value; return $this; }
    public function getPhone(): ?string { return $this->phone; } public function setPhone(?string $value): static { $this->phone = $value; return $this; }
    public function getStatus(): string { return $this->status; } public function setStatus(string $value): static { $this->status = $value; return $this; }
    public function getEmailVerifiedAt(): ?\DateTimeImmutable { return $this->emailVerifiedAt; } public function setEmailVerifiedAt(?\DateTimeImmutable $value): static { $this->emailVerifiedAt = $value; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    /** @return Collection<int, Favorite> */
    public function getFavorites(): Collection { return $this->favorites; }
    public function addFavorite(Favorite $favorite): static
    {
        if (!$this->favorites->contains($favorite)) {
            $this->favorites->add($favorite);
            $favorite->setUser($this);
        }

        return $this;
    }
    public function removeFavorite(Favorite $favorite): static
    {
        $this->favorites->removeElement($favorite);

        return $this;
    }
    public function __toString(): string { return $this->firstName . ' ' . $this->lastName . ' (' . $this->email . ')'; }

    /** Convenience single-role getter for forms (backed by role column) */
    public function getRole(): string
    {
        return $this->role ?? 'ROLE_USER';
    }

    /** Convenience single-role setter for forms (backed by role column) */
    public function setRole(?string $role): static
    {
        $this->role = $role ?? 'ROLE_USER';
        return $this;
    }
}
