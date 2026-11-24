<?php

namespace App\Repository;

use App\DTO\ArtworkSearchDTO;
use App\Entity\Artwork;
use App\Entity\ArtworkColorAnalysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;
use DateTimeZone;

/**
 * @extends ServiceEntityRepository<Artwork>
 */
class ArtworkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Artwork::class);
    }

    public function findDailyArtwork(?DateTimeImmutable $date = null): ?Artwork
    {
        $manualSelection = $this->createQueryBuilder('a')
            ->andWhere('a.dailyFeatured = true')
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($manualSelection instanceof Artwork) {
            return $this->fetchDailyArtwork($manualSelection->getId());
        }

        $artworkRows = $this->createQueryBuilder('a')
            ->select('a.id')
            ->leftJoin('a.images', 'image')
            ->andWhere('a.photo IS NOT NULL OR image.id IS NOT NULL')
            ->distinct()
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        if ($artworkRows === []) {
            $artworkRows = $this->createQueryBuilder('a')
                ->select('a.id')
                ->orderBy('a.id', 'ASC')
                ->getQuery()
                ->getScalarResult();
        }
        $artworkIds = array_column($artworkRows, 'id');

        if ($artworkIds === []) {
            return null;
        }

        $utcToday = $date ?? new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $dayNumber = intdiv((int) $utcToday->setTimezone(new DateTimeZone('UTC'))->format('U'), 86400);
        $dailyArtworkId = (int) $artworkIds[$dayNumber % count($artworkIds)];

        return $this->fetchDailyArtwork($dailyArtworkId);
    }

    /**
     * @return list<int>
     */
    public function findIdsWithLocalImageCandidates(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('DISTINCT a.id')
            ->leftJoin('a.images', 'image')
            ->andWhere('(a.photo IS NOT NULL AND a.photo NOT LIKE :remoteUrl) OR (image.filePath IS NOT NULL AND image.filePath NOT LIKE :remoteUrl)')
            ->setParameter('remoteUrl', '%://%')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'id'));
    }

    private function fetchDailyArtwork(?int $id): ?Artwork
    {
        if ($id === null) {
            return null;
        }

        return $this->createQueryBuilder('a')
            ->addSelect('artist', 'image')
            ->leftJoin('a.artist', 'artist')
            ->leftJoin('a.images', 'image')
            ->andWhere('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param array{colors?: list<string>, category?: ?string, minPrice?: ?float, maxPrice?: ?float} $criteria
     *
     * @return list<Artwork>
     */
    public function findAdvisorCandidates(array $criteria, int $limit = 500): array
    {
        $queryBuilder = $this->createQueryBuilder('artwork')
            ->distinct()
            ->addSelect('artist', 'category', 'image', 'analysis', 'analysisColor')
            ->leftJoin('artwork.artist', 'artist')
            ->leftJoin('artwork.category', 'category')
            ->leftJoin('artwork.images', 'image')
            ->leftJoin('artwork.colorAnalyses', 'analysis')
            ->leftJoin('analysis.color', 'analysisColor')
            ->andWhere('artwork.status = :advisorAvailable')
            ->setParameter('advisorAvailable', 'AVAILABLE');

        if (($criteria['minPrice'] ?? null) !== null) {
            $queryBuilder->andWhere('artwork.price IS NOT NULL')
                ->andWhere('artwork.price >= :advisorMinPrice')
                ->setParameter('advisorMinPrice', $criteria['minPrice']);
        }
        if (($criteria['maxPrice'] ?? null) !== null) {
            $queryBuilder->andWhere('artwork.price IS NOT NULL')
                ->andWhere('artwork.price <= :advisorMaxPrice')
                ->setParameter('advisorMaxPrice', $criteria['maxPrice']);
        }
        if (($criteria['category'] ?? null) !== null) {
            $queryBuilder->andWhere('LOWER(category.name) = :advisorCategory')
                ->setParameter('advisorCategory', mb_strtolower(trim($criteria['category'])));
        }
        if (($criteria['colors'] ?? []) !== []) {
            $colorMatch = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(ArtworkColorAnalysis::class, 'requestedAnalysis')
                ->join('requestedAnalysis.color', 'requestedColor')
                ->where('requestedAnalysis.artwork = artwork')
                ->andWhere('requestedColor.slug IN (:advisorColors)');
            $queryBuilder->andWhere($queryBuilder->expr()->exists($colorMatch->getDQL()))
                ->setParameter('advisorColors', $criteria['colors']);
        }

        return $queryBuilder
            ->orderBy('artwork.createdAt', 'DESC')
            ->addOrderBy('artwork.id', 'ASC')
            ->setMaxResults(max(1, min($limit, 500)))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items: Paginator, totalItems: int, displayedItems: int, totalPages: int, currentPage: int, limit: int}
     */
    public function findByFilters(ArtworkSearchDTO $filter, int $page = 1, int $limit = 9): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.artist', 'art')
            ->addSelect('art')
            ->leftJoin('a.category', 'cat')
            ->addSelect('cat')
            ->leftJoin('a.images', 'img')
            ->addSelect('img');

        if ($filter->q !== null && trim($filter->q) !== '') {
            $queryExpression = $qb->expr()->orX(
                'LOWER(a.title) LIKE :query',
                'LOWER(a.description) LIKE :query',
                'LOWER(a.technique) LIKE :query',
                'LOWER(art.name) LIKE :query',
            );
            $qb->andWhere($queryExpression)
                ->setParameter('query', '%' . mb_strtolower(trim($filter->q)) . '%');
        }

        if ($filter->category !== null) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $filter->category);
        }

        if ($filter->artist !== null) {
            $qb->andWhere('a.artist = :artist')
                ->setParameter('artist', $filter->artist);
        }

        if ($filter->minPrice !== null) {
            $qb->andWhere('a.price >= :minPrice')
                ->setParameter('minPrice', $filter->minPrice);
        }

        if ($filter->maxPrice !== null) {
            $qb->andWhere('a.price <= :maxPrice')
                ->setParameter('maxPrice', $filter->maxPrice);
        }

        if (!empty($filter->status)) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $filter->status);
        }

        if ($filter->color !== null && $filter->color !== '') {
            $qb->leftJoin('a.colors', 'manualColor')
                ->leftJoin('a.colorAnalyses', 'colorAnalysis')
                ->leftJoin('colorAnalysis.color', 'analysisColor')
                ->andWhere($qb->expr()->orX(
                    'manualColor.slug = :color',
                    'analysisColor.slug = :color',
                ))
                ->setParameter('color', $filter->color);
            $qb->distinct();
        }

        $sort = $filter->sort ?: 'newest';
        match ($sort) {
            'price_asc' => $qb->orderBy('a.price', 'ASC'),
            'price_desc' => $qb->orderBy('a.price', 'DESC'),
            'title_asc' => $qb->orderBy('a.title', 'ASC'),
            default => $qb->orderBy('a.createdAt', 'DESC'),
        };

        $page = max(1, $page);
        $firstResult = ($page - 1) * $limit;

        $qb->setFirstResult($firstResult)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);
        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);

        return [
            'items' => $paginator,
            'totalItems' => $totalItems,
            'displayedItems' => min($limit, max(0, $totalItems - $firstResult)),
            'totalPages' => max(1, $totalPages),
            'currentPage' => $page,
            'limit' => $limit,
        ];
    }
}
