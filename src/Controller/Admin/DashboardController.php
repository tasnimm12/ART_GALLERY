<?php

namespace App\Controller\Admin;

use App\Entity\Artist;
use App\Entity\Artwork;
use App\Entity\ArtworkColor;
use App\Entity\Auction;
use App\Entity\AuditLog;
use App\Entity\Category;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\ArtworkRepository;
use App\Repository\AuctionRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\DashboardStatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'app_admin_dashboard')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private UserRepository $userRepository,
        private ArtworkRepository $artworkRepository,
        private AuctionRepository $auctionRepository,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private DashboardStatisticsService $dashboardService,
        private RequestStack $requestStack,
    ) {
    }

    public function index(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request ? $request->query->get('period', '30d') : '30d';

        $now = new \DateTimeImmutable();
        switch ($period) {
            case 'today':
                $from = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
                $to = (new \DateTimeImmutable('now'))->setTime((int)date('H'), (int)date('i'), (int)date('s'));
                $groupBy = 'day';
                break;
            case '7d':
                $from = $now->sub(new \DateInterval('P6D'))->setTime(0, 0, 0);
                $to = $now;
                $groupBy = 'day';
                break;
            case '90d':
                $from = $now->sub(new \DateInterval('P89D'))->setTime(0, 0, 0);
                $to = $now;
                $groupBy = 'day';
                break;
            case '365d':
                $from = $now->sub(new \DateInterval('P364D'))->setTime(0, 0, 0);
                $to = $now;
                $groupBy = 'month';
                break;
            case '30d':
            default:
                $from = $now->sub(new \DateInterval('P29D'))->setTime(0, 0, 0);
                $to = $now;
                $groupBy = 'day';
                break;
        }

        // 8 Key KPI Counts
        $usersCount = $this->userRepository->count([]);
        $artworksCount = $this->artworkRepository->count([]);
        $artistsCount = $this->entityManager->getRepository(Artist::class)->count([]);
        $categoriesCount = $this->entityManager->getRepository(Category::class)->count([]);
        
        $activeAuctions = $this->auctionRepository->findActiveAuctions();
        $activeAuctionsCount = count($activeAuctions);
        $lotsCount = $activeAuctionsCount; // Available auction lots
        
        $ordersCount = $this->orderRepository->count([]);
        $paidOrdersCount = $this->orderRepository->count(['status' => 'PAID']);
        $pendingPaymentsCount = $this->entityManager->getRepository(Payment::class)->count(['status' => 'PENDING']);
        $unreadNotificationsCount = $this->entityManager->getRepository(Notification::class)->count(['isRead' => false]);

        $totalRevenue = (float) ($this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0.0);

        // Six primary dashboard KPIs
        $kpiCards = [
            [
                'key' => 'users',
                'title' => 'UTILISATEURS',
                'value' => number_format($usersCount, 0, ',', ' '),
                'subtitle' => 'Utilisateurs enregistrés',
                'icon' => 'users',
                'controller' => UserCrudController::class,
            ],
            [
                'key' => 'artworks',
                'title' => 'ŒUVRES',
                'value' => number_format($artworksCount, 0, ',', ' '),
                'subtitle' => 'Œuvres au catalogue',
                'icon' => 'palette',
                'controller' => ArtworkCrudController::class,
            ],
            [
                'key' => 'artists',
                'title' => 'ARTISTES',
                'value' => number_format($artistsCount, 0, ',', ' '),
                'subtitle' => 'Artistes actifs',
                'icon' => 'paint-brush',
                'controller' => ArtistCrudController::class,
            ],
            [
                'key' => 'auctions',
                'title' => 'ENCHÈRES ACTIVES',
                'value' => number_format($activeAuctionsCount, 0, ',', ' '),
                'subtitle' => 'Enchères en direct',
                'icon' => 'gavel',
                'route' => 'app_admin_auction_index',
            ],
            [
                'key' => 'orders',
                'title' => 'COMMANDES',
                'value' => number_format($ordersCount, 0, ',', ' '),
                'subtitle' => 'Commandes totales',
                'icon' => 'shopping-cart',
                'controller' => OrderCrudController::class,
            ],
            [
                'key' => 'revenue',
                'title' => 'CHIFFRE D\'AFFAIRES',
                'value' => number_format($totalRevenue, 2, ',', ' ') . ' €',
                'subtitle' => 'Ventes générées',
                'icon' => 'coins',
                'controller' => OrderCrudController::class,
            ],
        ];

        // Detailed sections data
        $revenueSeries = $this->dashboardService->getRevenueSeries($from, $to, $groupBy);
        $ordersSeries = $this->dashboardService->getOrdersSeries($from, $to, $groupBy);
        $recentActivity = $this->dashboardService->getRecentActivity(8);
        $catalogueStats = $this->dashboardService->getCatalogueStats();
        $topArtists = $this->dashboardService->getTopArtists(6);
        $adminAlerts = $this->dashboardService->getAdminAlerts();
        $auctionStats = $this->dashboardService->getAuctionStats();

        // Recent orders with items & customer
        $recentOrders = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')->addSelect('u')
            ->leftJoin('o.items', 'i')->addSelect('i')
            ->leftJoin('i.artwork', 'a')->addSelect('a')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        // Detailed auctions list (active + recent)
        $detailedAuctions = $this->entityManager->getRepository(Auction::class)
            ->createQueryBuilder('auc')
            ->leftJoin('auc.artwork', 'art')->addSelect('art')
            ->leftJoin('art.artist', 'artist')->addSelect('artist')
            ->leftJoin('auc.winner', 'w')->addSelect('w')
            ->orderBy('auc.startDate', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        // Compute max revenue for chart scaling
        $revenueMax = 0.0;
        if (!empty($revenueSeries)) {
            $vals = array_column($revenueSeries, 'value');
            $revenueMax = max($vals);
        }

        return $this->render('admin/dashboard.html.twig', [
            'period' => $period,
            'periodFrom' => $from,
            'periodTo' => $to,
            'groupBy' => $groupBy,
            'kpiCards' => $kpiCards,
            'usersCount' => $usersCount,
            'artworksCount' => $artworksCount,
            'artistsCount' => $artistsCount,
            'categoriesCount' => $categoriesCount,
            'activeAuctionsCount' => $activeAuctionsCount,
            'lotsCount' => $lotsCount,
            'ordersCount' => $ordersCount,
            'totalRevenue' => $totalRevenue,
            'paidOrdersCount' => $paidOrdersCount,
            'pendingPaymentsCount' => $pendingPaymentsCount,
            'unreadNotificationsCount' => $unreadNotificationsCount,
            'revenueSeries' => $revenueSeries,
            'ordersSeries' => $ordersSeries,
            'revenueMax' => $revenueMax,
            'recentActivity' => $recentActivity,
            'catalogueStats' => $catalogueStats,
            'topArtists' => $topArtists,
            'adminAlerts' => $adminAlerts,
            'auctionStats' => $auctionStats,
            'recentOrders' => $recentOrders,
            'detailedAuctions' => $detailedAuctions,
            'orderCrudControllerFqcn' => OrderCrudController::class,
            'artworkCrudControllerFqcn' => ArtworkCrudController::class,
            'artistCrudControllerFqcn' => ArtistCrudController::class,
            'categoryCrudControllerFqcn' => CategoryCrudController::class,
            'userCrudControllerFqcn' => UserCrudController::class,
            'paymentCrudControllerFqcn' => PaymentCrudController::class,
            'notificationCrudControllerFqcn' => NotificationCrudController::class,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Abstract Art Gallery')
            ->setFaviconPath('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 128 128%22><text y=%221.2em%22 font-size=%2296%22>🎨</text></svg>')
            ->renderContentMaximized()
            ->disableDarkMode();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('styles/admin.css')
            ->addAssetMapperEntry('app')
            ->addAssetMapperEntry('admin-artwork-table');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user instanceof User ? $user->getFirstName() . ' ' . $user->getLastName() : $user->getUserIdentifier())
            ->displayUserName(true)
            ->displayUserAvatar(true);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('CATALOGUE');
        yield MenuItem::linkToCrud('Œuvres', 'fa fa-palette', Artwork::class);
        yield MenuItem::linkToCrud('Couleurs', 'fa fa-swatchbook', ArtworkColor::class);
        yield MenuItem::linkToCrud('Artistes', 'fa fa-paint-brush', Artist::class);
        yield MenuItem::linkToCrud('Catégories', 'fa fa-tags', Category::class);

        yield MenuItem::section('VENTES');
        yield MenuItem::linkToRoute('Enchères', 'fa fa-gavel', 'app_admin_auction_index');
        yield MenuItem::linkToCrud('Commandes', 'fa fa-shopping-cart', Order::class);
        yield MenuItem::linkToCrud('Paiements', 'fa fa-credit-card', Payment::class);

        yield MenuItem::section('UTILISATEURS');
        yield MenuItem::linkToCrud('Clients', 'fa fa-users', User::class)
            ->setQueryParameter('filters[role][comparison]', '=')
            ->setQueryParameter('filters[role][value]', 'ROLE_CLIENT');
        yield MenuItem::linkToCrud('Administrateurs', 'fa fa-user-shield', User::class)
            ->setQueryParameter('filters[role][comparison]', '=')
            ->setQueryParameter('filters[role][value]', 'ROLE_ADMIN');

        yield MenuItem::section('SYSTÈME');
        yield MenuItem::linkToCrud('Notifications', 'fa fa-bell', Notification::class);
        yield MenuItem::linkToCrud('Historique', 'fa fa-history', AuditLog::class);
    }
}
