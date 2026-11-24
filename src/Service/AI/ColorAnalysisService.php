<?php

namespace App\Service\AI;

use App\Entity\Artwork;
use App\Entity\ArtworkColor;
use App\Entity\ArtworkColorAnalysis;
use App\Entity\ArtworkImage;
use App\Repository\ArtworkColorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ColorAnalysisService
{
    private const SAMPLE_SIZE = 64;
    private const CLUSTER_COUNT = 5;
    private const MAX_IMAGE_PIXELS = 16000000;
    public function __construct(
        private readonly string $projectDir,
        private readonly EntityManagerInterface $entityManager,
        private readonly ArtworkColorRepository $colorRepository,
    ) {
    }

    /**
     * @return array{colors: list<array{slug: string, name: string, hex: string, percentage: float}>}
     */
    public function analyzeAndPersist(Artwork $artwork): array
    {
        [$imagePath, $sourceHash] = $this->resolveImage($artwork);
        $colors = $this->analyzeImage($imagePath);
        $colorBySlug = $this->getCanonicalColors($colors);

        foreach ($colors as &$color) {
            if (!isset($colorBySlug[$color['slug']])) {
                throw new ColorAnalysisException(sprintf('La couleur canonique "%s" est absente du référentiel.', $color['slug']));
            }
            $color['name'] = $colorBySlug[$color['slug']]->getName();
        }
        unset($color);

        $analyzedAt = new \DateTimeImmutable();
        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($artwork, $colors, $colorBySlug, $sourceHash, $analyzedAt): void {
            foreach ($artwork->getColors()->toArray() as $assignedColor) {
                $artwork->removeColor($assignedColor);
            }

            foreach ($colors as $colorData) {
                $artwork->addColor($colorBySlug[$colorData['slug']]);
            }

            $existingBySlug = [];
            foreach ($artwork->getColorAnalyses() as $analysis) {
                $slug = $analysis->getColor()?->getSlug();
                if ($slug !== null) {
                    $existingBySlug[$slug] = $analysis;
                }
            }

            foreach ($colors as $colorData) {
                $slug = $colorData['slug'];
                $analysis = $existingBySlug[$slug] ?? new ArtworkColorAnalysis();
                $analysis
                    ->setArtwork($artwork)
                    ->setColor($colorBySlug[$slug])
                    ->setHexCode($colorData['hex'])
                    ->setPercentage($colorData['percentage'])
                    ->setSourceHash($sourceHash)
                    ->setAnalyzedAt($analyzedAt);
                $artwork->addColorAnalysis($analysis);
                $entityManager->persist($analysis);
                unset($existingBySlug[$slug]);
            }

            foreach ($existingBySlug as $obsoleteAnalysis) {
                $artwork->removeColorAnalysis($obsoleteAnalysis);
                $entityManager->remove($obsoleteAnalysis);
            }
        });

        return ['colors' => $colors];
    }

    /**
     * @return list<array{id: int, slug: string, name: string, hex: string, percentage: float}>
     */
    public function analyzeUploadedImage(UploadedFile $image): array
    {
        if (!$image->isValid()) {
            throw new ColorAnalysisException('Le téléversement de l’image a échoué.');
        }

        $size = $image->getSize();
        if ($size === false || $size > 5 * 1024 * 1024) {
            throw new ColorAnalysisException('L’image doit peser au maximum 5 Mo.');
        }

        if (!in_array($image->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new ColorAnalysisException('L’image doit être au format JPG, PNG ou WebP.');
        }

        $path = $image->getPathname();
        if (!is_file($path)) {
            throw new ColorAnalysisException('Le fichier image téléversé est introuvable.');
        }

        $colors = $this->analyzeImage($path);
        $colorBySlug = $this->getCanonicalColors($colors);

        return array_map(static function (array $color) use ($colorBySlug): array {
            $canonicalColor = $colorBySlug[$color['slug']];
            $id = $canonicalColor->getId();
            if ($id === null) {
                throw new ColorAnalysisException(sprintf('La couleur canonique "%s" n’est pas enregistrée.', $color['slug']));
            }

            return [
                'id' => $id,
                'slug' => $color['slug'],
                'name' => $canonicalColor->getName(),
                'hex' => $color['hex'],
                'percentage' => $color['percentage'],
            ];
        }, $colors);
    }

    /**
     * @param list<array{slug: string, hex: string, percentage: float}> $colors
     *
     * @return array<string, ArtworkColor>
     */
    private function getCanonicalColors(array $colors): array
    {
        $canonicalColors = $this->colorRepository->findBy(['slug' => array_column($colors, 'slug')]);
        $colorBySlug = [];
        foreach ($canonicalColors as $canonicalColor) {
            $colorBySlug[$canonicalColor->getSlug()] = $canonicalColor;
        }

        foreach ($colors as $color) {
            if (!isset($colorBySlug[$color['slug']])) {
                throw new ColorAnalysisException(sprintf('La couleur canonique "%s" est absente du référentiel.', $color['slug']));
            }
        }

        return $colorBySlug;
    }

    public function isUpToDate(Artwork $artwork): bool
    {
        [$imagePath, $sourceHash] = $this->resolveImage($artwork);
        if ($artwork->getColorAnalyses()->isEmpty()) {
            return false;
        }

        foreach ($artwork->getColorAnalyses() as $analysis) {
            if (!hash_equals($sourceHash, $analysis->getSourceHash())) {
                return false;
            }
        }

        return is_file($imagePath);
    }

    /**
     * @param list<array{0: int, 1: int, 2: int}> $pixels
     *
     * @return list<array{slug: string, hex: string, percentage: float}>
     */
    public function analyzePixels(array $pixels): array
    {
        if ($pixels === []) {
            throw new ColorAnalysisException('Aucun pixel exploitable n’a été trouvé dans l’image.');
        }

        foreach ($pixels as $pixel) {
            if (count($pixel) !== 3 || min($pixel) < 0 || max($pixel) > 255) {
                throw new ColorAnalysisException('L’échantillon contient une valeur RGB invalide.');
            }
        }

        $centroids = $this->clusterPixels($pixels);
        $groups = [];
        foreach ($centroids as $centroid) {
            $slug = $this->classifyColor($centroid['rgb']);
            if (!isset($groups[$slug])) {
                $groups[$slug] = ['count' => 0, 'rgb' => [0.0, 0.0, 0.0]];
            }
            $groups[$slug]['count'] += $centroid['count'];
            foreach ([0, 1, 2] as $channel) {
                $groups[$slug]['rgb'][$channel] += $centroid['rgb'][$channel] * $centroid['count'];
            }
        }

        $totalPixels = count($pixels);
        $colors = [];
        foreach ($groups as $slug => $group) {
            $rgb = array_map(
                static fn (float $channel): int => (int) round($channel / $group['count']),
                $group['rgb'],
            );
            $colors[] = [
                'slug' => $slug,
                'hex' => sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]),
                'percentage' => round($group['count'] * 100 / $totalPixels, 2),
                'count' => $group['count'],
            ];
        }

        usort($colors, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $roundedTotal = array_sum(array_column($colors, 'percentage'));
        if ($colors !== [] && $roundedTotal !== 100.0) {
            $colors[0]['percentage'] = round($colors[0]['percentage'] + 100 - $roundedTotal, 2);
        }

        foreach ($colors as &$color) {
            unset($color['count']);
        }
        unset($color);

        return $colors;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveImage(Artwork $artwork): array
    {
        $imagePath = null;
        foreach ($artwork->getImages() as $image) {
            if ($image->isPrimary()) {
                $imagePath = $image->getFilePath();
                break;
            }
        }

        if ($imagePath === null) {
            $firstImage = $artwork->getImages()->first();
            if ($firstImage instanceof ArtworkImage) {
                $imagePath = $firstImage->getFilePath();
            }
        }
        $imagePath ??= $artwork->getPhoto();

        if ($imagePath === null || trim($imagePath) === '') {
            throw new ColorAnalysisException('Cette œuvre ne possède pas d’image à analyser.');
        }

        $normalizedPath = str_replace('\\', '/', trim($imagePath));
        if (str_contains($normalizedPath, '://') || str_contains($normalizedPath, "\0")) {
            throw new ColorAnalysisException('Seules les images locales de la galerie peuvent être analysées.');
        }

        $filename = basename($normalizedPath);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new ColorAnalysisException('Le chemin de l’image est invalide.');
        }
        $publicArtworkDirectory = realpath($this->projectDir.'/public/uploads/artworks');
        $absolutePath = realpath($this->projectDir.'/public/uploads/artworks/'.$filename);
        if ($publicArtworkDirectory === false || $absolutePath === false || !is_file($absolutePath)) {
            throw new ColorAnalysisException('Le fichier image de cette œuvre est introuvable.');
        }
        $allowedPrefix = rtrim($publicArtworkDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (!str_starts_with($absolutePath, $allowedPrefix)) {
            throw new ColorAnalysisException('Le chemin de l’image sort du dossier sécurisé de la galerie.');
        }

        $sourceHash = hash_file('sha256', $absolutePath);
        if (!is_string($sourceHash)) {
            throw new ColorAnalysisException('Impossible de calculer l’empreinte du fichier image.');
        }

        return [$absolutePath, $sourceHash];
    }

    /**
     * @return list<array{slug: string, hex: string, percentage: float}>
     */
    private function analyzeImage(string $path): array
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
            throw new ColorAnalysisException('L’extension PHP GD doit être activée pour analyser les images.');
        }

        $imageInfo = @getimagesize($path);
        if ($imageInfo === false || $imageInfo[0] < 1 || $imageInfo[1] < 1) {
            throw new ColorAnalysisException('Le fichier sélectionné n’est pas une image lisible.');
        }
        if ($imageInfo[0] * $imageInfo[1] > self::MAX_IMAGE_PIXELS) {
            throw new ColorAnalysisException('L’image dépasse la taille maximale prise en charge pour l’analyse.');
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new ColorAnalysisException('Impossible de lire le fichier image.');
        }
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new ColorAnalysisException('GD ne peut pas décoder ce format d’image.');
        }

        $sample = imagecreatetruecolor(self::SAMPLE_SIZE, self::SAMPLE_SIZE);
        if ($sample === false) {
            imagedestroy($image);
            throw new ColorAnalysisException('Impossible de créer l’échantillon de pixels.');
        }
        imagealphablending($sample, true);
        $background = imagecolorallocate($sample, 255, 255, 255);
        imagefill($sample, 0, 0, $background);
        if (!imagecopyresampled($sample, $image, 0, 0, 0, 0, self::SAMPLE_SIZE, self::SAMPLE_SIZE, $imageInfo[0], $imageInfo[1])) {
            imagedestroy($sample);
            imagedestroy($image);
            throw new ColorAnalysisException('Impossible de réduire l’image pour l’analyse.');
        }

        $pixels = [];
        for ($y = 0; $y < self::SAMPLE_SIZE; ++$y) {
            for ($x = 0; $x < self::SAMPLE_SIZE; ++$x) {
                $rgb = imagecolorat($sample, $x, $y);
                $pixels[] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
            }
        }
        imagedestroy($sample);
        imagedestroy($image);

        return $this->analyzePixels($pixels);
    }

    /**
     * @param list<array{0: int, 1: int, 2: int}> $pixels
     *
     * @return list<array{rgb: array{0: float, 1: float, 2: float}, count: int}>
     */
    private function clusterPixels(array $pixels): array
    {
        $centroids = [array_map('floatval', $pixels[0])];
        $maxClusters = min(self::CLUSTER_COUNT, count($pixels));

        while (count($centroids) < $maxClusters) {
            $farthestPixel = null;
            $farthestDistance = -1.0;
            foreach ($pixels as $pixel) {
                $distance = min(array_map(
                    fn (array $centroid): float => $this->distance($pixel, $centroid),
                    $centroids,
                ));
                if ($distance > $farthestDistance) {
                    $farthestDistance = $distance;
                    $farthestPixel = $pixel;
                }
            }
            if ($farthestPixel === null || $farthestDistance === 0.0) {
                break;
            }
            $centroids[] = array_map('floatval', $farthestPixel);
        }

        $assignments = [];
        for ($iteration = 0; $iteration < 12; ++$iteration) {
            $sums = array_fill(0, count($centroids), [0.0, 0.0, 0.0, 0]);
            $nextAssignments = [];
            foreach ($pixels as $index => $pixel) {
                $nearest = 0;
                $nearestDistance = PHP_FLOAT_MAX;
                foreach ($centroids as $centroidIndex => $centroid) {
                    $distance = $this->distance($pixel, $centroid);
                    if ($distance < $nearestDistance) {
                        $nearest = $centroidIndex;
                        $nearestDistance = $distance;
                    }
                }
                $nextAssignments[$index] = $nearest;
                foreach ([0, 1, 2] as $channel) {
                    $sums[$nearest][$channel] += $pixel[$channel];
                }
                ++$sums[$nearest][3];
            }

            $newCentroids = [];
            foreach ($sums as $clusterIndex => $sum) {
                if ($sum[3] === 0) {
                    continue;
                }
                $newCentroids[] = [
                    $sum[0] / $sum[3],
                    $sum[1] / $sum[3],
                    $sum[2] / $sum[3],
                ];
            }
            if ($assignments === $nextAssignments && $newCentroids === $centroids) {
                $assignments = $nextAssignments;
                break;
            }
            $assignments = $nextAssignments;
            $centroids = $newCentroids;
        }

        $clusters = [];
        foreach ($assignments as $pixelIndex => $clusterIndex) {
            if (!isset($clusters[$clusterIndex])) {
                $clusters[$clusterIndex] = ['sums' => [0.0, 0.0, 0.0], 'count' => 0];
            }
            foreach ([0, 1, 2] as $channel) {
                $clusters[$clusterIndex]['sums'][$channel] += $pixels[$pixelIndex][$channel];
            }
            ++$clusters[$clusterIndex]['count'];
        }

        $result = [];
        foreach ($clusters as $cluster) {
            $result[] = [
                'rgb' => array_map(static fn (float $sum): float => $sum / $cluster['count'], $cluster['sums']),
                'count' => $cluster['count'],
            ];
        }

        return $result;
    }

    /**
     * @param array{0: int|float, 1: int|float, 2: int|float} $left
     * @param array{0: int|float, 1: int|float, 2: int|float} $right
     */
    private function distance(array $left, array $right): float
    {
        return (($left[0] - $right[0]) ** 2) + (($left[1] - $right[1]) ** 2) + (($left[2] - $right[2]) ** 2);
    }

    /**
     * @param array{0: float, 1: float, 2: float} $rgb
     */
    private function classifyColor(array $rgb): string
    {
        [$red, $green, $blue] = $rgb;
        $maximum = max($rgb);
        $minimum = min($rgb);

        if ($maximum <= 52) {
            return 'noir';
        }
        if ($minimum >= 224) {
            return 'blanc';
        }

        $delta = $maximum - $minimum;
        if ($delta < 1) {
            return $maximum < 128 ? 'noir' : 'blanc';
        }

        $saturation = $maximum === 0 ? 0 : $delta / $maximum;
        if ($saturation < 0.16) {
            return $maximum < 128 ? 'noir' : 'blanc';
        }

        if ($maximum === $red) {
            $hue = 60 * fmod(($green - $blue) / $delta, 6);
        } elseif ($maximum === $green) {
            $hue = 60 * (($blue - $red) / $delta + 2);
        } else {
            $hue = 60 * (($red - $green) / $delta + 4);
        }
        if ($hue < 0) {
            $hue += 360;
        }

        return match (true) {
            $hue < 15 || $hue >= 345 => 'rouge',
            $hue < 40 => 'orange',
            $hue < 70 => 'jaune',
            $hue < 165 => 'vert',
            $hue < 255 => 'bleu',
            default => 'violet',
        };
    }
}
