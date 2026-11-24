<?php

namespace App\Controller;

use App\Entity\Artwork;
use App\Entity\ArtworkImage;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Service\AIArtAdvisorService;
use App\Service\ArtworkRecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AIArtAdvisorController extends AbstractController
{
    #[Route('/ai-advisor', name: 'app_ai_advisor', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('ai_advisor/index.html.twig');
    }

    #[Route('/api/ai-advisor', name: 'api_ai_advisor', methods: ['POST'])]
    public function recommend(
        Request $request,
        AIArtAdvisorService $advisorService,
        ArtworkRecommendationService $recommendationService,
        FavoriteRepository $favoriteRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
        Packages $assets,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !is_string($payload['message'] ?? null)) {
            return $this->json(['error' => 'Le message doit être fourni au format JSON.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $message = trim($payload['message']);
        if ($message === '' || mb_strlen($message) > 1000) {
            return $this->json(['error' => 'Saisissez une demande de 1 à 1 000 caractères.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $analysis = $advisorService->analyze($message);
        $recommendations = $recommendationService->recommend($analysis['criteria'], 6);
        $artworks = array_map(static fn (array $recommendation): Artwork => $recommendation['artwork'], $recommendations);
        $user = $this->getUser();
        $favoriteIds = $user instanceof User
            ? $favoriteRepository->findArtworkIdsForUserAndArtworkIds(
                $user,
                array_map(static fn (Artwork $artwork): int => (int) $artwork->getId(), $artworks),
            )
            : [];
        $favoriteIds = array_fill_keys($favoriteIds, true);

        $items = [];
        foreach ($recommendations as $recommendation) {
            $artwork = $recommendation['artwork'];
            $artworkId = (int) $artwork->getId();
            $items[] = [
                'id' => $artworkId,
                'title' => $artwork->getTitle(),
                'artist' => $artwork->getArtist()?->getName(),
                'category' => $artwork->getCategory()?->getName(),
                'price' => $artwork->getPrice() === null ? null : (float) $artwork->getPrice(),
                'currency' => $artwork->getCurrency(),
                'score' => $recommendation['score'],
                'colors' => $recommendation['colors'],
                'reasons' => $recommendation['reasons'],
                'image' => $this->artworkImageUrl($artwork, $assets),
                'url' => $this->generateUrl('app_catalog_show', ['id' => $artworkId]),
                'favorite' => [
                    'isFavorite' => isset($favoriteIds[$artworkId]),
                    'authenticated' => $user instanceof User,
                    'url' => $this->generateUrl('app_favorite_toggle', ['id' => $artworkId]),
                    'token' => $csrfTokenManager->getToken('favorite_'.$artworkId)->getValue(),
                    'loginUrl' => $this->generateUrl('app_login'),
                ],
            ];
        }

        return $this->json([
            'criteria' => $analysis['criteria'],
            'source' => $analysis['source'],
            'recommendations' => $items,
        ]);
    }

    private function artworkImageUrl(Artwork $artwork, Packages $assets): ?string
    {
        $imagePath = null;
        foreach ($artwork->getImages() as $image) {
            if ($image instanceof ArtworkImage && $image->isPrimary()) {
                $imagePath = $image->getFilePath();
                break;
            }
        }
        if ($imagePath === null) {
            $image = $artwork->getImages()->first();
            if ($image instanceof ArtworkImage) {
                $imagePath = $image->getFilePath();
            }
        }
        $imagePath ??= $artwork->getPhoto();
        if ($imagePath === null || trim($imagePath) === '') {
            return null;
        }

        $imagePath = str_replace('\\', '/', trim($imagePath));
        if (preg_match('#^https?://#i', $imagePath) === 1) {
            return $imagePath;
        }
        if (str_starts_with($imagePath, '/')) {
            return $assets->getUrl(ltrim($imagePath, '/'));
        }
        if (str_starts_with($imagePath, 'uploads/')) {
            return $assets->getUrl($imagePath);
        }

        return $assets->getUrl('uploads/artworks/'.basename($imagePath));
    }
}
