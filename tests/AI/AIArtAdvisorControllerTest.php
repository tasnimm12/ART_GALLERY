<?php

namespace App\Tests\AI;

use App\Entity\Artwork;
use App\Entity\ArtworkColor;
use App\Entity\ArtworkColorAnalysis;
use App\Repository\ArtworkColorRepository;
use App\Repository\ArtworkRepository;
use App\Repository\CategoryRepository;
use App\Service\AIArtAdvisorService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AIArtAdvisorControllerTest extends WebTestCase
{
    public function testAdvisorPageUsesTheSharedSiteLayoutAndShowsSuggestions(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ai-advisor');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'AI Art Advisor');
        self::assertSelectorExists('[data-advisor-form]');
        self::assertSelectorExists('[data-advisor-suggestion]');
        self::assertSelectorExists('script[src*="/assets/ai-advisor-"]');
        self::assertSelectorExists('[data-ai-chat-widget]');
        self::assertSelectorExists('[data-ai-chat-panel][hidden]');
        self::assertSelectorExists('.navbar');
        self::assertSelectorExists('.footer');
    }

    public function testAboutPagePresentsAdvisorAndLinksToItsExistingPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/a-propos');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.advisor-presentation__label', 'Intelligence artificielle');
        self::assertSelectorTextContains('.advisor-presentation__title', 'AI Art Advisor');
        self::assertSelectorTextContains('.advisor-presentation__subtitle', 'Une nouvelle façon de découvrir l’œuvre qui vous correspond.');
        self::assertSelectorTextContains('.advisor-presentation', 'Que vous recherchiez une œuvre minimaliste');
        self::assertSelectorExists(sprintf(
            'a[href="%s"]',
            static::getContainer()->get('router')->generate('app_ai_advisor'),
        ));
        self::assertSelectorExists('[data-ai-chat-widget]');

        $client->clickLink('✦ Découvrir AI Art Advisor');
        self::assertResponseIsSuccessful();
        self::assertSame('/ai-advisor', $client->getRequest()->getPathInfo());
        self::assertSelectorTextContains('h1', 'AI Art Advisor');
        self::assertSelectorExists('[data-advisor-form]');
    }

    public function testAdvisorApiRejectsInvalidOrEmptyRequests(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/ai-advisor', [], [], ['CONTENT_TYPE' => 'application/json'], '{"message":""}');
        self::assertResponseStatusCodeSame(400);

        $client->request('POST', '/api/ai-advisor', [], [], ['CONTENT_TYPE' => 'application/json'], '{invalid');
        self::assertResponseStatusCodeSame(400);
    }

    public function testAdvisorApiReturnsStructuredFallbackResultsAndStrictBudget(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $templateArtwork = $container->get(ArtworkRepository::class)->findOneBy([]);
        $blue = $entityManager->getRepository(ArtworkColor::class)->findOneBy(['slug' => 'bleu']);
        self::assertNotNull($templateArtwork);
        self::assertNotNull($blue);

        $artwork = (new Artwork())
            ->setArtist($templateArtwork->getArtist())
            ->setCategory($templateArtwork->getCategory())
            ->setTitle('Blue within zero budget')
            ->setPrice('0');
        $analysis = (new ArtworkColorAnalysis())
            ->setArtwork($artwork)
            ->setColor($blue)
            ->setHexCode('#2456A6')
            ->setPercentage(60)
            ->setSourceHash(str_repeat('c', 64));
        $artwork->addColorAnalysis($analysis);
        $entityManager->persist($artwork);
        $entityManager->persist($analysis);
        $entityManager->flush();

        $container->set(AIArtAdvisorService::class, new AIArtAdvisorService(
            new MockHttpClient(new MockResponse('unavailable', ['http_code' => 503])),
            $container->get(ArtworkColorRepository::class),
            $container->get(CategoryRepository::class),
            new NullLogger(),
            'test-api-key',
        ));

        try {
            $client->request(
                'POST',
                '/api/ai-advisor',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                json_encode(['message' => 'Je cherche une œuvre bleue à moins de 0 €'], JSON_THROW_ON_ERROR),
            );

            self::assertResponseIsSuccessful();
            $payload = json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            self::assertSame('fallback', $payload['source']);
            self::assertEquals(0, $payload['criteria']['maxPrice']);
            self::assertCount(1, $payload['recommendations']);
            self::assertSame($artwork->getId(), $payload['recommendations'][0]['id']);
            self::assertFalse($payload['recommendations'][0]['favorite']['authenticated']);
            self::assertArrayHasKey('token', $payload['recommendations'][0]['favorite']);
        } finally {
            $connection = $container->get(Connection::class);
            $connection->delete('artwork_color_analysis', ['artwork_id' => $artwork->getId()]);
            $connection->delete('artwork', ['id' => $artwork->getId()]);
        }
    }
}
