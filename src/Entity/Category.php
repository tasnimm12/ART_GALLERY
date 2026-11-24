<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'category')]
#[UniqueEntity(fields: ['name'], message: 'Cette catégorie existe déjà.')]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug existe déjà.')]
class Category
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: "Le nom de la catégorie est obligatoire.")]
    #[Assert\Length(min: 2, max: 100, minMessage: "Le nom doit contenir au moins {{ limit }} caractères.", maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères.")]
    #[Assert\Regex(pattern: '/^[\\p{L}\\s\'\-]+$/u', message: 'Le nom contient des caractères invalides.')]
    private string $name;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank(message: "Le slug est obligatoire.")]
    #[Assert\Length(max: 150, maxMessage: "Le slug ne peut pas dépasser {{ limit }} caractères.")]
    #[Assert\Regex(pattern: '/^[a-z0-9\-]+$/', message: 'Le slug contient des caractères invalides.')]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = trim($slug); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description === null ? null : trim($description); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function ensureSlug(): void
    {
        // If slug empty or contains invalid chars, auto-generate from name
        if (empty($this->slug) && !empty($this->name)) {
            $this->slug = self::slugify($this->name);
        } else {
            // normalize provided slug
            $this->slug = self::slugify($this->slug);
        }
    }

    private static function slugify(string $text): string
    {
        // Transliterate accents to ASCII
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9 ] remove; Lower()', $text);
        } else {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = preg_replace('/[^A-Za-z0-9 ]/', '', $text);
            $text = strtolower($text);
        }

        // Replace spaces and multiple separators with single dash
        $text = preg_replace('/[\s\_]+/', '-', $text);
        // Keep only a-z0-9 and dashes
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);
        $text = trim($text, '-');

        return $text === '' ? 'category' : $text;
    }

    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    public function __toString(): string { return $this->name; }
}
