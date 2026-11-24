<?php

namespace App\Service;

use App\Entity\Auction;
use App\Enum\AuctionStatus;
use App\Entity\AuditLog;
use App\Entity\Bid;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\BidRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class AuctionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BidRepository $bidRepository,
        private ?HubInterface $hub = null,
    ) {
    }

    public function placeBid(Auction $auction, User $user, float $amount, ?Request $request = null): Bid
    {
        // 1. Validation: User account must be active and verified
        if ($user->getStatus() !== 'ACTIVE') {
            throw new \InvalidArgumentException('Votre compte n\'est pas actif.');
        }

        if ($user->getEmailVerifiedAt() === null) {
            throw new \InvalidArgumentException('Vous devez avoir vérifié votre compte (email/KYC) pour pouvoir enchérir.');
        }

        // 2. Validation: Auction must be actively ongoing
        if (!$auction->isActive()) {
            throw new \InvalidArgumentException('Cette vente aux enchères n\'est pas en cours.');
        }

        // 3. Validation: Minimum required amount
        $minRequired = $auction->getMinimumNextBid();
        if ($amount < $minRequired) {
            throw new \InvalidArgumentException(sprintf(
                'Le montant de votre mise (%.2f €) doit être au moins de %.2f €.',
                $amount,
                $minRequired
            ));
        }

        // 4. Update previous bids to OUTBID
        $previousBids = $this->bidRepository->findBy([
            'auction' => $auction,
            'status'  => 'VALID',
        ]);
        foreach ($previousBids as $prevBid) {
            $prevBid->setStatus('OUTBID');
        }

        $previousWinner = $auction->getWinner();

        // 5. Create new Bid
        $bid = new Bid();
        $bid->setAuction($auction)
            ->setUser($user)
            ->setAmount(number_format($amount, 2, '.', ''))
            ->setStatus('VALID');

        $this->entityManager->persist($bid);

        // 6. Update Auction current price & winner
        $auction->setCurrentPrice((string) $amount);
        $auction->setWinner($user);

        // 7. Anti-Sniping Rule: extend by 2 minutes if placed in last 120 seconds
        $antiSnipeTriggered = false;
        if ($auction->getTimeRemainingSeconds() <= 120 && $auction->getTimeRemainingSeconds() > 0) {
            $newEndDate = $auction->getEndDate()->modify('+2 minutes');
            $auction->setEndDate($newEndDate);
            $antiSnipeTriggered = true;
        }

        // 8. Notification to the bidder
        $notificationBidder = new Notification();
        $notificationBidder->setUser($user)
            ->setType('BID_PLACED')
            ->setTitle('Offre confirmée pour "' . $auction->getArtworkTitle() . '"')
            ->setMessage(sprintf(
                'Votre enchère de %.2f EUR a bien été enregistrée. Vous êtes actuellement le meilleur enchérisseur.',
                $amount
            ));
        $this->entityManager->persist($notificationBidder);

        // 9. Outbid notification to the previous highest bidder
        if ($previousWinner && $previousWinner->getId() !== $user->getId()) {
            $notificationOutbid = new Notification();
            $notificationOutbid->setUser($previousWinner)
                ->setType('OUTBID')
                ->setTitle('Surenchère sur "' . $auction->getArtworkTitle() . '"')
                ->setMessage(sprintf(
                    'Une nouvelle offre de %.2f EUR a dépassé votre enchère précédente. Vous pouvez surenchérir pour reprendre la tête !',
                    $amount
                ));
            $this->entityManager->persist($notificationOutbid);
        }

        // 10. Audit Log
        $auditLog = new AuditLog();
        $auditLog->setUser($user)
            ->setAction('BID_PLACED')
            ->setEntityType('Auction')
            ->setEntityId($auction->getId())
            ->setDetails([
                'artworkId'    => $auction->getArtwork()?->getId(),
                'artworkTitle' => $auction->getArtworkTitle(),
                'bidAmount'    => $amount,
                'minIncrement' => $auction->getMinIncrement(),
                'extended'     => $antiSnipeTriggered,
            ]);

        if ($request) {
            $auditLog->setIpAddress($request->getClientIp())
                ->setUserAgent(substr((string) $request->headers->get('User-Agent'), 0, 500));
        }

        $this->entityManager->persist($auditLog);

        $this->entityManager->flush();

        // 11. Mercure real-time update (non-blocking — silently fails if hub unreachable)
        $this->publishMercureUpdate($auction, $bid, $antiSnipeTriggered);

        return $bid;
    }

    /**
     * Clôture une enchère : statut CLOSED, horodatage, notification au vainqueur, audit.
     */
    public function closeAuction(Auction $auction): void
    {
        if ($auction->getStatus() === AuctionStatus::ENDED->value || $auction->getClosedAt() !== null) {
            return; // Already closed — idempotent
        }

        $auction->setStatus(AuctionStatus::ENDED->value);
        $auction->setClosedAt(new \DateTimeImmutable());

        // Notify winner if there is one
        if ($auction->getWinner() !== null) {
            $winner     = $auction->getWinner();
            $finalPrice = $auction->getCurrentPrice() ?? $auction->getStartingPrice();

            $notification = new Notification();
            $notification->setUser($winner)
                ->setType('AUCTION_WON')
                ->setTitle('🏆 Félicitations ! Vous avez remporté "' . $auction->getArtworkTitle() . '"')
                ->setMessage(sprintf(
                    'Vous avez remporté l\'enchère pour l\'œuvre "%s" avec une offre de %.2f €. Notre équipe vous contactera sous 24h pour finaliser la transaction.',
                    $auction->getArtworkTitle(),
                    (float) $finalPrice
                ));
            $this->entityManager->persist($notification);
        }

        // Audit log
        $auditLog = new AuditLog();
        $auditLog->setAction('AUCTION_CLOSED')
            ->setEntityType('Auction')
            ->setEntityId($auction->getId())
            ->setDetails([
                'artworkId'    => $auction->getArtwork()?->getId(),
                'artworkTitle' => $auction->getArtworkTitle(),
                'finalPrice'   => $auction->getCurrentPrice(),
                'winnerId'     => $auction->getWinner()?->getId(),
            ]);

        if ($auction->getWinner() !== null) {
            $auditLog->setUser($auction->getWinner());
        }

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();

        // Mercure: notify all watchers the auction is closed
        $this->publishMercureUpdate($auction, null, false);
    }

    /**
     * Publie une mise à jour temps réel sur le hub Mercure.
     * Non-bloquant : si le hub n'est pas joignable, l'exception est silencieusement ignorée.
     */
    private function publishMercureUpdate(Auction $auction, ?Bid $bid, bool $antiSnipeTriggered): void
    {
        if ($this->hub === null) {
            return;
        }

        try {
            $totalBids = $this->bidRepository->count(['auction' => $auction]);

            $payload = [
                'auctionId'          => $auction->getId(),
                'currentPrice'       => $auction->getCurrentPrice(),
                'minimumNextBid'     => $auction->getMinimumNextBid(),
                'endDateTimestamp'   => $auction->getEndDate()->getTimestamp(),
                'endDateFormatted'   => $auction->getEndDate()->format('d/m/Y H:i:s'),
                'antiSnipeTriggered' => $antiSnipeTriggered,
                'totalBids'          => $totalBids,
                'status'             => $auction->getStatus(),
                'isClosed'           => $auction->isEnded(),
            ];

            if ($bid !== null) {
                $payload['lastBid'] = [
                    'amount'    => $bid->getAmount(),
                    'bidderName' => $bid->getUser()->getFirstName() . ' ' . mb_strimwidth($bid->getUser()->getLastName(), 0, 1, '.'),
                    'placedAt'  => $bid->getCreatedAt()->format('d/m à H:i:s'),
                ];
            }

            $update = new Update(
                topics: '/auction/' . $auction->getId(),
                data: json_encode($payload, JSON_THROW_ON_ERROR),
                private: false,
            );

            $this->hub->publish($update);
        } catch (\Throwable) {
            // Hub unreachable or misconfigured — auction logic must not be interrupted
        }
    }
}
