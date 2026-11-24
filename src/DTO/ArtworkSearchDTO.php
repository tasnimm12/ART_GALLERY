<?php

namespace App\DTO;

use App\Entity\Artist;
use App\Entity\Category;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ArtworkSearchDTO
{
    public ?string $q = null;
    public ?Category $category = null;
    public ?Artist $artist = null;
    public ?float $minPrice = null;
    public ?float $maxPrice = null;
    public ?string $status = null;
    public ?string $color = null;
    public ?string $sort = 'newest';

    #[Assert\Callback]
    public function validatePriceRange(ExecutionContextInterface $context): void
    {
        if ($this->minPrice !== null && $this->maxPrice !== null && $this->minPrice > $this->maxPrice) {
            $context->buildViolation('Le prix minimum doit être inférieur ou égal au prix maximum.')
                ->atPath('maxPrice')
                ->addViolation();
        }
    }
}
