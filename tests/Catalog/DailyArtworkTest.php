<?php

namespace App\Tests\Catalog;

use App\Entity\Artwork;
use App\Repository\ArtworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DailyArtworkTest extends WebTestCase
{
    public function testAutomaticArtworkIsStableForTheDayAndHomeDisplaysIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $artworkRepository = static::getContainer()->get(ArtworkRepository::class);
        $artworks = $artworkRepository->findBy([], ['id' => 'ASC']);
        $this->assertNotEmpty($artworks);

        $originalSelections = [];
        foreach ($artworks as $artwork) {
            $originalSelections[$artwork->getId()] = $artwork->isDailyFeatured();
            $artwork->setDailyFeatured(false);
        }
        $entityManager->flush();

        try {
            $firstSelection = $artworkRepository->findDailyArtwork();
            $secondSelection = $artworkRepository->findDailyArtwork();
            $this->assertInstanceOf(Artwork::class, $firstSelection);
            $this->assertSame($firstSelection->getId(), $secondSelection->getId());

            $client->request('GET', '/');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('#daily-artwork-title', $firstSelection->getTitle());
            $this->assertSelectorExists('.daily-artwork-visual img[src]');
            $this->assertSelectorExists('.daily-artwork-cta[href="/catalogue/'.$firstSelection->getId().'"]');
        } finally {
            foreach ($artworks as $artwork) {
                $artwork->setDailyFeatured($originalSelections[$artwork->getId()]);
            }
            $entityManager->flush();
        }
    }

    public function testManualSelectionTakesPrecedenceOverDailyRotation(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $artworkRepository = static::getContainer()->get(ArtworkRepository::class);
        $artworks = $artworkRepository->findBy([], ['id' => 'ASC'], 2);
        $this->assertNotEmpty($artworks);

        $originalSelections = [];
        foreach ($artworkRepository->findAll() as $artwork) {
            $originalSelections[$artwork->getId()] = $artwork->isDailyFeatured();
            $artwork->setDailyFeatured(false);
        }

        $selectedArtwork = $artworks[0];
        $selectedArtwork->setDailyFeatured(true);
        $entityManager->flush();

        try {
            $this->assertSame($selectedArtwork->getId(), $artworkRepository->findDailyArtwork()?->getId());
        } finally {
            foreach ($artworkRepository->findAll() as $artwork) {
                $artwork->setDailyFeatured($originalSelections[$artwork->getId()] ?? false);
            }
            $entityManager->flush();
        }
    }
}
