<?php

namespace App\Controller\Admin;

use App\Entity\Payment;
use App\Entity\Order;
use App\Entity\AuditLog;
use App\Entity\Notification;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/payment')]
class PaymentAdminController extends AbstractController
{
    public function __construct(private ManagerRegistry $doctrine, private CsrfTokenManagerInterface $csrf)
    {
    }

    #[Route('/', name: 'admin_payment_index', methods: ['GET'])]
    public function index(): Response
    {
        $em = $this->doctrine->getManager();
        $payments = $em->getRepository(Payment::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/payment/index.html.twig', [
            'payments' => $payments,
        ]);
    }

    #[Route('/{id}/accept', name: 'admin_payment_accept', methods: ['POST'])]
    public function accept(Request $request, int $id): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('accept-payment-'.$id, $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_payment_index');
        }

        $em = $this->doctrine->getManager();
        /** @var Payment|null $payment */
        $payment = $em->getRepository(Payment::class)->find($id);
        if (!$payment) {
            $this->addFlash('danger', 'Paiement introuvable.');
            return $this->redirectToRoute('admin_payment_index');
        }

        // Mark payment as PAID
        $payment->setStatus('PAID');
        $payment->setPaidAt(new \DateTimeImmutable());
        $em->persist($payment);

        // Mark order as PAID
        $order = $payment->getOrder();
        if ($order instanceof Order) {
            $order->setStatus('PAID');
            $em->persist($order);
        }

        // Mark artworks as SOLD and clear any pending flags
        foreach ($order->getItems() as $item) {
            $artwork = $item->getArtwork();
            if ($artwork) {
                try {
                    $artwork->setStatus('SOLD');
                } catch (\Throwable $e) {
                    $artwork->setStatus('SOLD');
                }
                $em->persist($artwork);
            }
        }

        // Notification to user
        $notification = new Notification();
        $notification->setUser($order->getUser())
            ->setType('PAYMENT_CONFIRMED')
            ->setTitle('Votre paiement a été confirmé')
            ->setMessage('Votre paiement pour la commande ' . $order->getOrderNumber() . ' a été accepté par l\'administration.');
        $em->persist($notification);

        // Audit log
        $audit = new AuditLog();
        $audit->setUser($this->getUser())
            ->setAction('ADMIN_ACCEPT_PAYMENT')
            ->setEntityType('Payment')
            ->setEntityId($payment->getId())
            ->setDetails(['order' => $order->getId()]);
        $em->persist($audit);

        $em->flush();

        $this->addFlash('success', 'Paiement accepté et commande marquée payée.');

        return $this->redirectToRoute('admin_payment_index');
    }

    #[Route('/{id}/decline', name: 'admin_payment_decline', methods: ['POST'])]
    public function decline(Request $request, int $id): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('decline-payment-'.$id, $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_payment_index');
        }

        $em = $this->doctrine->getManager();
        /** @var Payment|null $payment */
        $payment = $em->getRepository(Payment::class)->find($id);
        if (!$payment) {
            $this->addFlash('danger', 'Paiement introuvable.');
            return $this->redirectToRoute('admin_payment_index');
        }

        // Mark payment as DECLINED
        $payment->setStatus('DECLINED');
        $em->persist($payment);

        // Optionally release artworks back to AVAILABLE
        $order = $payment->getOrder();
        foreach ($order->getItems() as $item) {
            $artwork = $item->getArtwork();
            if ($artwork) {
                try {
                    $artwork->setStatus('AVAILABLE');
                } catch (\Throwable $e) {
                    $artwork->setStatus('AVAILABLE');
                }
                $em->persist($artwork);
            }
        }

        // Notification to user
        $notification = new Notification();
        $notification->setUser($order->getUser())
            ->setType('PAYMENT_DECLINED')
            ->setTitle('Votre paiement a été refusé')
            ->setMessage('Le paiement pour la commande ' . $order->getOrderNumber() . ' a été refusé par l\'administration.');
        $em->persist($notification);

        // Audit log
        $audit = new AuditLog();
        $audit->setUser($this->getUser())
            ->setAction('ADMIN_DECLINE_PAYMENT')
            ->setEntityType('Payment')
            ->setEntityId($payment->getId())
            ->setDetails(['order' => $order->getId()]);
        $em->persist($audit);

        $em->flush();

        $this->addFlash('success', 'Paiement refusé et articles libérés.');

        return $this->redirectToRoute('admin_payment_index');
    }
}
