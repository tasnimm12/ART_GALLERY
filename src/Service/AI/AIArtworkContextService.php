<?php

namespace App\Service\AI;

use App\Entity\Artwork;
use App\Entity\ArtworkColorAnalysis;
use App\Repository\ArtworkRepository;

final class AIArtworkContextService
{
    public function __construct(private readonly ArtworkRepository $artworkRepository)
    {
    }

    /**
     * @return Artwork[]
     */
    public function findRelevantArtworks(string $message, int $limit = 4): array
    {
        $qb = $this->artworkRepository->createQueryBuilder('a')
            ->leftJoin('a.artist', 'artist')
            ->addSelect('artist')
            ->leftJoin('a.category', 'category')
            ->addSelect('category')
            ->where('a.status = :available')
            ->setParameter('available', 'AVAILABLE');

        $normalized = strtolower(trim($message));
        $maxPrice = $this->extractMaxPrice($normalized);
        $keywords = $this->extractKeywords($normalized);

        if ($maxPrice !== null) {
            $qb->andWhere('a.price IS NOT NULL')
                ->andWhere('CAST(a.price AS DECIMAL) <= :maxPrice')
                ->setParameter('maxPrice', (string) $maxPrice);
        }

        if ($keywords !== []) {
            $or = $qb->expr()->orX();

            foreach ($keywords as $index => $keyword) {
                $parameter = 'keyword_' . $index;
                $or->add('LOWER(a.title) LIKE :' . $parameter);
                $or->add('LOWER(a.description) LIKE :' . $parameter);
                $or->add('LOWER(category.name) LIKE :' . $parameter);
                $or->add('LOWER(artist.name) LIKE :' . $parameter);
                $qb->setParameter($parameter, '%' . $keyword . '%');
            }

            $qb->andWhere($or);
        }

        $qb->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildContext(string $message, int $limit = 6, ?Artwork $currentArtwork = null): array
    {
        $artworks = $this->findRelevantArtworks($message, $limit);

        if ($artworks === []) {
            $artworks = $this->artworkRepository->createQueryBuilder('a')
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

        $context = [];
        if ($currentArtwork !== null) {
            $context[] = $this->serializeArtwork($currentArtwork);
        }

        foreach ($artworks as $artwork) {
            if ($currentArtwork !== null && $artwork->getId() === $currentArtwork->getId()) {
                continue;
            }

            $context[] = $this->serializeArtwork($artwork);
        }

        return $context;
    }

    /**
     * @return array{
     *     id: int|null,
     *     title: string,
     *     artist: string|null,
     *     category: string|null,
     *     description: string|null,
     *     technique: string|null,
     *     dimensions: string|null,
     *     price: string|null,
     *     currency: string,
     *     status: string,
     *     creation_date: string|null,
     *     colors: list<array{name: string, hex: string, percentage: float|null}>
     * }
     */
    private function serializeArtwork(Artwork $artwork): array
    {
        $colors = [];
        $colorNames = [];
        foreach ($artwork->getColorAnalyses() as $analysis) {
            if (!$analysis instanceof ArtworkColorAnalysis || $analysis->getColor() === null) {
                continue;
            }

            $colorName = $analysis->getColor()->getName();
            $colors[] = [
                'name' => $colorName,
                'hex' => $analysis->getHexCode(),
                'percentage' => (float) $analysis->getPercentage(),
            ];
            $colorNames[] = mb_strtolower($colorName);
        }

        foreach ($artwork->getColors() as $color) {
            if (in_array(mb_strtolower($color->getName()), $colorNames, true)) {
                continue;
            }

            $colors[] = [
                'name' => $color->getName(),
                'hex' => $color->getHex(),
                'percentage' => null,
            ];
            $colorNames[] = mb_strtolower($color->getName());
        }

        return [
            'id' => $artwork->getId(),
            'title' => $artwork->getTitle(),
            'artist' => $artwork->getArtist()?->getName(),
            'category' => $artwork->getCategory()?->getName(),
            'description' => $artwork->getDescription(),
            'technique' => $artwork->getTechnique(),
            'dimensions' => $artwork->getDimensions(),
            'price' => $artwork->getPrice(),
            'currency' => $artwork->getCurrency(),
            'status' => $artwork->getStatus(),
            'creation_date' => $artwork->getYearCreated()?->format('Y-m-d'),
            'colors' => $colors,
        ];
    }

    private function extractMaxPrice(string $message): ?float
    {
        if (preg_match('/(?:max(?:imum)?|under|moins de|budget|max budget|budget maximum|<=|<)\s*(\d{3,7})/i', $message, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match('/(\d{3,7})\s*(?:dt|dtn|tnd|tunisian dinar|dinars)/i', $message, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    private function extractKeywords(string $message): array
    {
        $stopWords = [
            'i', 'want', 'looking', 'need', 'for', 'a', 'an', 'the', 'my', 'our', 'with', 'without', 'and', 'or',
            'under', 'maximum', 'budget', 'more', 'less', 'than', 'please', 'show', 'me', 'artwork', 'works',
            'art', 'artist', 'gallery', 'piece', 'be', 'is', 'of', 'to', 'in', 'at', 'on', 'from', 'by', 'this',
            'that', 'than', 'about', 'around', 'maximum', 'up', 'to', 'do', 'does', 'je', 'cherche', 'œuvre',
            'oeuvre', 'oeuvres', 'quel', 'quelle', 'quelles', 'quelques', 'veux', 'trouver', 'mon', 'ma', 'bonjour',
        ];

        $clean = preg_replace('/[^a-z0-9\s\-_]/u', ' ', $message);
        if (!is_string($clean)) {
            return [];
        }

        $words = preg_split('/\s+/', strtolower(trim($clean)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = [];

        foreach ($words as $word) {
            $word = trim($word, "_-");
            if ($word === '' || in_array($word, $stopWords, true) || is_numeric($word)) {
                continue;
            }

            $keywords[] = $word;
        }

        $unique = array_values(array_unique($keywords));
        return array_slice($unique, 0, 6);
    }
}
