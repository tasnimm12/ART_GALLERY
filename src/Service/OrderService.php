<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartService $cartService
    ) {
    }

    /**
     * @param array{address?: string, city?: string, postalCode?: string, country?: string, latitude?: string, longitude?: string, paymentMethod?: string} $checkoutData
     */
    public function createOrderFromCart(User $user, array $checkoutData = [], ?Request $request = null): Order
    {
        $items = $this->cartService->getItems();
        if (empty($items)) {
            throw new \RuntimeException('Le panier est vide ou les œuvres ne sont plus disponibles.');
        }

        $totalAmount = $this->cartService->getTotal();

        // 1. Create Order
        $order = new Order();
        $order->setUser($user)
            ->setOrderNumber('ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)))
            ->setTotalAmount(number_format($totalAmount, 2, '.', ''))
            ->setCurrency('EUR')
            ->setSource('DIRECT')
            ->setStatus('PENDING_PAYMENT')
            ->setLatitude($checkoutData['latitude'] ?? null)
            ->setLongitude($checkoutData['longitude'] ?? null);

        $this->entityManager->persist($order);

        // 2. Create Order Items & Update Artwork Status
        foreach ($items as $item) {
            $artwork = $item['artwork'];

            $orderItem = new OrderItem();
            $orderItem->setOrder($order)
                ->setArtwork($artwork)
                ->setQuantity(1)
                ->setUnitPrice((string) $item['price'])
                ->setTotalPrice((string) $item['price']);

            $this->entityManager->persist($orderItem);

            // Mark artwork as pending payment to avoid double-selling
            try {
                $artwork->setStatus('PENDING_PAYMENT');
            } catch (\Throwable $e) {
                // ignore if artwork status value is unexpected
                $artwork->setStatus('PENDING_PAYMENT');
            }
        }

        // 3. Create Payment (pending)
        $payment = new Payment();
        $payment->setOrder($order)
            ->setTransactionReference(null)
            ->setAmount(number_format($totalAmount, 2, '.', ''))
            ->setCurrency('EUR')
            ->setMethod($checkoutData['paymentMethod'] ?? 'CARD')
            ->setStatus('PENDING')
            ->setPaidAt(null);

        $this->entityManager->persist($payment);

        // 4. Create Notification
        $notification = new Notification();
        $notification->setUser($user)
            ->setType('ORDER_CONFIRMED')
            ->setTitle('Confirmation de votre commande #' . $order->getOrderNumber())
            ->setMessage(sprintf(
                'Félicitations %s ! Votre commande d\'un montant de %s EUR a été validée avec succès. Nous préparons vos œuvres d\'art avec le plus grand soin.',
                $user->getFirstName(),
                $order->getTotalAmount()
            ));

        $this->entityManager->persist($notification);

        // 5. Create AuditLog
        $auditLog = new AuditLog();
        $auditLog->setUser($user)
            ->setAction('DIRECT_ORDER_PAID')
            ->setEntityType('Order')
            ->setDetails([
                'orderNumber' => $order->getOrderNumber(),
                'totalAmount' => $order->getTotalAmount(),
                'itemsCount' => count($items),
                'paymentMethod' => $payment->getMethod(),
                'transactionReference' => $payment->getTransactionReference(),
                'delivery' => [
                    'city' => $checkoutData['city'] ?? null,
                    'country' => $checkoutData['country'] ?? null,
                ],
            ]);

        if ($request) {
            $auditLog->setIpAddress($request->getClientIp())
                ->setUserAgent(substr((string) $request->headers->get('User-Agent'), 0, 500));
        }

        $this->entityManager->persist($auditLog);

        // Flush all changes
        $this->entityManager->flush();

        // 6. Clear Cart
        $this->cartService->clear();

        return $order;
    }
}
