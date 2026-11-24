<?php

namespace App\Tests\Catalog;

use App\Entity\ArtworkColor;
use App\Entity\ArtworkColorAnalysis;
use App\Repository\ArtworkRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ColorExplorerTest extends WebTestCase
{
    public function testCatalogCanFilterArtworkByItsAssignedColor(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $artwork = $container->get(ArtworkRepository::class)->findOneBy([]);
        $this->assertNotNull($artwork);

        $color = (new ArtworkColor())
            ->setName('Test couleur')
            ->setSlug('test-couleur-'.bin2hex(random_bytes(4)))
            ->setHex('#123456');
        $entityManager->persist($color);
        $entityManager->flush();

        try {
            $artwork->addColor($color);
            $entityManager->flush();

            $client->request('GET', '/catalogue?color='.$color->getSlug());
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#artworks-grid .art-card');
            $this->assertSelectorTextContains('#artworks-grid .art-card', $artwork->getTitle());
            $this->assertSelectorTextContains('.color-swatch-option.is-selected', $color->getName());

            $client->request('GET', '/catalogue/filter?color='.$color->getSlug(), [], [], [
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $this->assertResponseIsSuccessful();
            $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('id="artworks-grid"', $response['html']);
            $this->assertStringContainsString('class="art-card', $response['html']);
            $this->assertStringContainsString($artwork->getTitle(), $response['html']);
            $this->assertStringContainsString('Couleur : Test couleur', $response['chipsHtml']);
            $this->assertSame(1, $response['displayedItems']);
        } finally {
            $connection = $container->get(Connection::class);
            $connection->delete('artwork_color_link', ['color_id' => $color->getId()]);
            $connection->delete('artwork_color', ['id' => $color->getId()]);
        }
    }

    public function testCatalogCanFilterArtworkByItsAnalyzedColor(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $artwork = $container->get(ArtworkRepository::class)->findOneBy([]);
        $blue = $entityManager->getRepository(ArtworkColor::class)->findOneBy(['slug' => 'bleu']);
        $this->assertNotNull($artwork);
        $this->assertNotNull($blue);

        $analysis = (new ArtworkColorAnalysis())
            ->setArtwork($artwork)
            ->setColor($blue)
            ->setHexCode('#2456A6')
            ->setPercentage(42)
            ->setSourceHash(str_repeat('a', 64));
        $artwork->addColorAnalysis($analysis);
        $entityManager->persist($analysis);
        $entityManager->flush();

        try {
            $client->request('GET', '/catalogue?color=bleu');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('#artworks-grid .art-card', $artwork->getTitle());
        } finally {
            $container->get(Connection::class)->delete('artwork_color_analysis', ['id' => $analysis->getId()]);
        }
    }
}
