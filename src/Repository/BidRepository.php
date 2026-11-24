<?php

namespace App\Repository;

use App\Entity\Auction;
use App\Entity\Bid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bid>
 */
class BidRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bid::class);
    }

    /**
     * @return list<Bid>
     */
    public function findBidsForAuction(Auction $auction, int $limit = 20): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.user', 'u')
            ->addSelect('u')
            ->where('b.auction = :auction')
            ->setParameter('auction', $auction)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findHighestBid(Auction $auction): ?Bid
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.user', 'u')
            ->addSelect('u')
            ->where('b.auction = :auction')
            ->andWhere('b.status = :status')
            ->setParameter('auction', $auction)
            ->setParameter('status', 'VALID')
            ->orderBy('b.amount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
