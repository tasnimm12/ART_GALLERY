<?php

namespace App\Controller;

use App\Entity\Favorite;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    #[Route('/compte', name: 'app_account')]
    public function index(): Response
    {
        return $this->render('account/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/compte/favoris', name: 'app_account_favorites', methods: ['GET'])]
    public function favorites(FavoriteRepository $favoriteRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $favorites = $favoriteRepository->findAllForUserWithArtwork($user);
        $favoriteArtworkIds = array_map(
            static fn (Favorite $favorite): int => (int) $favorite->getArtwork()->getId(),
            $favorites,
        );

        return $this->render('account/favorites.html.twig', [
            'artworks' => array_map(
                static fn (Favorite $favorite) => $favorite->getArtwork(),
                $favorites,
            ),
            'favoriteArtworkIds' => $favoriteArtworkIds,
        ]);
    }
}
