<?php

namespace App\Service;

use App\Entity\Auction;
use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée une commande (Order) à partir d'une enchère remportée.
 * Découplé de CartService : pas de session, pas de panier.
 */
class AuctionOrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Génère un Order (source=AUCTION, status=PENDING_PAYMENT) + OrderItem + Payment (PENDING)
     * pour le gagnant d'une enchère clôturée.
     */
    public function createOrderFromAuction(Auction $auction, User $winner, float $finalPrice): Order
    {
        $artwork = $auction->getArtwork();
        if ($artwork === null) {
            throw new \InvalidArgumentException('Cette enchère ne peut pas générer de commande car son œuvre a été retirée.');
        }

        // 1. Créer la commande
        $order = new Order();
        $order->setUser($winner)
            ->setOrderNumber('AUC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)))
            ->setTotalAmount(number_format($finalPrice, 2, '.', ''))
            ->setCurrency('EUR')
            ->setSource('AUCTION')
            ->setStatus('PENDING_PAYMENT');

        $this->entityManager->persist($order);

        // 2. Ligne de commande
        $orderItem = new OrderItem();
        $orderItem->setOrder($order)
            ->setArtwork($artwork)
            ->setQuantity(1)
            ->setUnitPrice(number_format($finalPrice, 2, '.', ''))
            ->setTotalPrice(number_format($finalPrice, 2, '.', ''));

        $this->entityManager->persist($orderItem);

        // 3. Paiement en attente
        $payment = new Payment();
        $payment->setOrder($order)
            ->setTransactionReference(null)   // sera rempli quand le paiement sera effectué
            ->setAmount(number_format($finalPrice, 2, '.', ''))
            ->setCurrency('EUR')
            ->setMethod(null)                 // choix de méthode lors du paiement
            ->setStatus('PENDING');

        $this->entityManager->persist($payment);

        // 4. Marquer l'œuvre comme SOLD
        $artwork->setStatus('SOLD');

        // 5. Notification de paiement à effectuer
        $notification = new Notification();
        $notification->setUser($winner)
            ->setType('PAYMENT_REQUIRED')
            ->setTitle('💳 Paiement requis pour "' . $artwork->getTitle() . '"')
            ->setMessage(sprintf(
                'Félicitations %s ! Vous avez remporté l\'enchère pour "%s" avec une mise de %.2f €. '
                . 'Votre commande #%s est créée et en attente de paiement. '
                . 'Rendez-vous dans votre espace client pour finaliser.',
                $winner->getFirstName(),
                $artwork->getTitle(),
                $finalPrice,
                $order->getOrderNumber()
            ));

        $this->entityManager->persist($notification);

        // 6. Audit
        $auditLog = new AuditLog();
        $auditLog->setUser($winner)
            ->setAction('AUCTION_ORDER_CREATED')
            ->setEntityType('Order')
            ->setDetails([
                'orderNumber'  => $order->getOrderNumber(),
                'auctionId'    => $auction->getId(),
                'artworkId'    => $artwork->getId(),
                'artworkTitle' => $artwork->getTitle(),
                'finalPrice'   => $finalPrice,
                'winnerId'     => $winner->getId(),
            ]);

        $this->entityManager->persist($auditLog);

        return $order;
    }
}
