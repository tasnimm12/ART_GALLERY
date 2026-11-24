<?php

namespace App\Controller\Admin;

use App\Entity\Artwork;
use App\Service\AI\ColorAnalysisException;
use App\Service\AI\ColorAnalysisService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ArtworkColorAnalysisController extends AbstractController
{
    #[Route('/admin/artwork/analyze-upload-colors', name: 'admin_artwork_analyze_upload_colors', methods: ['POST'])]
    public function analyzeUploadedImage(
        Request $request,
        ColorAnalysisService $colorAnalysisService,
    ): JsonResponse {
        $csrfToken = $request->request->get('_token');
        if (!is_string($csrfToken) || !$this->isCsrfTokenValid('analyze-artwork-upload-colors', $csrfToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $image = $request->files->get('image');
        if (!$image instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Sélectionnez une image à analyser.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            return new JsonResponse(['colors' => $colorAnalysisService->analyzeUploadedImage($image)]);
        } catch (ColorAnalysisException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/admin/artwork/{id}/analyze-colors', name: 'admin_artwork_analyze_colors', methods: ['POST'])]
    public function analyze(
        Artwork $artwork,
        Request $request,
        ColorAnalysisService $colorAnalysisService,
        AdminUrlGenerator $adminUrlGenerator,
    ): Response {
        $csrfToken = $request->request->get('_token');
        if (!is_string($csrfToken) || !$this->isCsrfTokenValid('analyze-colors-'.$artwork->getId(), $csrfToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $result = $colorAnalysisService->analyzeAndPersist($artwork);
            $this->addFlash('success', sprintf('Palette enregistrée : %d couleurs détectées.', count($result['colors'])));
        } catch (ColorAnalysisException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        $editUrl = $adminUrlGenerator
            ->setController(ArtworkCrudController::class)
            ->setAction(Crud::PAGE_EDIT)
            ->setEntityId($artwork->getId())
            ->generateUrl();

        return $this->redirect($editUrl);
    }
}
