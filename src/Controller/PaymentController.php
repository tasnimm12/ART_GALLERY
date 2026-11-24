<?php

namespace App\Controller;

use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;

class PaymentController extends AbstractController
{
    private ?StripeClient $stripe = null;

    public function __construct()
    {
        $secret = getenv('STRIPE_SECRET_KEY') ?: $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if ($secret !== null) {
            $this->stripe = new StripeClient($secret);
        }
        // If secret is not set, keep $this->stripe === null and handle in methods with a friendly message.
    }

    #[Route('/payment/create-checkout/{orderId}', name: 'payment_create_checkout', methods: ['GET'])]
    public function createCheckout(Request $request, int $orderId, ManagerRegistry $doctrine): Response
    {

        $secret = getenv('STRIPE_SECRET_KEY') ?: $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$secret) {
            return new Response('STRIPE_SECRET_KEY not configured. Add your test key to .env.local', 500);
        }

        $em = $doctrine->getManager();
        $order = $em->getRepository(\App\Entity\Order::class)->find($orderId);
        if (!$order) {
            return new Response('Commande introuvable', 404);
        }

        // Ensure user is owner or allow admin
        $user = $this->getUser();
        if ($user === null || ($order->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN'))) {
            return new Response('Accès refusé', 403);
        }

        // Find pending payment for this order
        $payment = $em->getRepository(\App\Entity\Payment::class)->findOneBy(['order' => $order]);
        if (!$payment) {
            return new Response('Aucun paiement trouvé pour cette commande', 400);
        }
        if ($payment->getStatus() !== 'PENDING') {
            return new Response('Le paiement a déjà été traité ou n\'est pas en attente', 400);
        }

        // Build Checkout session
        $amount = (int) round((float) $order->getTotalAmount() * 100);

        if ($this->stripe === null) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'STRIPE_SECRET_KEY not configured. Add your test key to .env.local'], 500);
            }
            return new Response('STRIPE_SECRET_KEY not configured. Add your test key to .env.local', 500);
        }

        // Build success/cancel URLs robustly
        $successUrl = $this->generateUrl('payment_success', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $this->generateUrl('payment_cancel', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        // Fallback if generateUrl didn't return a valid absolute URL (edge cases)
        if (!filter_var($successUrl, FILTER_VALIDATE_URL)) {
            $base = $request->getSchemeAndHttpHost();
            $successUrl = rtrim($base, '/') . $this->generateUrl('payment_success', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_PATH) . '?session_id={CHECKOUT_SESSION_ID}';
        }
        if (!filter_var($cancelUrl, FILTER_VALIDATE_URL)) {
            $base = $request->getSchemeAndHttpHost();
            $cancelUrl = rtrim($base, '/') . $this->generateUrl('payment_cancel', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_PATH);
        }

        try {
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($order->getCurrency()),
                        'product_data' => [
                            'name' => sprintf('Commande %s', $order->getOrderNumber()),
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'order_id' => (string) $order->getId(),
                    'payment_id' => (string) $payment->getId(),
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $msg = $e->getMessage();
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $msg], 500);
            }
            return new Response('Stripe API error: ' . $msg, 500);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => $msg], 500);
            }
            return new Response('Error creating checkout session: ' . $msg, 500);
        }

        // If AJAX request, return the session info as JSON so client-side can redirect
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['sessionId' => $session->id, 'url' => $session->url]);
        }

        // Fallback: server-side redirect for non-AJAX requests
        return $this->redirect($session->url);
    }

    #[Route('/payment/create-checkout', name: 'payment_create_checkout_test', methods: ['GET'])]
    public function createCheckoutTest(Request $request): Response
    {
        $secret = getenv('STRIPE_SECRET_KEY') ?: $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$secret) {
            return new Response('STRIPE_SECRET_KEY not configured. Add your test key to .env.local', 500);
        }

        if ($this->stripe === null) {
            return new Response('STRIPE client not initialized', 500);
        }

        // Create a test Checkout session for a fixed amount (32.00 EUR)
        $amount = 3200; // cents

        $session = $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Test payment - Abstract Art Gallery',
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'metadata' => [
                'test' => 'true',
            ],
            'success_url' => $this->generateUrl('payment_success', [], true) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->generateUrl('payment_cancel', [], true),
        ]);

        // Redirect the user to Stripe Checkout
        return $this->redirect($session->url);
    }

    #[Route('/payment/success', name: 'payment_success', methods: ['GET'])]
    public function success(Request $request): Response
    {
        $sessionId = $request->query->get('session_id');
        if ($sessionId) {
            try {
                $session = $this->stripe->checkout->sessions->retrieve($sessionId, []);
            } catch (\Exception $e) {
                return new Response('Impossible de récupérer la session Stripe: ' . $e->getMessage(), 500);
            }

            $orderId = $session->metadata->order_id ?? null;
            if ($orderId) {
                return $this->redirectToRoute('app_order_confirmation', ['id' => (int) $orderId]);
            }
        }

        return new Response("Paiement réussi (test). Session: " . ($sessionId ?? 'n/a'));
    }

    #[Route('/payment/cancel', name: 'payment_cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        return new Response('Paiement annulé (test).');
    }

    /**
     * Minimal webhook endpoint for testing checkout.session.completed
     * Configure STRIPE_WEBHOOK_SECRET in your environment to verify signatures
     */
    #[Route('/payment/webhook', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');
        $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;

        if ($webhookSecret) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (\UnexpectedValueException $e) {
                return new JsonResponse(['error' => 'Invalid payload'], 400);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                return new JsonResponse(['error' => 'Invalid signature'], 400);
            }
        } else {
            // If no webhook secret is configured, attempt to decode without verification (only for local testing)
            $event = json_decode($payload);
            if ($event === null) {
                return new JsonResponse(['error' => 'Invalid payload'], 400);
            }
        }

        if (($event->type ?? '') === 'checkout.session.completed') {
            $session = $event->data->object;
            // handle completed session: mark payment and order
            $metadata = $session->metadata ?? null;
            if ($metadata && ($metadata->payment_id ?? null)) {
                $em = $doctrine->getManager();
                $payment = $em->getRepository(\App\Entity\Payment::class)->find((int) $metadata->payment_id);
                $order = $em->getRepository(\App\Entity\Order::class)->find((int) $metadata->order_id);

                if ($payment && $order) {
                    // Use payment_intent as transaction reference when available
                    $txRef = $session->payment_intent ?? $session->id ?? null;
                    $payment->setTransactionReference($txRef);
                    $payment->setStatus('PAID');
                    $payment->setPaidAt(new \DateTimeImmutable());

                    $order->setStatus('PAID');

                    // Notification to user
                    $notification = new \App\Entity\Notification();
                    $notification->setUser($order->getUser())
                        ->setType('PAYMENT_RECEIVED')
                        ->setTitle('Paiement reçu pour la commande ' . $order->getOrderNumber())
                        ->setMessage('Merci ! Votre paiement a été confirmé (sandbox).');
                    $em->persist($notification);

                    // Audit log
                    $audit = new \App\Entity\AuditLog();
                    $audit->setUser($order->getUser())
                        ->setAction('PAYMENT_CONFIRMED')
                        ->setEntityType('Payment')
                        ->setEntityId($payment->getId())
                        ->setDetails(['transaction' => $txRef]);
                    $em->persist($audit);

                    $em->flush();
                }
            }
        }

        return new JsonResponse(['ok' => true]);
    }
}
