<?php

namespace App\Tests\Catalog;

use App\Entity\Artwork;
use App\Entity\ArtworkImage;
use App\Repository\ArtistRepository;
use App\Repository\ArtworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CatalogTest extends WebTestCase
{
    public function testCatalogIndexIsPubliclyAccessible(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/catalogue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Galerie & Catalogue');
        $this->assertGreaterThan(0, $crawler->filter('.artwork-card')->count());
    }

    public function testCatalogKeywordFilter(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/catalogue', ['q' => 'Symphonie']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.artwork-title', 'Symphonie en Bleu Majeur');
    }

    public function testCatalogPriceFilter(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/catalogue', [
            'minPrice' => 3000,
            'maxPrice' => 4000,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('.artwork-card')->count());
    }

    public function testArtworkDetailView(): void
    {
        $client = static::createClient();
        $artworkRepo = static::getContainer()->get(ArtworkRepository::class);
        $artwork = $artworkRepo->findOneBy([]);

        $this->assertNotNull($artwork);

        $client->request('GET', '/catalogue/' . $artwork->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $artwork->getTitle());
        $this->assertSelectorExists('link[href*="artwork-gallery"]');
        $this->assertSelectorExists('.main-image-box[data-artwork-gallery]');

        if ($artwork->getImages()->count() > 0) {
            $this->assertSelectorExists('.gallery-stage [data-gallery-main]');
            $galleryCrawler = $client->getCrawler();
            $thumbnails = $galleryCrawler->filter('[data-gallery-thumb]');
            $thumbnailCount = $thumbnails->count();
            $this->assertSame($artwork->getImages()->count(), $thumbnailCount);
            $this->assertSame(1, $thumbnails->filter('[aria-pressed="true"]')->count());
            $this->assertSame(
                $thumbnails->filter('[aria-pressed="true"]')->attr('data-gallery-index'),
                $galleryCrawler->filter('[data-gallery-main]')->attr('data-gallery-index'),
            );
            if ($thumbnailCount > 1) {
                $this->assertSelectorExists('[data-gallery-previous]');
                $this->assertSelectorExists('[data-gallery-next]');
                $this->assertCount($thumbnailCount, $galleryCrawler->filter('[data-gallery-page]'));
                $this->assertSelectorExists('dialog[data-gallery-lightbox]');
            }
        }
    }

    public function testArtworkGalleryRendersSynchronizedActiveImageControls(): void
    {
        $client = static::createClient();
        $existingArtwork = static::getContainer()->get(ArtworkRepository::class)->findOneBy([]);
        $this->assertNotNull($existingArtwork);

        $galleryArtwork = (new Artwork())
            ->setTitle('Test artwork gallery')
            ->setArtist($existingArtwork->getArtist())
            ->setCategory($existingArtwork->getCategory())
            ->setPrice('100.00');

        for ($index = 0; $index < 4; ++$index) {
            $galleryArtwork->addImage(
                (new ArtworkImage())
                    ->setFilePath(sprintf('https://example.test/gallery-image-%d.jpg', $index))
                    ->setAltText(sprintf('Gallery image %d', $index + 1))
                    ->setDisplayOrder($index)
                    ->setIsPrimary($index === 2),
            );
        }

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($galleryArtwork);
        $entityManager->flush();

        try {
            $client->request('GET', '/catalogue/'.$galleryArtwork->getId());
            $this->assertResponseIsSuccessful();

            $crawler = $client->getCrawler();
            $thumbnails = $crawler->filter('[data-gallery-thumb]');
            $this->assertCount(4, $thumbnails);
            $this->assertSame(1, $thumbnails->filter('[aria-pressed="true"]')->count());
            $this->assertSame('2', $thumbnails->filter('[aria-pressed="true"]')->attr('data-gallery-index'));
            $this->assertSame('2', $crawler->filter('[data-gallery-main]')->attr('data-gallery-index'));
            $this->assertCount(4, $crawler->filter('[data-gallery-page]'));
            $this->assertSelectorExists('[data-gallery-previous]');
            $this->assertSelectorExists('[data-gallery-next]');
            $this->assertSelectorExists('dialog[data-gallery-lightbox]');
        } finally {
            $entityManager->remove($galleryArtwork);
            $entityManager->flush();
        }
    }

    public function testArtistsDirectoryAndProfile(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/artistes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Les Artistes de la Galerie');
        $this->assertGreaterThan(0, $crawler->filter('.artist-card-full')->count());

        $artistRepo = static::getContainer()->get(ArtistRepository::class);
        $artist = $artistRepo->findOneBy([]);
        $this->assertNotNull($artist);

        $client->request('GET', '/artistes/' . $artist->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $artist->getName());
    }
}
