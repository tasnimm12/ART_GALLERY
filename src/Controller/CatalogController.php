<?php

namespace App\Controller;

use App\DTO\ArtworkSearchDTO;
use App\Entity\Artwork;
use App\Entity\User;
use App\Form\ArtworkSearchType;
use App\Repository\ArtworkRepository;
use App\Repository\ArtworkColorRepository;
use App\Repository\ArtworkColorAnalysisRepository;
use App\Repository\FavoriteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Persistence\ManagerRegistry;

class CatalogController extends AbstractController
{
    #[Route('/catalogue', name: 'app_catalog_index')]
    public function index(Request $request, ArtworkRepository $artworkRepository, ArtworkColorRepository $colorRepository, FavoriteRepository $favoriteRepository): Response
    {
        $searchDTO = new ArtworkSearchDTO();
        $form = $this->createForm(ArtworkSearchType::class, $searchDTO);
        $form->handleRequest($request);

        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $artworkRepository->findByFilters($searchDTO, $page, 9);
        $favoriteArtworkIds = $this->findFavoriteArtworkIds($pagination['items'], $favoriteRepository);

        return $this->render('catalog/index.html.twig', [
            'form' => $form,
            'artworks' => $pagination['items'],
            'displayedItems' => $pagination['displayedItems'],
            'totalPages' => $pagination['totalPages'],
            'currentPage' => $pagination['currentPage'],
            'searchDTO' => $searchDTO,
            'favoriteArtworkIds' => $favoriteArtworkIds,
            'artworkColors' => $colorRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/catalogue/filter', name: 'app_catalog_filter', methods: ['GET'])]
    public function filter(
        Request $request,
        ArtworkRepository $artworkRepository,
        ArtworkColorRepository $colorRepository,
        FavoriteRepository $favoriteRepository,
    ): JsonResponse
    {
        $searchDTO = new ArtworkSearchDTO();
        $form = $this->createForm(ArtworkSearchType::class, $searchDTO);
        $form->handleRequest($request);

        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $artworkRepository->findByFilters($searchDTO, $page, 9);
        $favoriteArtworkIds = $this->findFavoriteArtworkIds($pagination['items'], $favoriteRepository);

        $artworkColors = $colorRepository->findBy([], ['name' => 'ASC']);
        $html = $this->renderView('catalog/_artworks_grid.html.twig', [
            'artworks' => $pagination['items'],
            'favoriteArtworkIds' => $favoriteArtworkIds,
        ]);
        $chipsHtml = $this->renderView('catalog/_filter_chips.html.twig', [
            'searchDTO' => $searchDTO,
            'artworkColors' => $artworkColors,
        ]);
        $errorsHtml = $this->renderView('catalog/_filter_errors.html.twig', [
            'form' => $form->createView(),
        ]);

        return $this->json([
            'html' => $html,
            'chipsHtml' => $chipsHtml,
            'errorsHtml' => $errorsHtml,
            'displayedItems' => $pagination['displayedItems'],
        ]);
    }

    #[Route('/catalogue/{id}', name: 'app_catalog_show', requirements: ['id' => '\d+'])]
    public function show(
        Artwork $artwork,
        ManagerRegistry $doctrine,
        FavoriteRepository $favoriteRepository,
        ArtworkColorAnalysisRepository $colorAnalysisRepository,
    ): Response
    {
        $pendingOrder = null;
        $user = $this->getUser();
        $favoriteArtworkIds = $user instanceof User
            ? $favoriteRepository->findArtworkIdsForUserAndArtworkIds($user, [(int) $artwork->getId()])
            : [];

        if ($user) {
            $em = $doctrine->getManager();
            // Find a pending payment related to this artwork for the current user
            $qb = $em->createQueryBuilder()
                ->select('p')
                ->from(\App\Entity\Payment::class, 'p')
                ->join('p.order', 'o')
                ->join('o.items', 'it')
                ->where('p.status = :pstatus')
                ->andWhere('o.status = :ostatus')
                ->andWhere('it.artwork = :artwork')
                ->andWhere('o.user = :user')
                ->setParameter('pstatus', 'PENDING')
                ->setParameter('ostatus', 'PENDING_PAYMENT')
                ->setParameter('artwork', $artwork)
                ->setParameter('user', $user)
                ->setMaxResults(1);

            $payment = $qb->getQuery()->getOneOrNullResult();
            if ($payment) {
                $pendingOrder = $payment->getOrder();
            }
        }

        return $this->render('catalog/show.html.twig', [
            'artwork' => $artwork,
            'pendingOrder' => $pendingOrder,
            'favoriteArtworkIds' => $favoriteArtworkIds,
            'colorAnalyses' => $colorAnalysisRepository->findForArtwork($artwork),
        ]);
    }

    /**
     * @param iterable<Artwork> $artworks
     *
     * @return list<int>
     */
    private function findFavoriteArtworkIds(iterable $artworks, FavoriteRepository $favoriteRepository): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $artworkIds = [];
        foreach ($artworks as $artwork) {
            $artworkIds[] = (int) $artwork->getId();
        }

        return $favoriteRepository->findArtworkIdsForUserAndArtworkIds($user, $artworkIds);
    }
}
