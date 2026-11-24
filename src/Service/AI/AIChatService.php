<?php

namespace App\Service\AI;

use Symfony\Component\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Artwork;

final class AIChatService
{
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/interactions';
    private const GEMINI_MODEL = 'gemini-3.1-flash-lite';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AIArtworkContextService $artworkContextService,
        private readonly AIRecommendationService $recommendationService,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
    ) {
    }

    /**
     * @return array{message: string, artworks: array<int, \App\Entity\Artwork>}
     */
    public function ask(string $message, ?Artwork $currentArtwork = null): array
    {
        $userMessage = trim($message);
        if ($userMessage === '') {
            throw new \InvalidArgumentException('The user message cannot be empty.');
        }

        if (empty($this->apiKey)) {
            throw new AIChatException('The artistic assistant is not configured.');
        }

        $context = $this->artworkContextService->buildContext($userMessage, 6, $currentArtwork);
        $systemPrompt = $this->buildSystemPrompt();

        try {
            $response = $this->httpClient->request('POST', self::GEMINI_URL, [
                'headers' => [
                    'x-goog-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::GEMINI_MODEL,
                    'input' => $systemPrompt . "\n\nUSER REQUEST:\n" . $userMessage . "\n\nGALLERY DATA:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'store' => false,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface | \Exception $exception) {
            $this->logger->error('Gemini request failed.', [
                'exception_class' => $exception::class,
            ]);

            throw new AIChatException('The artistic assistant request failed.', 0, $exception);
        }

        if ($statusCode >= 400) {
            $providerMessage = $payload['error']['message'] ?? null;
            if (is_string($providerMessage)) {
                $providerMessage = substr(str_replace($this->apiKey, '[redacted]', $providerMessage), 0, 500);
            }

            $this->logger->error('Gemini returned an unsuccessful HTTP status.', [
                'status_code' => $statusCode,
                'provider_message' => is_string($providerMessage) ? $providerMessage : null,
            ]);

            throw new AIChatException('The artistic assistant request failed.');
        }

        $text = $this->extractTextFromPayload($payload);
        if ($text === '') {
            $this->logger->warning('Gemini returned no text content.');

            throw new AIChatException('The artistic assistant returned no answer.');
        }

        return [
            'message' => $text,
            'artworks' => $this->recommendationService->recommend($userMessage, 3),
        ];
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are the AI Art Assistant of Abstract Art Gallery.

You help visitors discover artworks available in this gallery.
You are specialized in abstract art, contemporary art, artists, artwork styles, colors, moods, techniques, and recommendations.

Rules:
1. Be polite and concise.
2. Ask questions if the user's taste is unclear.
3. Help users choose artworks based on style, mood, colors, room, budget and preferences.
4. Never invent an artwork.
5. Never invent an artist.
6. Never invent a price.
7. Never claim an artwork is available unless it exists in the provided gallery data.
8. Use the gallery data as the source of truth.
9. If information is missing, say that it is not available.
10. Do not make professional valuation claims.
11. The final answer should be easy to understand.

When the request includes a CURRENT ARTWORK, answer questions about it using only its supplied fields.
Clearly distinguish that artwork from other gallery recommendations.
PROMPT;
    }

    private function extractTextFromPayload(array $payload): string
    {
        $steps = $payload['steps'] ?? [];
        foreach ($steps as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach ($step['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'text' && isset($content['text']) && is_string($content['text'])) {
                    return trim($content['text']);
                }
            }
        }

        return '';
    }

}
