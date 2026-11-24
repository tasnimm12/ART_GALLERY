<?php

namespace App\Controller;

use App\Entity\Artwork;
use App\Entity\Favorite;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class FavoriteController extends AbstractController
{
    #[Route('/compte/favoris/{id}/toggle', name: 'app_favorite_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(
        Artwork $artwork,
        Request $request,
        FavoriteRepository $favoriteRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('favorite_'.$artwork->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'La demande a expiré. Actualisez la page et réessayez.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $favorite = $favoriteRepository->findOneForUserAndArtwork($user, $artwork);
        if ($favorite instanceof Favorite) {
            $user->removeFavorite($favorite);
            $artwork->removeFavorite($favorite);
            $entityManager->remove($favorite);
            $isFavorite = false;
            $message = 'Œuvre retirée des favoris';
        } else {
            $favorite = (new Favorite())
                ->setUser($user)
                ->setArtwork($artwork);
            $user->addFavorite($favorite);
            $artwork->addFavorite($favorite);
            $entityManager->persist($favorite);
            $isFavorite = true;
            $message = 'Œuvre ajoutée aux favoris';
        }

        $entityManager->flush();

        return $this->json([
            'favorite' => $isFavorite,
            'count' => $favoriteRepository->countForUser($user),
            'message' => $message,
        ]);
    }
}
