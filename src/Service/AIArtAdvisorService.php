<?php

namespace App\Service;

use App\Entity\ArtworkColor;
use App\Repository\ArtworkColorRepository;
use App\Repository\CategoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AIArtAdvisorService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/interactions';
    private const MODEL = 'gemini-3.1-flash-lite';

    /** @var array<string, string> */
    private const COLOR_ALIASES = [
        'black' => 'noir', 'noir' => 'noir', 'noire' => 'noir',
        'white' => 'blanc', 'blanc' => 'blanc', 'blanche' => 'blanc',
        'red' => 'rouge', 'rouge' => 'rouge',
        'blue' => 'bleu', 'bleu' => 'bleu', 'bleue' => 'bleu', 'bleues' => 'bleu', 'bleus' => 'bleu', 'azur' => 'bleu',
        'green' => 'vert', 'verte' => 'vert', 'verts' => 'vert', 'vertes' => 'vert',
        'yellow' => 'jaune', 'jaune' => 'jaune',
        'orange' => 'orange', 'oranges' => 'orange',
        'purple' => 'violet', 'violet' => 'violet', 'violette' => 'violet',
        'multicolored' => 'multicolore', 'multicolore' => 'multicolore',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ArtworkColorRepository $colorRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
    ) {
    }

    /**
     * @return array{criteria: array{style: ?string, colors: list<string>, mood: ?string, category: ?string, maxPrice: ?float, minPrice: ?float, room: ?string, keywords: list<string>}, source: 'ai'|'fallback'}
     */
    public function analyze(string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('La demande ne peut pas être vide.');
        }

        if ($this->apiKey !== '') {
            try {
                $criteria = $this->requestCriteria($message);
                if ($criteria !== null) {
                    return ['criteria' => $criteria, 'source' => 'ai'];
                }
            } catch (TransportExceptionInterface|DecodingExceptionInterface|\JsonException $exception) {
                $this->logger->warning('AI Art Advisor indisponible ; utilisation du fallback local.', [
                    'exception_class' => $exception::class,
                ]);
            }
        } else {
            $this->logger->warning('AI Art Advisor sans clé API ; utilisation du fallback local.');
        }

        return ['criteria' => $this->analyzeLocally($message), 'source' => 'fallback'];
    }

    /**
     * @return array{style: ?string, colors: list<string>, mood: ?string, category: ?string, maxPrice: ?float, minPrice: ?float, room: ?string, keywords: list<string>}
     */
    public function analyzeLocally(string $message): array
    {
        $normalized = $this->normalizeText($message);
        $colorPositions = [];
        foreach (self::COLOR_ALIASES as $alias => $slug) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($alias, '/').'(?![\p{L}\p{N}])/u', $normalized, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $position = $matches[0][1];
                $colorPositions[$slug] = min($colorPositions[$slug] ?? PHP_INT_MAX, $position);
            }
        }
        asort($colorPositions, SORT_NUMERIC);
        $colors = array_keys($colorPositions);

        $knownSlugs = [];
        foreach ($this->colorRepository->findAll() as $color) {
            if ($color instanceof ArtworkColor) {
                $knownSlugs[$color->getSlug()] = true;
            }
        }
        $colors = array_values(array_unique(array_filter($colors, static fn (string $slug): bool => isset($knownSlugs[$slug]))));

        $categories = $this->categoryRepository->findAll();
        $category = null;
        foreach ($categories as $knownCategory) {
            $categoryTokens = array_filter([$this->normalizeText($knownCategory->getName()), $this->normalizeText($knownCategory->getSlug())]);
            foreach ($categoryTokens as $token) {
                if ($token !== '' && str_contains($normalized, $token)) {
                    $category = $knownCategory->getName();
                    break 2;
                }
            }
        }

        $style = $this->firstMention($normalized, [
            'minimaliste' => ['minimaliste', 'minimalist', 'minimal'],
            'moderne' => ['moderne', 'modern', 'contemporain', 'contemporary'],
            'abstrait' => ['abstrait', 'abstraite', 'abstract'],
            'classique' => ['classique', 'classic', 'classical'],
            'geometrique' => ['geometrique', 'geometric'],
            'expressionniste' => ['expressionniste', 'expressionist'],
        ]);
        $mood = $this->firstMention($normalized, [
            'calme' => ['calme', 'calm', 'serene', 'serenite', 'paisible', 'relaxant', 'relaxing'],
            'energique' => ['energique', 'energetic', 'dynamique', 'dynamic', 'vibrant', 'vibrante'],
            'joyeux' => ['joyeux', 'joyeuse', 'joyful', 'happy'],
            'melancolique' => ['melancolique', 'melancholic', 'melancholy'],
            'chaleureux' => ['chaleureux', 'warm', 'chaleureuse'],
            'apaisant' => ['apaisant', 'soothing', 'peaceful'],
            'audacieux' => ['audacieux', 'bold', 'intense'],
        ]);
        $room = $this->firstMention($normalized, [
            'salon' => ['salon', 'living room', 'sejour'],
            'chambre' => ['chambre', 'bedroom'],
            'bureau' => ['bureau', 'office', 'workspace'],
            'salle a manger' => ['salle a manger', 'dining room'],
            'entree' => ['entree', 'hallway'],
        ]);
        [$minPrice, $maxPrice] = $this->extractBudget($normalized);

        $criteriaWords = array_merge(array_keys(self::COLOR_ALIASES), [
            'oeuvre', 'oeuvres', 'art', 'artwork', 'painting', 'piece', 'cherche', 'recherche', 'veux',
            'want', 'looking', 'find', 'for', 'the', 'a', 'an', 'and', 'avec', 'pour', 'dans', 'mon', 'ma',
            'une', 'un', 'des', 'de', 'du', 'et', 'calme', 'calm', 'mood', 'style', 'room', 'salon',
            'chambre', 'bureau', 'maximum', 'max', 'budget', 'moins', 'under', 'below', 'than', 'euros',
            'euro', 'eur', 'dt', 'dtn', 'tnd', 'modern', 'moderne', 'minimaliste', 'minimalist', 'abstrait',
            'abstract', 'contemporain', 'contemporary',
            'belle', 'beau', 'beautiful', 'pretty', 'oeuvre', 'artwork',
        ]);
        $criteriaWords = array_map(fn (string $word): string => $this->normalizeText($word), $criteriaWords);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 3 || in_array($word, $criteriaWords, true) || is_numeric($word)) {
                continue;
            }
            $keywords[] = $word;
        }

        return [
            'style' => $style,
            'colors' => $colors,
            'mood' => $mood,
            'category' => $category,
            'maxPrice' => $maxPrice,
            'minPrice' => $minPrice,
            'room' => $room,
            'keywords' => array_slice(array_values(array_unique($keywords)), 0, 8),
        ];
    }

    /**
     * @return array{style: ?string, colors: list<string>, mood: ?string, category: ?string, maxPrice: ?float, minPrice: ?float, room: ?string, keywords: list<string>}|null
     */
    private function requestCriteria(string $message): ?array
    {
        $categories = array_map(static fn ($category): string => $category->getName(), $this->categoryRepository->findAll());
        $colors = array_map(static fn (ArtworkColor $color): string => $color->getName(), $this->colorRepository->findAll());
        $prompt = sprintf(
            "You are the preference parser for Abstract Art Gallery. Extract only preferences explicitly stated by the user. Understand French, English, and French/Tunisian mixed language. Normalize colors to names from this list: %s. A category must be one of these existing categories: %s. Do not invent catalog metadata. Return ONLY JSON with keys style, colors, mood, category, maxPrice, minPrice, room, keywords. Use null for absent scalar values, [] for absent arrays, and numeric budget values without currency symbols.",
            implode(', ', $colors),
            implode(', ', $categories),
        );
        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'input' => $prompt."\n\nUSER REQUEST:\n".$message,
                'store' => false,
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            $this->logger->warning('AI Art Advisor returned an unsuccessful response.', [
                'status_code' => $response->getStatusCode(),
            ]);

            return null;
        }

        $payload = $response->toArray(false);
        $text = $this->extractText($payload);
        if ($text === '') {
            return null;
        }
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? '';
        $decoded = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            return null;
        }

        return $this->normalizeCriteria($decoded);
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @return array{style: ?string, colors: list<string>, mood: ?string, category: ?string, maxPrice: ?float, minPrice: ?float, room: ?string, keywords: list<string>}|null
     */
    private function normalizeCriteria(array $decoded): ?array
    {
        $colorByName = [];
        foreach ($this->colorRepository->findAll() as $color) {
            if ($color instanceof ArtworkColor) {
                $colorByName[$this->normalizeText($color->getName())] = $color->getSlug();
                $colorByName[$this->normalizeText($color->getSlug())] = $color->getSlug();
            }
        }
        $colors = [];
        if (is_array($decoded['colors'] ?? null)) {
            foreach (array_slice($decoded['colors'], 0, 4) as $color) {
                if (is_string($color)) {
                    $normalizedColor = $this->normalizeText($color);
                    $slug = $colorByName[$normalizedColor] ?? self::COLOR_ALIASES[$normalizedColor] ?? null;
                    if (is_string($slug) && isset($colorByName[$slug])) {
                        $colors[] = $slug;
                    }
                }
            }
        }

        $categoryByName = [];
        foreach ($this->categoryRepository->findAll() as $category) {
            $categoryByName[$this->normalizeText($category->getName())] = $category->getName();
            $categoryByName[$this->normalizeText($category->getSlug())] = $category->getName();
        }
        $categoryValue = $decoded['category'] ?? null;
        $category = is_string($categoryValue) ? ($categoryByName[$this->normalizeText($categoryValue)] ?? null) : null;

        $minPrice = $this->validPrice($decoded['minPrice'] ?? null);
        $maxPrice = $this->validPrice($decoded['maxPrice'] ?? null);
        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            return null;
        }

        return [
            'style' => $this->normalizeStyle($this->validText($decoded['style'] ?? null)),
            'colors' => array_values(array_unique($colors)),
            'mood' => $this->normalizeMood($this->validText($decoded['mood'] ?? null)),
            'category' => $category,
            'maxPrice' => $maxPrice,
            'minPrice' => $minPrice,
            'room' => $this->validText($decoded['room'] ?? null),
            'keywords' => $this->validStringList($decoded['keywords'] ?? null, 8),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractText(array $payload): string
    {
        foreach ($payload['steps'] ?? [] as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }
            foreach ($step['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'text' && is_string($content['text'] ?? null)) {
                    return trim($content['text']);
                }
            }
        }

        return '';
    }

    private function validText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim(strip_tags($value));

        return $value !== '' && mb_strlen($value) <= 64 ? $value : null;
    }

    private function normalizeStyle(?string $style): ?string
    {
        if ($style === null) {
            return null;
        }
        $normalized = $this->normalizeText($style);

        return match ($normalized) {
            'minimal', 'minimalist', 'minimalistic', 'minimaliste' => 'minimaliste',
            'modern', 'moderne', 'contemporary', 'contemporain', 'contemporaine' => 'moderne',
            'abstract', 'abstrait', 'abstraite' => 'abstrait',
            'geometric', 'geometrique' => 'geometrique',
            'classic', 'classical', 'classique' => 'classique',
            'expressionist', 'expressionniste' => 'expressionniste',
            default => $style,
        };
    }

    private function normalizeMood(?string $mood): ?string
    {
        if ($mood === null) {
            return null;
        }
        $normalized = $this->normalizeText($mood);

        return match ($normalized) {
            'calm', 'serene', 'serenity', 'paisible', 'relaxing', 'calme', 'serenite' => 'calme',
            'energetic', 'dynamic', 'vibrant', 'energique', 'dynamique' => 'energique',
            'happy', 'joyful', 'joyeux', 'joyeuse' => 'joyeux',
            'melancholic', 'melancholy', 'melancolique' => 'melancolique',
            'warm', 'chaleureux', 'chaleureuse' => 'chaleureux',
            'soothing', 'peaceful', 'apaisant' => 'apaisant',
            'bold', 'intense', 'audacieux' => 'audacieux',
            default => $mood,
        };
    }

    private function validPrice(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            return null;
        }
        $price = (float) $value;

        return is_finite($price) && $price >= 0 && $price <= 100000000 ? $price : null;
    }

    /**
     * @return list<string>
     */
    private function validStringList(mixed $value, int $limit): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach (array_slice($value, 0, $limit) as $item) {
            $valid = $this->validText($item);
            if ($valid !== null) {
                $items[] = $valid;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<string, list<string>> $options
     */
    private function firstMention(string $normalized, array $options): ?string
    {
        foreach ($options as $label => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($this->normalizeText($alias), '/').'(?![\p{L}\p{N}])/u', $normalized) === 1) {
                    return $label;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function extractBudget(string $normalized): array
    {
        $amount = '(\d{1,3}(?:[ .]\d{3})*(?:[,.]\d{1,2})?|\d+(?:[,.]\d{1,2})?)';
        $maxPrice = null;
        $minPrice = null;
        if (preg_match('/(?:maximum|max(?:imum)?|moins\s+de|under|below|up\s+to|budget(?:\s+maximum)?|<=|<)\s*'.$amount.'/u', $normalized, $matches) === 1) {
            $maxPrice = $this->parseAmount($matches[1]);
        } elseif (preg_match('/'.$amount.'\s*(?:maximum|max|au\s+maximum|or\s+less|or\s+below|and\s+under)/u', $normalized, $matches) === 1) {
            $maxPrice = $this->parseAmount($matches[1]);
        }
        if (preg_match('/(?:minimum|min(?:imum)?|plus\s+de|more\s+than|at\s+least|>=|>)\s*'.$amount.'/u', $normalized, $matches) === 1) {
            $minPrice = $this->parseAmount($matches[1]);
        }
        if ($maxPrice !== null && $minPrice !== null && $minPrice > $maxPrice) {
            return [null, null];
        }

        return [$minPrice, $maxPrice];
    }

    private function parseAmount(string $amount): ?float
    {
        $amount = str_replace(' ', '', $amount);
        if (str_contains($amount, ',') && str_contains($amount, '.')) {
            $amount = str_replace('.', '', $amount);
            $amount = str_replace(',', '.', $amount);
        } else {
            $amount = str_replace(',', '.', $amount);
        }
        $price = (float) $amount;

        return $price <= 100000000 ? $price : null;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($ascii) ? $ascii : $value;
    }
}
