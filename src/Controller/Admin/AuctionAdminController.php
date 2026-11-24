<?php

namespace App\Controller\Admin;

use App\Entity\Auction;
use App\Entity\AuditLog;
use App\Enum\AuctionStatus;
use App\Form\Admin\AuctionLotType;
use App\Repository\AuctionRepository;
use App\Repository\BidRepository;
use App\Service\AuctionOrderService;
use App\Service\AuctionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/auctions')]
#[IsGranted('ROLE_ADMIN')]
class AuctionAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuctionRepository $auctionRepository,
        private BidRepository $bidRepository,
        private AuctionService $auctionService,
        private AuctionOrderService $auctionOrderService,
    ) {
    }

    #[Route('', name: 'app_admin_auction_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = $request->query->get('filter', 'all');
        $now = new \DateTimeImmutable();

        $qb = $this->auctionRepository->createQueryBuilder('a')
            ->leftJoin('a.artwork', 'art')
            ->addSelect('art')
            ->leftJoin('art.artist', 'artist')
            ->addSelect('artist')
            ->leftJoin('a.winner', 'w')
            ->addSelect('w');

        match ($filter) {
            'active' => $qb
                ->where('a.status != :cancelled')
                ->andWhere('a.startDate <= :now')
                ->andWhere('a.endDate >= :now')
                ->setParameter('cancelled', AuctionStatus::CANCELLED->value)
                ->setParameter('now', $now),
            'scheduled' => $qb
                ->where('a.status != :cancelled')
                ->andWhere('a.startDate > :now')
                ->setParameter('cancelled', AuctionStatus::CANCELLED->value)
                ->setParameter('now', $now),
            'closed' => $qb
                ->where('a.status != :cancelled')
                ->andWhere('(a.closedAt IS NOT NULL OR a.endDate < :now OR a.status = :ended)')
                ->setParameter('cancelled', AuctionStatus::CANCELLED->value)
                ->setParameter('ended', AuctionStatus::ENDED->value)
                ->setParameter('now', $now),
            'cancelled' => $qb->where('a.status = :cancelled')->setParameter('cancelled', AuctionStatus::CANCELLED->value),
            default => null,
        };

        $auctions = $qb->orderBy('a.createdAt', 'DESC')->getQuery()->getResult();

        $counts = [
            'all' => $this->auctionRepository->count([]),
            'active' => (int) $this->auctionRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.status != :cancelled')
                ->andWhere('a.startDate <= :now')
                ->andWhere('a.endDate >= :now')
                ->setParameter('cancelled', AuctionStatus::CANCELLED->value)
                ->setParameter('now', $now)
                ->getQuery()
                ->getSingleScalarResult(),
            'scheduled' => (int) $this->auctionRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.status != :cancelled')
                ->andWhere('a.startDate > :now')
                ->setParameter('cancelled', AuctionStatus::CANCELLED->value)
                ->setParameter('now', $now)
                ->getQuery()
                ->getSingleScalarResult(),
            'closed' => (int) $this->auctionRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.status != :cancelled')
                ->andWhere('(a.closedAt IS NOT NULL OR a.endDate < :now OR a.status = :ended)')
                ->setParameter('cancelled', AuctionStatus::CANCELLED->value)
                ->setParameter('ended', AuctionStatus::ENDED->value)
                ->setParameter('now', $now)
                ->getQuery()
                ->getSingleScalarResult(),
            'cancelled' => $this->auctionRepository->count(['status' => AuctionStatus::CANCELLED->value]),
        ];

        return $this->render('admin/auction/index.html.twig', [
            'auctions' => $auctions,
            'currentFilter' => $filter,
            'counts' => $counts,
        ]);
    }

    #[Route('/new', name: 'app_admin_auction_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $auction = new Auction();
        $now = new \DateTimeImmutable();
        $auction->setStartDate($now);
        $auction->setEndDate($now->modify('+3 days'));
        $auction->setMinIncrement('10.00');

        $form = $this->createForm(AuctionLotType::class, $auction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $artwork = $auction->getArtwork();
            if ($artwork === null || $artwork->getStatus() !== 'AVAILABLE') {
                $this->addFlash('error', 'L\'œuvre sélectionnée n\'est pas disponible pour une mise aux enchères.');
                return $this->redirectToRoute('app_admin_auction_new');
            }

            // Dates validation
            if ($auction->getEndDate() <= $auction->getStartDate()) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                return $this->render('admin/auction/new.html.twig', ['form' => $form]);
            }

            // Prevent creating a second Auction for the same artwork when OneToOne mapping exists
            $existing = $this->auctionRepository->findOneBy(['artwork' => $artwork]);
            if ($existing !== null) {
                $this->addFlash('error', sprintf('Une enchère existe déjà pour cette œuvre (ID #%d). Modifiez l\'enchère existante ou supprimez-la pour en créer une nouvelle.', $existing->getId()));
                return $this->redirectToRoute('app_admin_auction_edit', ['id' => $existing->getId()]);
            }

            $auction->synchronizeStatusFromDates();

            // Mark artwork status as AUCTION
            $artwork->setStatus('AUCTION');

            $this->entityManager->persist($auction);

            // Audit Log
            $auditLog = new AuditLog();
            $auditLog->setUser($this->getUser())
                ->setAction('AUCTION_CREATED')
                ->setEntityType('Auction')
                ->setDetails([
                    'artworkId' => $artwork->getId(),
                    'artworkTitle' => $artwork->getTitle(),
                    'startingPrice' => $auction->getStartingPrice(),
                    'reservePrice' => $auction->getReservePrice(),
                    'startDate' => $auction->getStartDate()->format('Y-m-d H:i:s'),
                    'endDate' => $auction->getEndDate()->format('Y-m-d H:i:s'),
                ]);
            $this->entityManager->persist($auditLog);

            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le lot d\'enchère pour "%s" a été créé avec succès.', $artwork->getTitle()));
            return $this->redirectToRoute('app_admin_auction_index');
        }

        return $this->render('admin/auction/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_auction_edit', methods: ['GET', 'POST'])]
    public function edit(Auction $auction, Request $request): Response
    {
        $form = $this->createForm(AuctionLotType::class, $auction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Basic validation: ensure dates make sense
            if ($auction->getEndDate() <= $auction->getStartDate()) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                return $this->render('admin/auction/edit.html.twig', ['form' => $form, 'auction' => $auction]);
            }

            // Prevent assigning an artwork already used by another auction (OneToOne uniqueness)
            $artwork = $auction->getArtwork();
            if ($artwork !== null) {
                $existing = $this->auctionRepository->findOneBy(['artwork' => $artwork]);
                if ($existing !== null && $existing->getId() !== $auction->getId()) {
                    $this->addFlash('error', sprintf('Cette œuvre est déjà liée à l\'enchère #%d. Modifiez l\'enchère existante ou choisissez une autre œuvre.', $existing->getId()));
                    return $this->redirectToRoute('app_admin_auction_edit', ['id' => $existing->getId()]);
                }
            }

            $auction->synchronizeStatusFromDates();

            // Persist changes
            try {
                $this->entityManager->flush();
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                // Friendly message if a race-condition or unexpected duplicate occurs
                $this->addFlash('error', 'Impossible d\'enregistrer : une autre enchère utilise déjà cette œuvre.');
                return $this->redirectToRoute('app_admin_auction_index');
            }

            $this->addFlash('success', sprintf('Le lot d\'enchère #%s a été mis à jour.', $auction->getId()));
            return $this->redirectToRoute('app_admin_auction_index');
        }

        return $this->render('admin/auction/edit.html.twig', [
            'form' => $form,
            'auction' => $auction,
        ]);
    }

    #[Route('/{id}/publish', name: 'app_admin_auction_publish', methods: ['POST'])]
    public function publish(Auction $auction, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('publish' . $auction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        if ($auction->getStatus() === AuctionStatus::ENDED->value || $auction->getStatus() === AuctionStatus::CANCELLED->value) {
            $this->addFlash('error', 'Impossible de publier une enchère clôturée ou annulée.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        $now = new \DateTimeImmutable();
        $auction->setStatus(AuctionStatus::ACTIVE->value);
        // If the auction was previously closed, clear closedAt so it becomes active on the public site
        $auction->setClosedAt(null);
        if ($auction->getStartDate() > $now) {
            $auction->setStartDate($now);
        }

        $auditLog = new AuditLog();
        $auditLog->setUser($this->getUser())
            ->setAction('AUCTION_PUBLISHED')
            ->setEntityType('Auction')
            ->setEntityId($auction->getId());
        $this->entityManager->persist($auditLog);

        $this->entityManager->flush();

        $this->addFlash('success', 'L\'enchère est maintenant active et ouverte aux offres.');
        return $this->redirectToRoute('app_admin_auction_index');
    }

    #[Route('/{id}/set-status', name: 'app_admin_auction_set_status', methods: ['POST'])]
    public function setStatus(Auction $auction, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('set-status' . $auction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        $status = (string) $request->request->get('status', '');
        if (!in_array($status, AuctionStatus::values(), true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        // Apply status and maintain closedAt consistency
        $auction->setStatus($status);
        $now = new \DateTimeImmutable();
        if ($status === AuctionStatus::ACTIVE->value) {
            // Make sure the auction dates cover 'now' so it becomes visible on the public listing
            if ($auction->getStartDate() > $now) {
                $auction->setStartDate($now);
            }
            if ($auction->getEndDate() < $now) {
                $auction->setEndDate($now->modify('+3 days'));
            }
            $auction->setClosedAt(null);
        } elseif ($status === AuctionStatus::ENDED->value) {
            $auction->setClosedAt(new \DateTimeImmutable());
        }

        $auditLog = new AuditLog();
        $auditLog->setUser($this->getUser())
            ->setAction('AUCTION_STATUS_CHANGED')
            ->setEntityType('Auction')
            ->setEntityId($auction->getId())
            ->setDetails(['status' => $status]);
        $this->entityManager->persist($auditLog);

        $this->entityManager->flush();

        $this->addFlash('success', 'Statut mis à jour.');
        return $this->redirectToRoute('app_admin_auction_index');
    }

    #[Route('/{id}/cancel', name: 'app_admin_auction_cancel', methods: ['POST'])]
    public function cancel(Auction $auction, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cancel' . $auction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        if ($auction->getStatus() === AuctionStatus::ENDED->value) {
            $this->addFlash('error', 'Une enchère clôturée ne peut pas être annulée.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        $auction->setStatus(AuctionStatus::CANCELLED->value);
        $auction->setClosedAt(new \DateTimeImmutable());

        // Restore artwork availability
        if ($auction->getArtwork()) {
            $auction->getArtwork()->setStatus('AVAILABLE');
        }

        $auditLog = new AuditLog();
        $auditLog->setUser($this->getUser())
            ->setAction('AUCTION_CANCELLED')
            ->setEntityType('Auction')
            ->setEntityId($auction->getId());
        $this->entityManager->persist($auditLog);

        $this->entityManager->flush();

        $this->addFlash('warning', 'L\'enchère a été annulée et l\'œuvre est à nouveau disponible.');
        return $this->redirectToRoute('app_admin_auction_index');
    }

    #[Route('/{id}/force-close', name: 'app_admin_auction_force_close', methods: ['POST'])]
    public function forceClose(Auction $auction, Request $request): Response
    {
        if ($auction->getArtwork() === null) {
            $this->addFlash('error', 'Cette enchère archivée ne peut pas être clôturée à nouveau.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        if (!$this->isCsrfTokenValid('force-close' . $auction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        if ($auction->getStatus() === AuctionStatus::ENDED->value) {
            $this->addFlash('error', 'Cette enchère est déjà clôturée.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        $highestBid = $this->bidRepository->findHighestBid($auction);
        $reservePrice = $auction->getReservePrice() ? (float) $auction->getReservePrice() : null;
        $highestAmt = $highestBid ? (float) $highestBid->getAmount() : null;

        if ($highestBid !== null && ($reservePrice === null || $highestAmt >= $reservePrice)) {
            $winner = $highestBid->getUser();
            $this->auctionService->closeAuction($auction);
            $this->auctionOrderService->createOrderFromAuction($auction, $winner, $highestAmt);
            $this->addFlash('success', sprintf('Enchère clôturée ! Gagnant : %s (%s €). Commande de paiement créée.', $winner->getEmail(), number_format($highestAmt, 2)));
        } else {
            $auction->setStatus(AuctionStatus::ENDED->value);
            $auction->setClosedAt(new \DateTimeImmutable());
            if ($auction->getArtwork()) {
                $auction->getArtwork()->setStatus('AVAILABLE');
            }
            $this->entityManager->flush();
            $this->addFlash('warning', 'Enchère clôturée sans vente (aucune offre valide ou prix de réserve non atteint). L\'œuvre est à nouveau disponible.');
        }

        return $this->redirectToRoute('app_admin_auction_index');
    }

    #[Route('/{id}/delete', name: 'app_admin_auction_delete', methods: ['POST'])]
    public function delete(Auction $auction, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $auction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        // Before deletion, restore artwork availability to avoid leaving it in AUCTION state
        if ($auction->getArtwork()) {
            $auction->getArtwork()->setStatus('AVAILABLE');
        }

        $auditLog = new AuditLog();
        $auditLog->setUser($this->getUser())
            ->setAction('AUCTION_DELETED')
            ->setEntityType('Auction')
            ->setEntityId($auction->getId());
        $this->entityManager->persist($auditLog);

        $this->entityManager->remove($auction);
        $this->entityManager->flush();

        $this->addFlash('warning', sprintf('L\'enchère #%d a été supprimée.', $auction->getId()));
        return $this->redirectToRoute('app_admin_auction_index');
    }

    #[Route('/{id}/live', name: 'app_admin_auction_live', methods: ['GET'])]
    public function live(Auction $auction): Response
    {
        if ($auction->getArtwork() === null) {
            $this->addFlash('warning', 'Cette enchère est archivée ; son œuvre a été retirée du catalogue.');
            return $this->redirectToRoute('app_admin_auction_index');
        }

        $bids = $this->bidRepository->findBidsForAuction($auction, 50);
        $highestBid = $this->bidRepository->findHighestBid($auction);

        return $this->render('admin/auction/live.html.twig', [
            'auction' => $auction,
            'bids' => $bids,
            'highestBid' => $highestBid,
        ]);
    }
}
