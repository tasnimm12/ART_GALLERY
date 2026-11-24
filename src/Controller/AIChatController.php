<?php

namespace App\Controller;

use App\Entity\Artwork;
use App\Repository\ArtworkRepository;
use App\Service\AI\AIChatService;
use App\Service\AI\AIChatException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

final class AIChatController extends AbstractController
{
    #[Route('/ai-assistant', name: 'app_ai_assistant', methods: ['GET', 'POST'])]
    #[Route('/assistant', name: 'app_ai_assistant_legacy', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        AIChatService $aiChatService,
        ArtworkRepository $artworkRepository,
        LoggerInterface $logger,
    ): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_home');
        }

        $userMessage = '';
        $assistantReply = 'Bonjour ! Je suis l\'assistant artistique d\'Abstract Art Gallery.';
        $recommendations = [];
        $artworkId = null;
        $error = null;

        if (str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            $payload = json_decode($request->getContent(), true);
            $rawMessage = is_array($payload) ? ($payload['message'] ?? null) : null;
            $userMessage = is_string($rawMessage) ? trim($rawMessage) : '';
            $rawArtworkId = is_array($payload) ? ($payload['artworkId'] ?? null) : null;
        } else {
            $userMessage = trim((string) $request->request->get('message', ''));
            $rawArtworkId = $request->request->get('artworkId');
        }

        if ($rawArtworkId !== null) {
            if ((!is_int($rawArtworkId) && !is_string($rawArtworkId)) || !ctype_digit((string) $rawArtworkId)) {
                return $this->json(['error' => 'The artwork context is invalid.'], Response::HTTP_BAD_REQUEST);
            }

            $artworkId = (int) $rawArtworkId;
        }

        if ($userMessage === '') {
            $error = 'Please enter a message.';
        } elseif (mb_strlen($userMessage) > 2000) {
            $error = 'Please keep your message under 2,000 characters.';
        } else {
            try {
                $currentArtwork = $artworkId !== null ? $artworkRepository->find($artworkId) : null;
                if ($artworkId !== null && !($currentArtwork instanceof Artwork)) {
                    return $this->json(['error' => 'The requested artwork was not found.'], Response::HTTP_NOT_FOUND);
                }

                $result = $aiChatService->ask($userMessage, $currentArtwork);
                $assistantReply = $result['message'];
                $recommendations = $result['artworks'];
            } catch (AIChatException $exception) {
                $logger->error('AI assistant could not answer the request.', [
                    'exception_class' => $exception::class,
                ]);
                $error = 'Sorry, the artistic assistant is temporarily unavailable. Please try again.';
            } catch (\Throwable $exception) {
                $logger->error('AI assistant request failed.', [
                    'exception_class' => $exception::class,
                ]);
                $error = 'Sorry, the artistic assistant is temporarily unavailable. Please try again.';
            }
        }

        if ($error !== null) {
            return $this->json(
                ['error' => $error],
                $userMessage === '' || mb_strlen($userMessage) > 2000
                    ? Response::HTTP_UNPROCESSABLE_ENTITY
                    : Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $this->json([
            'message' => $assistantReply,
            'recommendations' => array_map(
                fn (Artwork $artwork): array => $this->serializeRecommendation($artwork),
                $recommendations,
            ),
        ]);
    }

    /**
     * @return array{id: int|null, title: string, artist: string|null, category: string|null, price: float|null, currency: string, image: string|null, url: string}
     */
    private function serializeRecommendation(Artwork $artwork): array
    {
        $imagePath = null;
        foreach ($artwork->getImages() as $image) {
            if ($image->isPrimary()) {
                $imagePath = $image->getFilePath();
                break;
            }
        }

        if ($imagePath === null) {
            $firstImage = $artwork->getImages()->first();
            if ($firstImage !== false) {
                $imagePath = $firstImage->getFilePath();
            }
        }

        $imageFilename = $imagePath !== null
            ? basename(str_replace('\\', '/', $imagePath))
            : $artwork->getPhoto();

        return [
            'id' => $artwork->getId(),
            'title' => $artwork->getTitle(),
            'artist' => $artwork->getArtist()?->getName(),
            'category' => $artwork->getCategory()?->getName(),
            'price' => $artwork->getPrice() !== null ? (float) $artwork->getPrice() : null,
            'currency' => $artwork->getCurrency(),
            'image' => $imageFilename !== null && $imageFilename !== ''
                ? '/uploads/artworks/'.rawurlencode($imageFilename)
                : null,
            'url' => $artwork->getId() !== null
                ? $this->generateUrl('app_catalog_show', ['id' => $artwork->getId()])
                : '',
        ];
    }
}
