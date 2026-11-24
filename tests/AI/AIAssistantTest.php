<?php

namespace App\Tests\AI;

use App\Service\AI\AIArtworkContextService;
use App\Service\AI\AIChatService;
use App\Service\AI\AIChatException;
use App\Service\AI\AIRecommendationService;
use App\Repository\ArtworkRepository;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AIAssistantTest extends WebTestCase
{
    public function testLegacyAssistantUrlRedirectsToTheGlobalWidget(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ai-assistant');

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-ai-chat-widget]');
        $this->assertSelectorExists('[data-ai-chat-panel][hidden]');
        $this->assertSelectorExists('[data-ai-chat-open][aria-label="Ouvrir l’assistant artistique"]');
        $this->assertSelectorNotExists('.ai-page');
        $this->assertSelectorNotExists('.ai-form');
    }

    public function testAssistantAliasRedirectsToTheGlobalWidget(): void
    {
        $client = static::createClient();
        $client->request('GET', '/assistant');

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-ai-chat-widget]');
        $this->assertSelectorExists('[data-ai-chat-panel][hidden]');
        $this->assertSelectorNotExists('.ai-page');
    }

    public function testFloatingAssistantIsAvailableOnFrontOfficePages(): void
    {
        $client = static::createClient();

        foreach (['/', '/catalogue', '/artistes'] as $path) {
            $client->request('GET', $path);

            $this->assertResponseIsSuccessful(sprintf('Expected %s to render successfully.', $path));
            $this->assertSelectorExists('[data-ai-chat-widget]', sprintf('Expected the global chat widget on %s.', $path));
            $this->assertSelectorExists('[data-ai-chat-panel][hidden]', sprintf('Expected the chat to start closed on %s.', $path));
            $this->assertSelectorExists('nav [data-ai-chat-open]', sprintf('Expected the navigation assistant shortcut on %s.', $path));
        }
    }

    public function testArtworkPageProvidesItsArtworkContextToTheFloatingAssistant(): void
    {
        $client = static::createClient();
        $artwork = static::getContainer()->get(ArtworkRepository::class)->findOneBy([]);

        $this->assertNotNull($artwork);
        $client->request('GET', '/catalogue/'.$artwork->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(sprintf(
            '[data-ai-chat-form][data-artwork-id="%d"]',
            $artwork->getId(),
        ));
        $this->assertSelectorTextContains(
            '.ai-widget-intro',
            sprintf('pour %s', $artwork->getTitle()),
        );
    }

    public function testFloatingAssistantJsonEndpointValidatesEmptyMessages(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/ai-assistant', ['message' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            ['error' => 'Please enter a message.'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testLegacyFormPostCannotRenderTheFullPageAssistant(): void
    {
        $client = static::createClient();
        $client->request('POST', '/ai-assistant', ['message' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertStringNotContainsString('AI Art Assistant - Abstract Art Gallery', (string) $client->getResponse()->getContent());
    }

    public function testAiChatServiceCanBeInstantiatedWithMockedHttpClient(): void
    {
        static::createClient();
        $container = static::getContainer();

        $mockResponse = new MockResponse(json_encode([
            'steps' => [
                [
                    'type' => 'model_output',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Voici quelques œuvres cohérentes avec votre recherche.',
                        ],
                    ],
                ],
            ],
        ]));

        $httpClient = new MockHttpClient($mockResponse, 'https://generativelanguage.googleapis.com');
        $service = new AIChatService(
            $httpClient,
            $container->get(AIArtworkContextService::class),
            $container->get(AIRecommendationService::class),
            new NullLogger(),
            'test-api-key',
        );

        $result = $service->ask('Je cherche une œuvre abstraite sombre');

        $this->assertSame('Voici quelques œuvres cohérentes avec votre recherche.', $result['message']);
        $this->assertIsArray($result['artworks']);
    }

    public function testAssistantContextIncludesTheCurrentArtworkAndItsRealDetails(): void
    {
        static::createClient();
        $container = static::getContainer();
        $artwork = $container->get(ArtworkRepository::class)->findOneBy([]);
        $this->assertNotNull($artwork);

        $context = $container->get(AIArtworkContextService::class)
            ->buildContext('Qui est l’artiste ?', 6, $artwork);

        $this->assertSame($artwork->getId(), $context[0]['id']);
        $this->assertSame($artwork->getTitle(), $context[0]['title']);
        $this->assertSame($artwork->getArtist()?->getName(), $context[0]['artist']);
        $this->assertSame($artwork->getCategory()?->getName(), $context[0]['category']);
        $this->assertSame($artwork->getTechnique(), $context[0]['technique']);
        $this->assertSame($artwork->getDimensions(), $context[0]['dimensions']);
        $this->assertSame($artwork->getStatus(), $context[0]['status']);
        $this->assertSame($artwork->getYearCreated()?->format('Y-m-d'), $context[0]['creation_date']);
        $this->assertIsArray($context[0]['colors']);
    }

    public function testAiChatServiceHandlesHttpErrorGracefully(): void
    {
        static::createClient();
        $container = static::getContainer();

        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 429]),
        ], 'https://generativelanguage.googleapis.com');

        $service = new AIChatService(
            $httpClient,
            $container->get(AIArtworkContextService::class),
            $container->get(AIRecommendationService::class),
            new NullLogger(),
            'test-api-key',
        );

        $this->expectException(AIChatException::class);
        $service->ask('Je cherche une œuvre abstraite sombre');
    }
}
