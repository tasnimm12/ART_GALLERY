<?php

namespace App\Twig;

use App\Entity\Artist;
use App\Entity\Category;
use App\Repository\ArtistRepository;
use App\Repository\CategoryRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class CatalogExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ArtistRepository $artistRepository
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'catalog_categories' => $this->categoryRepository->findAll(),
            'catalog_artists' => $this->artistRepository->findAll(),
        ];
    }
}
