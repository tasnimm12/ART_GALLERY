<?php

namespace App\Controller;

use App\Repository\ArtworkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use DateTimeImmutable;
use DateTimeZone;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/a-propos', name: 'app_about')]
    public function index(ArtworkRepository $artworkRepository): Response
    {
        $dailyDate = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $galleryLocation = [
            'name' => 'Abstract Art Gallery',
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'address' => 'Tunis, Tunisia',
        ];

        return $this->render('home/index.html.twig', [
            'galleryLocation' => $galleryLocation,
            'dailyArtwork' => $artworkRepository->findDailyArtwork($dailyDate),
            'dailyDate' => $dailyDate,
        ]);
    }
}
