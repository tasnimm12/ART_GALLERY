<?php

namespace App\Tests\AI;

use App\Entity\Artwork;
use App\Entity\ArtworkColor;
use App\Entity\ArtworkColorAnalysis;
use App\Repository\ArtworkRepository;
use App\Service\ArtworkRecommendationService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArtworkRecommendationServiceTest extends WebTestCase
{
    public function testOnlyProvidedCriteriaContributeToTheNormalizedScore(): void
    {
        static::createClient();
        $container = static::getContainer();
        $artwork = (new Artwork())->setTitle('Minimal blue study');
        $blue = (new ArtworkColor())->setName('Bleu')->setSlug('bleu')->setHex('#426C9A');
        $white = (new ArtworkColor())->setName('Blanc')->setSlug('blanc')->setHex('#F5F1E9');
        $artwork->addColorAnalysis((new ArtworkColorAnalysis())
            ->setArtwork($artwork)
            ->setColor($blue)
            ->setHexCode('#2456A6')
            ->setPercentage(60)
            ->setSourceHash(str_repeat('d', 64)));
        $artwork->addColorAnalysis((new ArtworkColorAnalysis())
            ->setArtwork($artwork)
            ->setColor($white)
            ->setHexCode('#F5F5F5')
            ->setPercentage(10)
            ->setSourceHash(str_repeat('d', 64)));

        $service = $container->get(ArtworkRecommendationService::class);
        $criteria = [
            'style' => null,
            'colors' => ['bleu'],
            'mood' => null,
            'category' => null,
            'maxPrice' => null,
            'minPrice' => null,
            'room' => 'salon',
            'keywords' => [],
        ];
        [$singleColorScore] = $service->score($artwork, $criteria);
        self::assertSame(100, $singleColorScore);

        $criteria['colors'] = ['bleu', 'blanc'];
        [$multipleColorScore] = $service->score($artwork, $criteria);
        self::assertGreaterThan(0, $multipleColorScore);
        self::assertLessThan(100, $multipleColorScore);

        $criteria['colors'] = [];
        [$noCriterionScore, $reasons] = $service->score($artwork, $criteria);
        self::assertSame(100, $noCriterionScore);
        self::assertSame(['Sélection parmi les œuvres actuellement disponibles.'], $reasons);
    }

    public function testHigherDominanceRanksFirstAndStrictBudgetExcludesMoreExpensiveArtwork(): void
    {
        static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $templateArtwork = $container->get(ArtworkRepository::class)->findOneBy([]);
        $blue = $entityManager->getRepository(ArtworkColor::class)->findOneBy(['slug' => 'bleu']);
        self::assertNotNull($templateArtwork);
        self::assertNotNull($blue);

        $dominantBlue = $this->createArtwork($templateArtwork->getArtist(), $templateArtwork->getCategory(), 'Dominant blue', '650');
        $littleBlue = $this->createArtwork($templateArtwork->getArtist(), $templateArtwork->getCategory(), 'Small blue', '790');
        $overBudget = $this->createArtwork($templateArtwork->getArtist(), $templateArtwork->getCategory(), 'Over budget', '1200');
        foreach ([[$dominantBlue, 60], [$littleBlue, 8], [$overBudget, 90]] as [$artwork, $percentage]) {
            $analysis = (new ArtworkColorAnalysis())
                ->setArtwork($artwork)
                ->setColor($blue)
                ->setHexCode('#2456A6')
                ->setPercentage($percentage)
                ->setSourceHash(str_repeat('b', 64));
            $artwork->addColorAnalysis($analysis);
            $entityManager->persist($artwork);
            $entityManager->persist($analysis);
        }
        $entityManager->flush();

        try {
            $criteria = [
                'style' => null,
                'colors' => ['bleu'],
                'mood' => null,
                'category' => null,
                'maxPrice' => 800.0,
                'minPrice' => null,
                'room' => null,
                'keywords' => [],
            ];
            $recommendations = $container->get(ArtworkRecommendationService::class)->recommend($criteria);

            self::assertSame($dominantBlue->getId(), $recommendations[0]['artwork']->getId());
            self::assertNotContains($overBudget->getId(), array_map(
                static fn (array $recommendation): ?int => $recommendation['artwork']->getId(),
                $recommendations,
            ));
        } finally {
            $connection = $container->get(Connection::class);
            foreach ([$dominantBlue, $littleBlue, $overBudget] as $artwork) {
                $connection->delete('artwork_color_analysis', ['artwork_id' => $artwork->getId()]);
                $connection->delete('artwork', ['id' => $artwork->getId()]);
            }
        }
    }

    private function createArtwork(?\App\Entity\Artist $artist, ?\App\Entity\Category $category, string $title, string $price): Artwork
    {
        return (new Artwork())
            ->setArtist($artist)
            ->setCategory($category)
            ->setTitle($title)
            ->setPrice($price)
            ->setStatus('AVAILABLE');
    }
}
