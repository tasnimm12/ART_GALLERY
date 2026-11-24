<?php

namespace App\Entity;

use App\Repository\ArtworkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArtworkRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'artwork')]
#[ORM\Index(columns: ['artist_id'], name: 'idx_artwork_artist')]
#[ORM\Index(columns: ['category_id'], name: 'idx_artwork_category')]
#[ORM\Index(columns: ['status'], name: 'idx_artwork_status')]
#[ORM\Index(columns: ['daily_featured'], name: 'idx_artwork_daily_featured')]
class Artwork
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: "Veuillez sélectionner un artiste.")]
    private ?Artist $artist = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: "Veuillez sélectionner une catégorie.")]
    private ?Category $category = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères.")]
    private string $title;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $yearCreated = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'La technique ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $technique = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Les dimensions ne peuvent pas dépasser {{ limit }} caractères.')]
    private ?string $dimensions = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^\d+(?:\.\d{1,2})?$/', message: 'Le prix doit être un nombre valide (2 décimales maximum).')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le prix doit être supérieur ou égal à 0.')]
    private ?string $price = null;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(length: 20, options: ['default' => 'AVAILABLE'])]
    #[Assert\Choice(choices: ['AVAILABLE','AUCTION','RESERVED','SOLD'], message: 'Statut invalide.')]
    private string $status = 'AVAILABLE';

    #[ORM\Column(options: ['default' => false])]
    private bool $dailyFeatured = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ArtworkImage> */
    #[ORM\OneToMany(mappedBy: 'artwork', targetEntity: ArtworkImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['displayOrder' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Count(max: 5, maxMessage: 'Une œuvre ne peut pas avoir plus de 5 images.')]
    #[Assert\Valid]
    private Collection $images;

    /** @var Collection<int, Favorite> */
    #[ORM\OneToMany(mappedBy: 'artwork', targetEntity: Favorite::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $favorites;

    /** @var Collection<int, ArtworkColor> */
    #[ORM\ManyToMany(targetEntity: ArtworkColor::class)]
    #[ORM\JoinTable(name: 'artwork_color_link')]
    #[ORM\JoinColumn(name: 'artwork_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'color_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $colors;

    /** @var Collection<int, ArtworkColorAnalysis> */
    #[ORM\OneToMany(mappedBy: 'artwork', targetEntity: ArtworkColorAnalysis::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['percentage' => 'DESC', 'id' => 'ASC'])]
    private Collection $colorAnalyses;

    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); $this->images = new ArrayCollection(); $this->favorites = new ArrayCollection(); $this->colors = new ArrayCollection(); $this->colorAnalyses = new ArrayCollection(); }

    public function getId(): ?int { return $this->id; }

    public function getArtist(): ?Artist { return $this->artist; } public function setArtist(?Artist $artist): static { $this->artist = $artist; return $this; }

    public function getCategory(): ?Category { return $this->category; } public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    public function getTitle(): string { return $this->title; } public function setTitle(string $title): static { $this->title = trim($title); return $this; }

    public function getPhoto(): ?string { return $this->photo; } public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function getDescription(): ?string { return $this->description; } public function setDescription(?string $description): static { $this->description = $description === null ? null : trim($description); return $this; }

    public function getYearCreated(): ?\DateTimeImmutable { return $this->yearCreated; } public function setYearCreated(?\DateTimeImmutable $value): static { $this->yearCreated = $value; return $this; }

    public function getTechnique(): ?string { return $this->technique; } public function setTechnique(?string $value): static { $this->technique = $value === null ? null : trim($value); return $this; }

    public function getDimensions(): ?string { return $this->dimensions; } public function setDimensions(?string $value): static { $this->dimensions = $value === null ? null : trim($value); return $this; }

    public function getPrice(): ?string { return $this->price; } public function setPrice(?string $value): static { $this->price = $value === null ? null : trim((string)$value); return $this; }

    public function getCurrency(): string { return $this->currency; } public function setCurrency(string $value): static { $this->currency = $value; return $this; }

    public function getStatus(): string { return $this->status; } public function setStatus(string $value): static { $this->status = $value; return $this; }

    public function isDailyFeatured(): bool { return $this->dailyFeatured; }
    public function setDailyFeatured(bool $dailyFeatured): static { $this->dailyFeatured = $dailyFeatured; return $this; }

    /** @return Collection<int, ArtworkImage> */ public function getImages(): Collection { return $this->images; }

    /** @return Collection<int, Favorite> */
    public function getFavorites(): Collection { return $this->favorites; }

    public function addFavorite(Favorite $favorite): static
    {
        if (!$this->favorites->contains($favorite)) {
            $this->favorites->add($favorite);
            $favorite->setArtwork($this);
        }

        return $this;
    }

    public function removeFavorite(Favorite $favorite): static
    {
        $this->favorites->removeElement($favorite);

        return $this;
    }

    /** @return Collection<int, ArtworkColor> */
    public function getColors(): Collection
    {
        return $this->colors;
    }

    public function addColor(ArtworkColor $color): static
    {
        if (!$this->colors->contains($color)) {
            $this->colors->add($color);
        }

        return $this;
    }

    public function removeColor(ArtworkColor $color): static
    {
        $this->colors->removeElement($color);

        return $this;
    }

    /** @return Collection<int, ArtworkColorAnalysis> */
    public function getColorAnalyses(): Collection
    {
        return $this->colorAnalyses;
    }

    public function addColorAnalysis(ArtworkColorAnalysis $analysis): static
    {
        if (!$this->colorAnalyses->contains($analysis)) {
            $this->colorAnalyses->add($analysis);
            $analysis->setArtwork($this);
        }

        return $this;
    }

    public function removeColorAnalysis(ArtworkColorAnalysis $analysis): static
    {
        $this->colorAnalyses->removeElement($analysis);

        return $this;
    }

    public function addImage(ArtworkImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setArtwork($this);
        }

        return $this;
    }

    public function removeImage(ArtworkImage $image): static
    {
        if ($this->images->removeElement($image) && $image->getArtwork() === $this) {
            $image->setArtwork(null);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; } public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function __toString(): string { return $this->title; }
}
