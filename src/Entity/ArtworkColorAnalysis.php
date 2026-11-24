<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use App\Repository\ArtworkColorAnalysisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArtworkColorAnalysisRepository::class)]
#[ORM\Table(name: 'artwork_color_analysis')]
#[ORM\UniqueConstraint(name: 'uniq_artwork_color_analysis_color', columns: ['artwork_id', 'color_id'])]
#[ORM\Index(name: 'idx_artwork_color_analysis_color', columns: ['color_id'])]
class ArtworkColorAnalysis
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'colorAnalyses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Artwork $artwork = null;

    #[ORM\ManyToOne(inversedBy: 'analyses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?ArtworkColor $color = null;

    #[ORM\Column(length: 7)]
    private string $hexCode;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $percentage;

    #[ORM\Column(length: 64)]
    private string $sourceHash;

    #[ORM\Column]
    private \DateTimeImmutable $analyzedAt;

    public function __construct()
    {
        $this->analyzedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArtwork(): ?Artwork
    {
        return $this->artwork;
    }

    public function setArtwork(?Artwork $artwork): static
    {
        $this->artwork = $artwork;

        return $this;
    }

    public function getColor(): ?ArtworkColor
    {
        return $this->color;
    }

    public function setColor(?ArtworkColor $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getHexCode(): string
    {
        return $this->hexCode;
    }

    public function setHexCode(string $hexCode): static
    {
        $this->hexCode = strtoupper($hexCode);

        return $this;
    }

    public function getPercentage(): string
    {
        return $this->percentage;
    }

    public function setPercentage(string|float $percentage): static
    {
        $this->percentage = number_format((float) $percentage, 2, '.', '');

        return $this;
    }

    public function getSourceHash(): string
    {
        return $this->sourceHash;
    }

    public function setSourceHash(string $sourceHash): static
    {
        $this->sourceHash = $sourceHash;

        return $this;
    }

    public function getAnalyzedAt(): \DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function setAnalyzedAt(\DateTimeImmutable $analyzedAt): static
    {
        $this->analyzedAt = $analyzedAt;

        return $this;
    }
}
