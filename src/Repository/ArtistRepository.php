<?php

namespace App\Repository;

use App\Entity\Artist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Artist>
 */
class ArtistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Artist::class);
    }

    /**
     * @return array<array{artist: Artist, artworksCount: int}>
     */
    public function findAllWithArtworksCount(): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a AS artist, COUNT(w.id) AS artworksCount')
            ->leftJoin('App\Entity\Artwork', 'w', 'ON', 'w.artist = a')
            ->groupBy('a.id')
            ->orderBy('a.name', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
