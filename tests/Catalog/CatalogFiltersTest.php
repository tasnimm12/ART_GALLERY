<?php

namespace App\Tests\Catalog;

use App\DTO\ArtworkSearchDTO;
use App\Entity\Artist;
use App\Entity\Artwork;
use App\Entity\ArtworkColor;
use App\Entity\Category;
use App\Repository\ArtworkRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CatalogFiltersTest extends WebTestCase
{
    public function testInvalidPriceRangeIsReportedWithoutServerError(): void
    {
        $client = static::createClient();
        $client->request('GET', '/catalogue', ['minPrice' => '100', 'maxPrice' => '10']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            '#catalog-filter-errors',
            'Le prix minimum doit être inférieur ou égal au prix maximum.',
        );
        self::assertSame(0, $client->getCrawler()->filter('.color-swatch-option.is-selected')->count());

        $client->request('GET', '/catalogue/filter?minPrice=100&maxPrice=10', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(0, $response['displayedItems']);
        self::assertStringContainsString(
            'Le prix minimum doit être inférieur ou égal au prix maximum.',
            $response['errorsHtml'],
        );
    }

    public function testCombinedFiltersAndRenderedCountStayInSync(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));

        $artist = (new Artist())->setName('Vincent van Gogh ' . $suffix);
        $matchingCategory = (new Category())
            ->setName('Filter match ' . $suffix)
            ->setSlug('filter-match-' . $suffix);
        $otherCategory = (new Category())
            ->setName('Filter other ' . $suffix)
            ->setSlug('filter-other-' . $suffix);
        $color = (new ArtworkColor())
            ->setName('Filter blue ' . $suffix)
            ->setSlug('filter-blue-' . $suffix)
            ->setHex('#123456');
        foreach ([$artist, $matchingCategory, $otherCategory, $color] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $matchingArtwork = (new Artwork())
            ->setTitle('Matching artwork ' . $suffix)
            ->setArtist($artist)
            ->setCategory($matchingCategory)
            ->setPrice('50.00')
            ->setStatus('AVAILABLE')
            ->addColor($color);
        $nonMatchingArtwork = (new Artwork())
            ->setTitle('Non-matching artwork ' . $suffix)
            ->setArtist($artist)
            ->setCategory($otherCategory)
            ->setPrice('50.00')
            ->setStatus('SOLD');
        $entityManager->persist($matchingArtwork);
        $entityManager->persist($nonMatchingArtwork);

        $artworkIds = [];

        try {
            $entityManager->flush();
            $artworkIds = [(int) $matchingArtwork->getId(), (int) $nonMatchingArtwork->getId()];

            $filters = new ArtworkSearchDTO();
            $filters->q = $artist->getName();
            $filters->artist = $artist;
            $filters->category = $matchingCategory;
            $filters->status = 'AVAILABLE';
            $filters->minPrice = 0.0;
            $filters->maxPrice = 50.0;
            $filters->color = $color->getSlug();

            $results = $container->get(ArtworkRepository::class)->findByFilters($filters);
            self::assertCount(1, $results['items']);
            $matchingResults = iterator_to_array($results['items'], false);
            self::assertSame($matchingArtwork->getId(), $matchingResults[0]->getId());
            self::assertSame(1, $results['displayedItems']);

            $titleFilters = new ArtworkSearchDTO();
            $titleFilters->q = $suffix;
            $firstPage = $container->get(ArtworkRepository::class)->findByFilters($titleFilters, 1, 1);
            self::assertSame(2, $firstPage['totalItems']);
            self::assertSame(1, $firstPage['displayedItems']);

            $query = http_build_query([
                'q' => $filters->q,
                'artist' => $artist->getId(),
                'category' => $matchingCategory->getId(),
                'status' => $filters->status,
                'minPrice' => '0',
                'maxPrice' => '50',
                'color' => $color->getSlug(),
            ]);
            $crawler = $client->request('GET', '/catalogue?' . $query);
            self::assertResponseIsSuccessful();
            self::assertSame(1, $crawler->filter('#artworks-grid .art-card')->count());
            self::assertSame('1', $crawler->filter('#catalog-result-count')->text());
            self::assertSame(1, $crawler->filter('.color-swatch-option.is-selected')->count());
            self::assertSame('false', $crawler->filter('.color-swatch-all')->attr('aria-pressed'));
            self::assertStringContainsString('Artiste : ' . $artist->getName(), $crawler->filter('#active-filter-chips')->text());
            self::assertStringNotContainsString('Artiste : ' . $artist->getId(), $crawler->filter('#active-filter-chips')->text());
            self::assertStringContainsString('0 €', $crawler->filter('#active-filter-chips')->text());

            $client->request('GET', '/catalogue/filter?' . $query, [], [], [
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            self::assertResponseIsSuccessful();
            $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(1, $response['displayedItems']);
            self::assertStringContainsString('Matching artwork ' . $suffix, $response['html']);
            self::assertStringNotContainsString('id="artworks-grid"', $response['html']);
            self::assertStringNotContainsString('Non-matching artwork ' . $suffix, $response['html']);
            self::assertStringContainsString('Artiste : ' . $artist->getName(), $response['chipsHtml']);
        } finally {
            $connection = $container->get(Connection::class);
            foreach ($artworkIds as $artworkId) {
                $connection->delete('artwork_color_link', ['artwork_id' => $artworkId]);
                $connection->delete('artwork', ['id' => $artworkId]);
            }
            $connection->delete('artwork_color', ['id' => $color->getId()]);
            $connection->delete('category', ['id' => $matchingCategory->getId()]);
            $connection->delete('category', ['id' => $otherCategory->getId()]);
            $connection->delete('artist', ['id' => $artist->getId()]);
        }
    }
}
