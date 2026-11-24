<?php

namespace App\Controller;

use App\Entity\Auction;
use App\Form\BidType;
use App\Repository\AuctionRepository;
use App\Repository\BidRepository;
use App\Service\AuctionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuctionController extends AbstractController
{
    #[Route('/encheres', name: 'app_auction_index')]
    public function index(AuctionRepository $auctionRepository): Response
    {
        $liveAuctions = $auctionRepository->findActiveAuctions();
        $plannedAuctions = $auctionRepository->findUpcomingAuctions();
        $finishedAuctions = $auctionRepository->findPastAuctions();

        return $this->render('auction/index.html.twig', [
            'liveAuctions'     => $liveAuctions,
            'plannedAuctions'  => $plannedAuctions,
            'finishedAuctions' => $finishedAuctions,
            'activeAuctions'   => $liveAuctions,
            'upcomingAuctions' => $plannedAuctions,
            'pastAuctions'     => $finishedAuctions,
        ]);
    }

    #[Route('/encheres/{id}', name: 'app_auction_show', requirements: ['id' => '\d+'])]
    public function show(
        Auction $auction,
        Request $request,
        BidRepository $bidRepository,
        AuctionService $auctionService
    ): Response {
        if ($auction->getArtwork() === null) {
            throw $this->createNotFoundException('L’œuvre de cette enchère n’est plus disponible.');
        }

        $minBid = $auction->getMinimumNextBid();

        $form = $this->createForm(BidType::class, null, [
            'min_amount' => $minBid,
            'action'     => $this->generateUrl('app_auction_show', ['id' => $auction->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->getUser()) {
                $this->addFlash('error', 'Vous devez être connecté pour enchérir.');
                return $this->redirectToRoute('app_login');
            }

            if (!$this->isGranted('ROLE_CLIENT')) {
                $this->addFlash('error', 'Seuls les clients peuvent participer aux enchères.');
                return $this->redirectToRoute('app_auction_show', ['id' => $auction->getId()]);
            }

            try {
                $this->denyAccessUnlessGranted('AUCTION_BID', $auction);

                $amount = (float) $form->get('amount')->getData();
                $auctionService->placeBid($auction, $this->getUser(), $amount, $request);

                $this->addFlash('success', sprintf(
                    '✅ Votre enchère de %.2f € a bien été enregistrée ! Vous êtes le meilleur enchérisseur.',
                    $amount
                ));
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue. Veuillez réessayer.');
            }

            return $this->redirectToRoute('app_auction_show', ['id' => $auction->getId()]);
        }

        $bids = $bidRepository->findBidsForAuction($auction, 20);

        return $this->render('auction/show.html.twig', [
            'auction' => $auction,
            'bids'    => $bids,
            'form'    => $form,
            'minBid'  => $minBid,
        ]);
    }

    /**
     * Endpoint JSON d'état de l'enchère — utilisé par le fallback polling et l'initialisation SSE.
     * Accessible publiquement, sans authentification requise.
     */
    #[Route('/encheres/{id}/state', name: 'app_auction_state', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function state(Auction $auction, BidRepository $bidRepository): JsonResponse
    {
        if ($auction->getArtwork() === null) {
            throw $this->createNotFoundException('L’œuvre de cette enchère n’est plus disponible.');
        }

        $bids     = $bidRepository->findBidsForAuction($auction, 10);
        $totalBids = $bidRepository->count(['auction' => $auction]);

        $bidsData = array_map(static function ($bid) {
            return [
                'amount'     => $bid->getAmount(),
                'bidderName' => $bid->getUser()->getFirstName() . ' ' . mb_strimwidth($bid->getUser()->getLastName(), 0, 1, '.'),
                'status'     => $bid->getStatus(),
                'placedAt'   => $bid->getCreatedAt()->format('d/m à H:i:s'),
                'isTop'      => $bid->getStatus() === 'VALID',
            ];
        }, $bids);

        return $this->json([
            'auctionId'        => $auction->getId(),
            'currentPrice'     => $auction->getCurrentPrice(),
            'minimumNextBid'   => $auction->getMinimumNextBid(),
            'startingPrice'    => $auction->getStartingPrice(),
            'endDateTimestamp' => $auction->getEndDate()->getTimestamp(),
            'endDateFormatted' => $auction->getEndDate()->format('d/m/Y H:i:s'),
            'isActive'         => $auction->isActive(),
            'isEnded'          => $auction->isEnded(),
            'status'           => $auction->getStatus(),
            'totalBids'        => $totalBids,
            'bids'             => $bidsData,
            'winner'           => $auction->getWinner() ? [
                'name' => $auction->getWinner()->getFirstName() . ' ' . mb_strimwidth($auction->getWinner()->getLastName(), 0, 1, '.'),
            ] : null,
        ]);
    }
}
