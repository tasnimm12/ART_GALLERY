<?php

namespace App\Entity;

use App\Repository\ArtworkColorRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArtworkColorRepository::class)]
#[ORM\Table(name: 'artwork_color')]
#[ORM\UniqueConstraint(name: 'uniq_artwork_color_slug', fields: ['slug'])]
class ArtworkColor
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $name;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/')]
    private string $slug;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/')]
    private string $hex;

    /** @var Collection<int, ArtworkColorAnalysis> */
    #[ORM\OneToMany(mappedBy: 'color', targetEntity: ArtworkColorAnalysis::class)]
    private Collection $analyses;

    public function __construct()
    {
        $this->analyses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = trim($slug);

        return $this;
    }

    public function getHex(): string
    {
        return $this->hex;
    }

    /** @return Collection<int, ArtworkColorAnalysis> */
    public function getAnalyses(): Collection
    {
        return $this->analyses;
    }

    public function setHex(string $hex): static
    {
        $this->hex = strtoupper(trim($hex));

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
