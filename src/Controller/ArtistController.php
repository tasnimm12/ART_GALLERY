<?php

namespace App\Controller;

use App\Entity\Artist;
use App\Repository\ArtistRepository;
use App\Repository\ArtworkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArtistController extends AbstractController
{
    #[Route('/artistes', name: 'app_artist_index')]
    public function index(ArtistRepository $artistRepository): Response
    {
        $artistsWithCount = $artistRepository->findAllWithArtworksCount();

        return $this->render('artist/index.html.twig', [
            'artistsWithCount' => $artistsWithCount,
        ]);
    }

    #[Route('/artistes/{id}', name: 'app_artist_show', requirements: ['id' => '\d+'])]
    public function show(Artist $artist, ArtworkRepository $artworkRepository): Response
    {
        $artworks = $artworkRepository->findBy(['artist' => $artist], ['createdAt' => 'DESC']);

        return $this->render('artist/show.html.twig', [
            'artist' => $artist,
            'artworks' => $artworks,
        ]);
    }
}
