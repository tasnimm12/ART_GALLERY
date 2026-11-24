<?php

namespace App\Controller;

use App\Entity\Artwork;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    #[Route('/panier', name: 'app_cart_index')]
    public function index(CartService $cartService): Response
    {
        return $this->render('cart/index.html.twig', [
            'items' => $cartService->getItems(),
            'total' => $cartService->getTotal(),
            'count' => $cartService->getCount(),
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'app_cart_add', requirements: ['id' => '\d+'])]
    public function add(Artwork $artwork, CartService $cartService, Request $request): Response
    {
        if ($artwork->getStatus() !== 'AVAILABLE') {
            $this->addFlash('error', 'Cette œuvre n\'est plus disponible à l\'achat.');
            return $this->redirectToRoute('app_catalog_show', ['id' => $artwork->getId()]);
        }

        $added = $cartService->add($artwork->getId());
        if ($added) {
            $this->addFlash('success', sprintf('L\'œuvre "%s" a été ajoutée à votre panier.', $artwork->getTitle()));
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/panier/supprimer/{id}', name: 'app_cart_remove', requirements: ['id' => '\d+'])]
    public function remove(int $id, CartService $cartService): Response
    {
        $cartService->remove($id);
        $this->addFlash('info', 'L\'œuvre a été retirée de votre panier.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/panier/vider', name: 'app_cart_clear')]
    public function clear(CartService $cartService): Response
    {
        $cartService->clear();
        $this->addFlash('info', 'Votre panier a été vidé.');

        return $this->redirectToRoute('app_cart_index');
    }
}
