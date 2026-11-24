<?php

namespace App\Repository;

use App\Entity\Auction;
use App\Enum\AuctionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Auction>
 */
class AuctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Auction::class);
    }

    /**
     * Les dates déterminent les enchères en cours ; les statuts terminaux et closedAt priment.
     *
     * @return list<Auction>
     */
    public function findActiveAuctions(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('a')
            ->innerJoin('a.artwork', 'art')
            ->addSelect('art')
            ->innerJoin('art.artist', 'artist')
            ->addSelect('artist')
            ->leftJoin('art.images', 'img')
            ->addSelect('img')
            ->where('a.startDate <= :now')
            ->andWhere('a.endDate >= :now')
            ->andWhere('a.status NOT IN (:terminalStatuses)')
            ->andWhere('a.closedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('terminalStatuses', [AuctionStatus::CANCELLED->value, AuctionStatus::ENDED->value])
            ->orderBy('a.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Auction>
     */
    public function findUpcomingAuctions(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('a')
            ->innerJoin('a.artwork', 'art')
            ->addSelect('art')
            ->innerJoin('art.artist', 'artist')
            ->addSelect('artist')
            ->leftJoin('art.images', 'img')
            ->addSelect('img')
            ->where('a.startDate > :now')
            ->andWhere('a.status NOT IN (:terminalStatuses)')
            ->andWhere('a.closedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('terminalStatuses', [AuctionStatus::CANCELLED->value, AuctionStatus::ENDED->value])
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Enchères expirées (endDate < now) mais pas encore clôturées — utilisée par CloseExpiredAuctionsCommand.
     *
     * @return list<Auction>
     */
    public function findExpiredUnclosed(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('a')
            ->leftJoin('a.artwork', 'art')
            ->addSelect('art')
            ->leftJoin('art.artist', 'artist')
            ->addSelect('artist')
            ->where('a.endDate < :now')
            ->andWhere('a.closedAt IS NULL')
            ->andWhere('a.status NOT IN (:excluded)')
            ->setParameter('now', $now)
            ->setParameter('excluded', [AuctionStatus::ENDED->value, AuctionStatus::CANCELLED->value])
            ->orderBy('a.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Auction>
     */
    public function findPastAuctions(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('a')
            ->innerJoin('a.artwork', 'art')
            ->addSelect('art')
            ->innerJoin('art.artist', 'artist')
            ->addSelect('artist')
            ->leftJoin('art.images', 'img')
            ->addSelect('img')
            ->where('(a.endDate < :now OR a.status = :ended OR a.closedAt IS NOT NULL)')
            ->andWhere('a.status != :cancelled')
            ->setParameter('now', $now)
            ->setParameter('ended', AuctionStatus::ENDED->value)
            ->setParameter('cancelled', AuctionStatus::CANCELLED->value)
            ->orderBy('a.endDate', 'DESC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();
    }
}
