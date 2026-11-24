<?php

namespace App\Repository;

use App\Entity\Artwork;
use App\Entity\ArtworkColorAnalysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArtworkColorAnalysis>
 */
class ArtworkColorAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArtworkColorAnalysis::class);
    }

    /**
     * @return list<ArtworkColorAnalysis>
     */
    public function findForArtwork(Artwork $artwork): array
    {
        return $this->createQueryBuilder('analysis')
            ->addSelect('color')
            ->join('analysis.color', 'color')
            ->andWhere('analysis.artwork = :artwork')
            ->setParameter('artwork', $artwork)
            ->orderBy('analysis.percentage', 'DESC')
            ->addOrderBy('color.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
