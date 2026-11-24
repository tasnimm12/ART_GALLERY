<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'artwork_image')]
class ArtworkImage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'images')] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Artwork $artwork = null;
    #[ORM\Column(length: 500)] private ?string $filePath = null;
    #[ORM\Column(length: 255, nullable: true)] #[Assert\Length(max: 255)] private ?string $altText = null;
    #[ORM\Column(options: ['default' => false])] private bool $isPrimary = false;
    #[ORM\Column(options: ['default' => 0])] #[Assert\PositiveOrZero] private int $displayOrder = 0;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    private ?File $imageFile = null;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getArtwork(): ?Artwork { return $this->artwork; } public function setArtwork(?Artwork $value): static { $this->artwork = $value; return $this; }
    public function getFilePath(): ?string { return $this->filePath; } public function setFilePath(?string $value): static { $this->filePath = $value; return $this; }
    public function getAltText(): ?string { return $this->altText; } public function setAltText(?string $value): static { $this->altText = $value; return $this; }
    public function isPrimary(): bool { return $this->isPrimary; } public function setIsPrimary(bool $value): static { $this->isPrimary = $value; return $this; }
    public function getDisplayOrder(): int { return $this->displayOrder; } public function setDisplayOrder(int $value): static { $this->displayOrder = $value; return $this; }
    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $imageFile): static { $this->imageFile = $imageFile; return $this; }

    #[Assert\Callback]
    public function validateImage(ExecutionContextInterface $context): void
    {
        if ($this->filePath === null && $this->imageFile === null) {
            $context->buildViolation('Ajoutez un fichier image.')
                ->atPath('imageFile')
                ->addViolation();
        }
    }
}
