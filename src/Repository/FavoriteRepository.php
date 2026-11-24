<?php

namespace App\Repository;

use App\Entity\Artwork;
use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * @param list<int> $artworkIds
     *
     * @return list<int>
     */
    public function findArtworkIdsForUserAndArtworkIds(User $user, array $artworkIds): array
    {
        if ($artworkIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('favorite')
            ->select('IDENTITY(favorite.artwork) AS artworkId')
            ->andWhere('favorite.user = :user')
            ->andWhere('IDENTITY(favorite.artwork) IN (:artworkIds)')
            ->setParameter('user', $user)
            ->setParameter('artworkIds', $artworkIds)
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'artworkId'));
    }

    /**
     * @return list<Favorite>
     */
    public function findAllForUserWithArtwork(User $user): array
    {
        return $this->createQueryBuilder('favorite')
            ->addSelect('artwork', 'artist', 'category', 'image')
            ->innerJoin('favorite.artwork', 'artwork')
            ->leftJoin('artwork.artist', 'artist')
            ->leftJoin('artwork.category', 'category')
            ->leftJoin('artwork.images', 'image')
            ->andWhere('favorite.user = :user')
            ->setParameter('user', $user)
            ->orderBy('favorite.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('favorite')
            ->select('COUNT(favorite.id)')
            ->andWhere('favorite.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForUserAndArtwork(User $user, Artwork $artwork): ?Favorite
    {
        return $this->findOneBy(['user' => $user, 'artwork' => $artwork]);
    }
}
