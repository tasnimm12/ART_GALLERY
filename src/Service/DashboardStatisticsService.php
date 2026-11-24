<?php

namespace App\Service;

use App\Entity\Artist;
use App\Entity\Artwork;
use App\Entity\Auction;
use App\Entity\Bid;
use App\Entity\Category;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DashboardStatisticsService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CacheInterface $cache
    ) {
    }

    public function getBasicCounts(): array
    {
        $ttl = 120;
        $key = 'dashboard_basic_counts_v2';

        return $this->cache->get($key, function (ItemInterface $item) use ($ttl) {
            $item->expiresAfter($ttl);

            $counts = [];
            $counts['users'] = (int) $this->em->getRepository(User::class)->count([]);
            $counts['artworks'] = (int) $this->em->getRepository(Artwork::class)->count([]);
            $counts['artists'] = (int) $this->em->getRepository(Artist::class)->count([]);
            $counts['categories'] = (int) $this->em->getRepository(Category::class)->count([]);
            $counts['orders'] = (int) $this->em->getRepository(Order::class)->count([]);
            $counts['activeAuctions'] = count($this->em->getRepository(Auction::class)->findActiveAuctions());
            $counts['lotsActive'] = $counts['activeAuctions'];
            $counts['unreadNotifications'] = (int) $this->em->getRepository(Notification::class)->count(['isRead' => false]);
            $counts['paidOrders'] = (int) $this->em->getRepository(Order::class)->count(['status' => 'PAID']);
            $counts['pendingPayments'] = (int) $this->em->getRepository(Payment::class)->count(['status' => 'PENDING']);

            $totalRevenue = (float) ($this->em->getRepository(Order::class)
                ->createQueryBuilder('o')
                ->select('SUM(o.totalAmount)')
                ->where('o.status = :paid')
                ->setParameter('paid', 'PAID')
                ->getQuery()
                ->getSingleScalarResult() ?? 0.0);

            if ($totalRevenue <= 0) {
                $totalRevenue = (float) ($this->em->getRepository(Order::class)
                    ->createQueryBuilder('o')
                    ->select('SUM(o.totalAmount)')
                    ->getQuery()
                    ->getSingleScalarResult() ?? 0.0);
            }

            $counts['totalRevenue'] = $totalRevenue;

            return $counts;
        });
    }

    public function getCatalogueStats(): array
    {
        $artworks = $this->em->getRepository(Artwork::class)->findAll();
        $total = count($artworks);
        $available = 0;
        $sold = 0;
        $auction = 0;
        $reserved = 0;

        foreach ($artworks as $art) {
            match ($art->getStatus()) {
                'AVAILABLE' => $available++,
                'SOLD' => $sold++,
                'AUCTION' => $auction++,
                'RESERVED' => $reserved++,
                default => $available++,
            };
        }

        return [
            'total' => $total,
            'available' => $available,
            'sold' => $sold,
            'auction' => $auction,
            'reserved' => $reserved,
            'availablePct' => $total > 0 ? round(($available / $total) * 100) : 0,
            'soldPct' => $total > 0 ? round(($sold / $total) * 100) : 0,
            'auctionPct' => $total > 0 ? round(($auction / $total) * 100) : 0,
        ];
    }

    public function getAdminAlerts(): array
    {
        $pendingPaymentsCount = (int) $this->em->getRepository(Payment::class)->count(['status' => 'PENDING']);
        $unreadNotifsCount = (int) $this->em->getRepository(Notification::class)->count(['isRead' => false]);
        
        // Artworks without photos or reserved
        $artworksToReview = (int) $this->em->getRepository(Artwork::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.photo IS NULL OR a.photo = :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        // Auctions ended without order or lots to finalize
        $endedAuctions = (int) $this->em->getRepository(Auction::class)
            ->createQueryBuilder('auc')
            ->select('COUNT(auc.id)')
            ->where('auc.endDate <= :now AND auc.status = :active')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('active', 'ACTIVE')
            ->getQuery()
            ->getSingleScalarResult();

        $alerts = [];
        if ($pendingPaymentsCount > 0) {
            $alerts[] = [
                'type' => 'payment',
                'title' => 'Paiement en attente',
                'count' => $pendingPaymentsCount,
                'label' => $pendingPaymentsCount . ' commande' . ($pendingPaymentsCount > 1 ? 's' : '') . ' en attente de règlement',
                'severity' => 'warning',
            ];
        }

        if ($artworksToReview > 0) {
            $alerts[] = [
                'type' => 'artwork',
                'title' => 'Validation d\'œuvre',
                'count' => $artworksToReview,
                'label' => $artworksToReview . ' œuvre' . ($artworksToReview > 1 ? 's' : '') . ' sans visuel au catalogue',
                'severity' => 'info',
            ];
        }

        if ($endedAuctions > 0) {
            $alerts[] = [
                'type' => 'auction',
                'title' => 'Lot à finaliser',
                'count' => $endedAuctions,
                'label' => $endedAuctions . ' enchère' . ($endedAuctions > 1 ? 's' : '') . ' arrivée' . ($endedAuctions > 1 ? 's' : '') . ' à terme',
                'severity' => 'warning',
            ];
        }

        if ($unreadNotifsCount > 0) {
            $alerts[] = [
                'type' => 'notification',
                'title' => 'Notifications urgentes',
                'count' => $unreadNotifsCount,
                'label' => $unreadNotifsCount . ' notification' . ($unreadNotifsCount > 1 ? 's' : '') . ' non traitée' . ($unreadNotifsCount > 1 ? 's' : ''),
                'severity' => 'urgent',
            ];
        }

        $totalItems = $pendingPaymentsCount + $artworksToReview + $endedAuctions + ($unreadNotifsCount > 0 ? 1 : 0);

        return [
            'totalCount' => $totalItems,
            'items' => $alerts,
        ];
    }

    public function getRevenueSeries(\DateTimeInterface $from, \DateTimeInterface $to, string $groupBy = 'day'): array
    {
        $ttl = 120;
        $key = sprintf('dashboard_revenue_series_%s_%s_%s', $groupBy, $from->format('YmdHis'), $to->format('YmdHis'));

        return $this->cache->get($key, function (ItemInterface $item) use ($ttl, $from, $to, $groupBy) {
            $item->expiresAfter($ttl);

            $meta = $this->em->getClassMetadata(Order::class);
            $table = $meta->getTableName();
            $createdCol = $meta->getColumnName('createdAt');
            $amountCol = $meta->getColumnName('totalAmount');

            $conn = $this->em->getConnection();
            $platform = $conn->getDatabasePlatform();

            $qTable = $conn->quoteIdentifier($table);
            $qCreated = $conn->quoteIdentifier($createdCol);
            $qAmount = $conn->quoteIdentifier($amountCol);

            if ($groupBy === 'month') {
                if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
                    $periodExpr = "strftime('%Y-%m', $qCreated)";
                } else {
                    $periodExpr = "DATE_FORMAT($qCreated, '%Y-%m')";
                }
            } else {
                if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
                    $periodExpr = "date($qCreated)";
                } else {
                    $periodExpr = "DATE($qCreated)";
                }
            }

            $sql = sprintf(
                'SELECT %s as period, SUM(%s) as revenue, COUNT(*) as order_count FROM %s WHERE %s BETWEEN :from AND :to GROUP BY period ORDER BY period ASC',
                $periodExpr,
                $qAmount,
                $qTable,
                $qCreated
            );
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('from', $from->format('Y-m-d H:i:s'));
            $stmt->bindValue('to', $to->format('Y-m-d H:i:s'));
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            if ($groupBy === 'month') {
                $periodKeyFormat = 'Y-m';
                $labelFormat = 'M Y';
                $interval = new \DateInterval('P1M');
                $period = new \DateTimeImmutable($from->format('Y-m-01'));
                $toNormalized = new \DateTimeImmutable($to->format('Y-m-01'));
            } else {
                $periodKeyFormat = 'Y-m-d';
                $labelFormat = 'd/m';
                $interval = new \DateInterval('P1D');
                $period = new \DateTimeImmutable($from->format('Y-m-d'));
                $toNormalized = new \DateTimeImmutable($to->format('Y-m-d'));
            }

            $map = [];
            $ordersMap = [];
            foreach ($rows as $r) {
                $map[$r['period']] = (float) $r['revenue'];
                $ordersMap[$r['period']] = (int) ($r['order_count'] ?? 0);
            }

            $series = [];
            for ($p = $period; $p <= $toNormalized; $p = $p->add($interval)) {
                $key = $p->format($periodKeyFormat);
                $series[] = [
                    'key' => $key,
                    'label' => $p->format($labelFormat),
                    'fullDate' => $p->format('d/m/Y'),
                    'value' => $map[$key] ?? 0.0,
                    'orders' => $ordersMap[$key] ?? 0,
                ];
            }

            return $series;
        });
    }

    public function getOrdersSeries(\DateTimeInterface $from, \DateTimeInterface $to, string $groupBy = 'day'): array
    {
        $meta = $this->em->getClassMetadata(Order::class);
        $table = $meta->getTableName();
        $idCol = $meta->getSingleIdentifierColumnName();
        $createdCol = $meta->getColumnName('createdAt');

        $conn = $this->em->getConnection();
        $platform = $conn->getDatabasePlatform();

        $qTable = $conn->quoteIdentifier($table);
        $qCreated = $conn->quoteIdentifier($createdCol);
        $qId = $conn->quoteIdentifier($idCol);

        if ($groupBy === 'month') {
            if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
                $periodExpr = "strftime('%Y-%m', $qCreated)";
            } else {
                $periodExpr = "DATE_FORMAT($qCreated, '%Y-%m')";
            }
        } else {
            if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
                $periodExpr = "date($qCreated)";
            } else {
                $periodExpr = "DATE($qCreated)";
            }
        }

        $sql = sprintf('SELECT %s as period, COUNT(%s) as cnt FROM %s WHERE %s BETWEEN :from AND :to GROUP BY period ORDER BY period ASC', $periodExpr, $qId, $qTable, $qCreated);
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('from', $from->format('Y-m-d H:i:s'));
        $stmt->bindValue('to', $to->format('Y-m-d H:i:s'));
        $rows = $stmt->executeQuery()->fetchAllAssociative();

        if ($groupBy === 'month') {
            $periodKeyFormat = 'Y-m';
            $interval = new \DateInterval('P1M');
            $period = new \DateTimeImmutable($from->format('Y-m-01'));
            $toNormalized = new \DateTimeImmutable($to->format('Y-m-01'));
        } else {
            $periodKeyFormat = 'Y-m-d';
            $interval = new \DateInterval('P1D');
            $period = new \DateTimeImmutable($from->format('Y-m-d'));
            $toNormalized = new \DateTimeImmutable($to->format('Y-m-d'));
        }

        $map = [];
        foreach ($rows as $r) {
            $map[$r['period']] = (int) $r['cnt'];
        }

        $series = [];
        for ($p = $period; $p <= $toNormalized; $p = $p->add($interval)) {
            $key = $p->format($periodKeyFormat);
            $series[] = ['label' => $key, 'value' => $map[$key] ?? 0];
        }

        return $series;
    }

    public function getTransactionBreakdown(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('o.source as src, COUNT(o.id) as cnt, SUM(o.totalAmount) as sum')
            ->from(Order::class, 'o')
            ->groupBy('o.source');

        if ($from !== null && $to !== null) {
            $qb->andWhere('o.createdAt BETWEEN :from AND :to')
                ->setParameter('from', $from)
                ->setParameter('to', $to);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $out = [];
        $totalCount = 0;
        $totalAmount = 0.0;
        foreach ($rows as $r) {
            $out[$r['src']] = ['count' => (int) $r['cnt'], 'amount' => (float) $r['sum']];
            $totalCount += (int) $r['cnt'];
            $totalAmount += (float) $r['sum'];
        }

        return ['bySource' => $out, 'totalCount' => $totalCount, 'totalAmount' => $totalAmount];
    }

    public function getAuctionStats(): array
    {
        $auctionRepo = $this->em->getRepository(Auction::class);

        $active = count($auctionRepo->findActiveAuctions());
        $upcoming = count($auctionRepo->findUpcomingAuctions());
        $past = count($auctionRepo->findPastAuctions());

        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(b.id) as totalBids, AVG(b.amount) as avgBid, MAX(b.amount) as maxBid')
            ->from(Bid::class, 'b')
            ->where('b.status = :status')
            ->setParameter('status', 'VALID');

        $bids = $qb->getQuery()->getSingleResult();

        return [
            'active' => $active,
            'upcoming' => $upcoming,
            'past' => $past,
            'totalBids' => (int) ($bids['totalBids'] ?? 0),
            'avgBid' => (float) ($bids['avgBid'] ?? 0),
            'maxBid' => (float) ($bids['maxBid'] ?? 0),
        ];
    }

    public function getTopArtists(int $limit = 6): array
    {
        $artists = $this->em->getRepository(Artist::class)->findAll();
        $results = [];

        foreach ($artists as $artist) {
            $artworksCount = (int) $this->em->getRepository(Artwork::class)->count(['artist' => $artist]);
            $results[] = [
                'id' => $artist->getId(),
                'name' => $artist->getName(),
                'photo' => $artist->getPhoto(),
                'country' => $artist->getCountry() ?? 'International',
                'artworks_count' => $artworksCount,
                'status' => 'Actif',
            ];
        }

        usort($results, static fn($a, $b) => $b['artworks_count'] <=> $a['artworks_count']);

        return array_slice($results, 0, $limit);
    }

    public function getRecentActivity(int $limit = 8): array
    {
        $activity = [];

        // 1. Recent orders
        $orders = $this->em->getRepository(Order::class)->findBy([], ['createdAt' => 'DESC'], $limit);
        foreach ($orders as $o) {
            $activity[] = [
                'type' => 'order',
                'icon' => 'cart',
                'title' => 'Nouvelle commande',
                'description' => 'Commande #' . $o->getOrderNumber() . ' (' . number_format((float)$o->getTotalAmount(), 2, ',', ' ') . ' €)',
                'date' => $o->getCreatedAt(),
                'relativeTime' => self::formatRelativeTime($o->getCreatedAt()),
            ];
        }

        // 2. Recent payments
        $payments = $this->em->getRepository(Payment::class)->findBy([], ['createdAt' => 'DESC'], $limit);
        foreach ($payments as $p) {
            $activity[] = [
                'type' => 'payment',
                'icon' => 'card',
                'title' => 'Paiement reçu',
                'description' => number_format((float)$p->getAmount(), 2, ',', ' ') . ' € — ' . ($p->getOrder() ? '#' . $p->getOrder()->getOrderNumber() : 'Règlement'),
                'date' => $p->getCreatedAt(),
                'relativeTime' => self::formatRelativeTime($p->getCreatedAt()),
            ];
        }

        // 3. Recent users
        $users = $this->em->getRepository(User::class)->findBy([], ['createdAt' => 'DESC'], $limit);
        foreach ($users as $u) {
            $activity[] = [
                'type' => 'user',
                'icon' => 'user',
                'title' => 'Utilisateur inscrit',
                'description' => $u->getFirstName() . ' ' . $u->getLastName() . ' (' . $u->getEmail() . ')',
                'date' => $u->getCreatedAt(),
                'relativeTime' => self::formatRelativeTime($u->getCreatedAt()),
            ];
        }

        // 4. Recent artworks
        $artworks = $this->em->getRepository(Artwork::class)->findBy([], ['createdAt' => 'DESC'], $limit);
        foreach ($artworks as $a) {
            $activity[] = [
                'type' => 'artwork',
                'icon' => 'palette',
                'title' => 'Œuvre ajoutée',
                'description' => '« ' . $a->getTitle() . ' » — ' . ($a->getArtist() ? $a->getArtist()->getName() : 'Artiste'),
                'date' => $a->getCreatedAt(),
                'relativeTime' => self::formatRelativeTime($a->getCreatedAt()),
            ];
        }

        // 5. Recent bids
        $bids = $this->em->getRepository(Bid::class)->findBy([], ['createdAt' => 'DESC'], $limit);
        foreach ($bids as $b) {
            $activity[] = [
                'type' => 'bid',
                'icon' => 'gavel',
                'title' => 'Nouvelle offre',
                'description' => number_format((float)$b->getAmount(), 2, ',', ' ') . ' € sur le lot #' . ($b->getAuction() ? str_pad((string)$b->getAuction()->getId(), 3, '0', STR_PAD_LEFT) : 'Enchère'),
                'date' => $b->getCreatedAt(),
                'relativeTime' => self::formatRelativeTime($b->getCreatedAt()),
            ];
        }

        usort($activity, static fn($a, $b) => $b['date'] <=> $a['date']);
        return array_slice($activity, 0, $limit);
    }

    public static function formatRelativeTime(\DateTimeInterface $date): string
    {
        $diff = time() - $date->getTimestamp();

        if ($diff < 60) {
            return "À l'instant";
        }

        $minutes = (int) floor($diff / 60);
        if ($minutes < 60) {
            return sprintf('Il y a %d min', $minutes);
        }

        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return sprintf('Il y a %d h', $hours);
        }

        $days = (int) floor($hours / 24);
        if ($days < 30) {
            return sprintf('Il y a %d j', $days);
        }

        return $date->format('d/m/Y');
    }
}
