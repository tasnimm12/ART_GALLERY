<?php

namespace App\Tests\AI;

use App\Entity\ArtworkColor;
use App\Entity\Category;
use App\Repository\ArtworkColorRepository;
use App\Repository\CategoryRepository;
use App\Service\AIArtAdvisorService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AIArtAdvisorServiceTest extends TestCase
{
    public function testStructuredGeminiResponseIsValidatedAgainstExistingColorsAndCategories(): void
    {
        $response = new MockResponse(json_encode([
            'steps' => [[
                'type' => 'model_output',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'style' => 'minimaliste',
                        'colors' => ['bleu', 'white', 'unknown'],
                        'mood' => 'calme',
                        'category' => 'Peinture',
                        'maxPrice' => 800,
                        'minPrice' => null,
                        'room' => 'salon',
                        'keywords' => ['moderne'],
                    ], JSON_THROW_ON_ERROR),
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));
        $service = $this->createService(new MockHttpClient($response));

        $result = $service->analyze('Une œuvre minimaliste, bleue et calme');

        self::assertSame('ai', $result['source']);
        self::assertSame(['bleu', 'blanc'], $result['criteria']['colors']);
        self::assertSame('Peinture', $result['criteria']['category']);
        self::assertSame(800.0, $result['criteria']['maxPrice']);
        self::assertSame('salon', $result['criteria']['room']);
    }

    public function testUnavailableGeminiUsesFrenchAndEnglishLocalFallback(): void
    {
        $response = new MockResponse('temporarily unavailable', ['http_code' => 503]);
        $service = $this->createService(new MockHttpClient($response));

        $result = $service->analyze('Je cherche une œuvre calme, bleue et minimaliste pour mon salon, maximum 800 €');

        self::assertSame('fallback', $result['source']);
        self::assertSame(['bleu'], $result['criteria']['colors']);
        self::assertSame('minimaliste', $result['criteria']['style']);
        self::assertSame('calme', $result['criteria']['mood']);
        self::assertSame('salon', $result['criteria']['room']);
        self::assertSame(800.0, $result['criteria']['maxPrice']);
    }

    public function testNaturalLanguageAndTunisianColorAliasesAreNormalizedLocally(): void
    {
        $service = $this->createService(new MockHttpClient(new MockResponse('unavailable', ['http_code' => 503])));

        $criteria = $service->analyzeLocally('Nheb tableau bleu w blanc fi chambre, budget 800 DT');

        self::assertSame(['bleu', 'blanc'], $criteria['colors']);
        self::assertSame('chambre', $criteria['room']);
        self::assertSame(800.0, $criteria['maxPrice']);
    }

    private function createService(MockHttpClient $httpClient): AIArtAdvisorService
    {
        $blue = (new ArtworkColor())->setName('Bleu')->setSlug('bleu')->setHex('#426C9A');
        $white = (new ArtworkColor())->setName('Blanc')->setSlug('blanc')->setHex('#F5F1E9');
        $colorRepository = $this->createMock(ArtworkColorRepository::class);
        $colorRepository->method('findAll')->willReturn([$blue, $white]);

        $category = (new Category())->setName('Peinture')->setSlug('peinture');
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->method('findAll')->willReturn([$category]);

        return new AIArtAdvisorService($httpClient, $colorRepository, $categoryRepository, new NullLogger(), 'test-api-key');
    }
}
