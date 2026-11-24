<?php

namespace App\Service\AI;

use App\Entity\Artwork;
use App\Repository\ArtworkRepository;

final class AIRecommendationService
{
    public function __construct(
        private readonly ArtworkRepository $artworkRepository,
        private readonly AIArtworkContextService $contextService,
    ) {
    }

    /**
     * @return Artwork[]
     */
    public function recommend(string $message, int $limit = 3): array
    {
        $artworks = $this->contextService->findRelevantArtworks($message, $limit);

        if ($artworks !== []) {
            return $artworks;
        }

        return $this->artworkRepository->createQueryBuilder('a')
            ->leftJoin('a.artist', 'artist')
            ->addSelect('artist')
            ->leftJoin('a.category', 'category')
            ->addSelect('category')
            ->where('a.status = :available')
            ->setParameter('available', 'AVAILABLE')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
