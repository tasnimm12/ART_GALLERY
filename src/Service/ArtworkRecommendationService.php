<?php

namespace App\Service;

use App\Entity\Artwork;
use App\Entity\ArtworkColorAnalysis;
use App\Repository\ArtworkRepository;

final class ArtworkRecommendationService
{
    private const WEIGHTS = [
        'colors' => 30,
        'style' => 20,
        'mood' => 20,
        'budget' => 15,
        'category' => 10,
        'keywords' => 5,
    ];

    public function __construct(private readonly ArtworkRepository $artworkRepository)
    {
    }

    /**
     * @param array{style: ?string, colors: list<string>, mood: ?string, category: ?string, maxPrice: ?float, minPrice: ?float, room: ?string, keywords: list<string>} $criteria
     *
     * @return list<array{artwork: Artwork, score: int, reasons: list<string>, colors: list<array{name: string, slug: string, hex: string, percentage: float}>}>
     */
    public function recommend(array $criteria, int $limit = 6): array
    {
        $candidates = $this->artworkRepository->findAdvisorCandidates($criteria);
        $recommendations = [];

        foreach ($candidates as $artwork) {
            [$score, $reasons] = $this->score($artwork, $criteria);
            $recommendations[] = [
                'artwork' => $artwork,
                'score' => $score,
                'reasons' => $reasons,
                'colors' => $this->getPalette($artwork),
            ];
        }

        usort($recommendations, static function (array $left, array $right): int {
            return ($right['score'] <=> $left['score'])
                ?: ($left['artwork']->getId() <=> $right['artwork']->getId());
        });

        return array_slice($recommendations, 0, max(1, min($limit, 6)));
    }

    /**
     * @param array{style: ?string, colors: list<string>, mood: ?string, category: ?string, maxPrice: ?float, minPrice: ?float, room: ?string, keywords: list<string>} $criteria
     *
     * @return array{0: int, 1: list<string>}
     */
    public function score(Artwork $artwork, array $criteria): array
    {
        $description = $this->normalize(implode(' ', array_filter([
            $artwork->getTitle(),
            $artwork->getDescription() ?? '',
            $artwork->getTechnique() ?? '',
            $artwork->getCategory()?->getName() ?? '',
        ])));
        $scores = [];
        $reasons = [];

        if ($criteria['colors'] !== []) {
            $palette = [];
            foreach ($artwork->getColorAnalyses() as $analysis) {
                if ($analysis instanceof ArtworkColorAnalysis && $analysis->getColor() !== null) {
                    $palette[$analysis->getColor()->getSlug()] = (float) $analysis->getPercentage();
                }
            }
            $perColorScores = [];
            foreach ($criteria['colors'] as $slug) {
                $percentage = $palette[$slug] ?? 0.0;
                $perColorScores[] = $this->dominanceScore($percentage);
                if ($percentage > 0) {
                    $color = $this->findColorName($artwork, $slug);
                    $reasons[] = sprintf('Présence de %s détectée à %.0f%%.', $color ?? $slug, $percentage);
                }
            }
            $scores['colors'] = array_sum($perColorScores) / count($perColorScores);
        }

        if ($criteria['style'] !== null) {
            $scores['style'] = $this->matchesConcept($description, $criteria['style'], [
                'minimaliste' => ['minimaliste', 'minimalist', 'minimal'],
                'moderne' => ['moderne', 'modern', 'contemporain', 'contemporary'],
                'abstrait' => ['abstrait', 'abstract'],
                'geometrique' => ['geometrique', 'geometric'],
                'classique' => ['classique', 'classic', 'classical'],
                'expressionniste' => ['expressionniste', 'expressionist'],
            ]) ? 100.0 : 0.0;
            if ($scores['style'] > 0) {
                $reasons[] = sprintf('Le style « %s » est mentionné dans les informations de l’œuvre.', $criteria['style']);
            }
        }

        if ($criteria['mood'] !== null) {
            $scores['mood'] = $this->matchesConcept($description, $criteria['mood'], [
                'calme' => ['calme', 'calm', 'serene', 'serenity', 'paisible', 'relaxing'],
                'energique' => ['energique', 'energetic', 'dynamique', 'dynamic', 'vibrant'],
                'joyeux' => ['joyeux', 'joyeuse', 'happy', 'joyful'],
                'melancolique' => ['melancolique', 'melancholic', 'melancholy'],
                'chaleureux' => ['chaleureux', 'chaleureuse', 'warm'],
                'apaisant' => ['apaisant', 'soothing', 'peaceful'],
                'audacieux' => ['audacieux', 'bold', 'intense'],
            ]) ? 100.0 : 0.0;
            if ($scores['mood'] > 0) {
                $reasons[] = sprintf('L’ambiance « %s » est mentionnée dans les informations de l’œuvre.', $criteria['mood']);
            }
        }

        if ($criteria['minPrice'] !== null || $criteria['maxPrice'] !== null) {
            $price = $artwork->getPrice() === null ? null : (float) $artwork->getPrice();
            $scores['budget'] = $price === null ? 0.0 : $this->budgetScore($price, $criteria['minPrice'], $criteria['maxPrice']);
            if ($price !== null) {
                $reasons[] = sprintf('Prix de %s %s, compatible avec votre budget.', number_format($price, 2, ',', ' '), $artwork->getCurrency());
            }
        }

        if ($criteria['category'] !== null) {
            $matches = $artwork->getCategory() !== null
                && $this->normalize($artwork->getCategory()->getName()) === $this->normalize($criteria['category']);
            $scores['category'] = $matches ? 100.0 : 0.0;
            if ($matches) {
                $reasons[] = sprintf('Catégorie « %s ».', $artwork->getCategory()->getName());
            }
        }

        if ($criteria['keywords'] !== []) {
            $matchedKeywords = 0;
            foreach ($criteria['keywords'] as $keyword) {
                if ($this->containsText($description, $keyword)) {
                    ++$matchedKeywords;
                }
            }
            $scores['keywords'] = $matchedKeywords * 100 / count($criteria['keywords']);
            if ($matchedKeywords > 0) {
                $reasons[] = sprintf('%d mot(s)-clé(s) de votre demande apparaissent dans les informations de l’œuvre.', $matchedKeywords);
            }
        }

        if ($scores === []) {
            return [100, ['Sélection parmi les œuvres actuellement disponibles.']];
        }

        $weightedTotal = 0.0;
        $availableWeight = 0;
        foreach ($scores as $criterion => $score) {
            $weight = self::WEIGHTS[$criterion];
            $weightedTotal += $score * $weight;
            $availableWeight += $weight;
        }

        if ($reasons === []) {
            $reasons[] = 'Œuvre disponible dans la galerie et compatible avec les critères renseignés.';
        }

        return [(int) round($weightedTotal / $availableWeight), $reasons];
    }

    private function dominanceScore(float $percentage): float
    {
        $points = [[0.0, 0.0], [5.0, 20.0], [20.0, 55.0], [40.0, 82.0], [60.0, 100.0], [100.0, 100.0]];
        $percentage = max(0.0, min(100.0, $percentage));
        for ($index = 1; $index < count($points); ++$index) {
            [$x2, $y2] = $points[$index];
            [$x1, $y1] = $points[$index - 1];
            if ($percentage <= $x2) {
                return $y1 + (($percentage - $x1) / ($x2 - $x1)) * ($y2 - $y1);
            }
        }

        return 100.0;
    }

    private function budgetScore(float $price, ?float $minPrice, ?float $maxPrice): float
    {
        if ($minPrice !== null && $maxPrice !== null && $maxPrice > $minPrice) {
            $target = ($minPrice + $maxPrice) / 2;
            $halfRange = ($maxPrice - $minPrice) / 2;

            return max(70.0, 100.0 - (abs($price - $target) / $halfRange) * 30.0);
        }
        if ($maxPrice !== null && $maxPrice > 0) {
            return 50.0 + (50.0 * (1 - min($price, $maxPrice) / $maxPrice));
        }

        return 100.0;
    }

    /**
     * @return list<array{name: string, slug: string, hex: string, percentage: float}>
     */
    private function getPalette(Artwork $artwork): array
    {
        $palette = [];
        foreach ($artwork->getColorAnalyses() as $analysis) {
            if ($analysis instanceof ArtworkColorAnalysis && $analysis->getColor() !== null) {
                $palette[] = [
                    'name' => $analysis->getColor()->getName(),
                    'slug' => $analysis->getColor()->getSlug(),
                    'hex' => $analysis->getHexCode(),
                    'percentage' => (float) $analysis->getPercentage(),
                ];
            }
        }

        return $palette;
    }

    private function findColorName(Artwork $artwork, string $slug): ?string
    {
        foreach ($artwork->getColorAnalyses() as $analysis) {
            if ($analysis instanceof ArtworkColorAnalysis && $analysis->getColor()?->getSlug() === $slug) {
                return $analysis->getColor()->getName();
            }
        }

        return null;
    }

    private function containsText(string $haystack, string $needle): bool
    {
        $needle = $this->normalize($needle);

        return $needle !== '' && str_contains($haystack, $needle);
    }

    /**
     * @param array<string, list<string>> $aliases
     */
    private function matchesConcept(string $haystack, string $criterion, array $aliases): bool
    {
        $criterion = $this->normalize($criterion);
        foreach ($aliases as $canonical => $variants) {
            if ($criterion !== $canonical && !in_array($criterion, $variants, true)) {
                continue;
            }
            foreach ($variants as $variant) {
                if ($this->containsText($haystack, $variant)) {
                    return true;
                }
            }

            return $this->containsText($haystack, $canonical);
        }

        return $this->containsText($haystack, $criterion);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($ascii) ? $ascii : $value;
    }
}
