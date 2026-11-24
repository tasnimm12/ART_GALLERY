<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\CheckoutFormType;
use App\Repository\OrderRepository;
use App\Service\CartService;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class OrderController extends AbstractController
{
    #[Route('/commande/recapitulatif', name: 'app_order_checkout')]
    public function checkout(Request $request, CartService $cartService, OrderService $orderService): Response
    {
        // Require logged in client
        if (!$this->getUser()) {
            $this->addFlash('info', 'Veuillez vous connecter ou créer un compte pour finaliser votre commande.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isGranted('ROLE_CLIENT')) {
            throw $this->createAccessDeniedException('Accès réservé aux clients.');
        }

        $items = $cartService->getItems();
        if (empty($items)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $form = $this->createForm(CheckoutFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $order = $orderService->createOrderFromCart($this->getUser(), $data, $request);

            // If user selected Stripe, redirect to Stripe Checkout
            if (($data['paymentMethod'] ?? '') === 'STRIPE') {
                // If requested via AJAX, return JSON with the checkout URL so client JS can redirect
                if ($request->isXmlHttpRequest()) {
                    return new \Symfony\Component\HttpFoundation\JsonResponse([
                        'redirect' => $this->generateUrl('payment_create_checkout', ['orderId' => $order->getId()])
                    ]);
                }

                return $this->redirectToRoute('payment_create_checkout', ['orderId' => $order->getId()]);
            }

            // Otherwise, keep previous behavior: consider payment handled (fallback)
            $this->addFlash('success', 'Votre commande a été créée. Choisissez un mode de paiement pour finaliser.');
            return $this->redirectToRoute('app_order_confirmation', ['id' => $order->getId()]);
        }

        $stripeSecret = getenv('STRIPE_SECRET_KEY') ?: ($_ENV['STRIPE_SECRET_KEY'] ?? null);
        $stripePublishable = getenv('STRIPE_PUBLISHABLE_KEY') ?: ($_ENV['STRIPE_PUBLISHABLE_KEY'] ?? null);

        return $this->render('order/checkout.html.twig', [
            'form' => $form,
            'items' => $items,
            'total' => $cartService->getTotal(),
            'stripe_configured' => ($stripeSecret && $stripePublishable) ? true : false,
            'stripe_publishable_key' => $stripePublishable,
        ]);
    }

    #[Route('/commande/confirmation/{id}', name: 'app_order_confirmation', requirements: ['id' => '\d+'])]
    public function confirmation(Order $order): Response
    {
        $this->denyAccessUnlessGranted('ORDER_VIEW', $order);

        return $this->render('order/confirmation.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/mes-commandes', name: 'app_order_history')]
    #[IsGranted('ROLE_CLIENT')]
    public function history(OrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']);

        return $this->render('order/history.html.twig', [
            'orders' => $orders,
        ]);
    }
}
