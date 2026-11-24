<?php

namespace App\Tests\AI;

use App\Entity\Artwork;
use App\Entity\ArtworkColorAnalysis;
use App\Repository\ArtworkRepository;
use App\Service\AI\ColorAnalysisService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ColorAnalysisPersistenceTest extends WebTestCase
{
    public function testUploadedImageCanBeAnalyzedToFillArtworkColorChoices(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('The PHP GD extension is required to test image decoding.');
        }

        static::createClient();
        $filename = 'color-analysis-upload-test-'.bin2hex(random_bytes(6)).'.png';
        $imagePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;
        $this->createColorTestImage($imagePath);
        $uploadedImage = new UploadedFile($imagePath, $filename, 'image/png', null, true);

        try {
            $result = static::getContainer()
                ->get(ColorAnalysisService::class)
                ->analyzeUploadedImage($uploadedImage);

            self::assertSame(['bleu', 'noir', 'blanc', 'jaune'], array_column($result, 'slug'));
            self::assertSame(['Bleu', 'Noir', 'Blanc', 'Jaune'], array_column($result, 'name'));
            self::assertEqualsWithDelta(100.0, array_sum(array_column($result, 'percentage')), 0.01);
            self::assertNotContains(null, array_column($result, 'id'));
        } finally {
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }
    }

    public function testLocalImagePaletteIsPersistedAndReanalysisKeepsOneRowPerCategory(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('The PHP GD extension is required to test image decoding.');
        }

        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $templateArtwork = $container->get(ArtworkRepository::class)->findOneBy([]);
        self::assertInstanceOf(Artwork::class, $templateArtwork);

        $filename = 'color-analysis-test-'.bin2hex(random_bytes(6)).'.png';
        $imagePath = dirname(__DIR__, 2).'/public/uploads/artworks/'.$filename;
        $this->createColorTestImage($imagePath);
        $artwork = (new Artwork())
            ->setTitle('Color analysis test artwork')
            ->setArtist($templateArtwork->getArtist())
            ->setCategory($templateArtwork->getCategory())
            ->setPhoto($filename);
        $entityManager->persist($artwork);
        $entityManager->flush();

        try {
            self::assertContains(
                $artwork->getId(),
                $container->get(ArtworkRepository::class)->findIdsWithLocalImageCandidates(),
            );
            $service = $container->get(ColorAnalysisService::class);
            $firstResult = $service->analyzeAndPersist($artwork);
            self::assertNotEmpty($firstResult['colors']);
            self::assertEqualsWithDelta(100.0, array_sum(array_column($firstResult['colors'], 'percentage')), 0.01);
            self::assertTrue($service->isUpToDate($artwork));

            $persistedRows = $entityManager->getRepository(ArtworkColorAnalysis::class)->findBy(['artwork' => $artwork]);
            self::assertNotEmpty($persistedRows);
            $firstRowIds = array_map(static fn (ArtworkColorAnalysis $row): ?int => $row->getId(), $persistedRows);
            $analyzedColorIds = array_map(
                static fn (ArtworkColorAnalysis $row): ?int => $row->getColor()?->getId(),
                $persistedRows,
            );
            $assignedColorIds = array_map(
                static fn ($color): ?int => $color->getId(),
                $artwork->getColors()->toArray(),
            );
            self::assertEqualsCanonicalizing($analyzedColorIds, $assignedColorIds);
            $client->request('GET', '/catalogue/'.$artwork->getId());
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.artwork-color-palette');
            self::assertSelectorTextContains('.artwork-color-palette', $persistedRows[0]->getColor()->getName());

            $service->analyzeAndPersist($artwork);
            $recalculatedRows = $entityManager->getRepository(ArtworkColorAnalysis::class)->findBy(['artwork' => $artwork]);
            self::assertCount(count($persistedRows), $recalculatedRows);
            self::assertSame($firstRowIds, array_map(static fn (ArtworkColorAnalysis $row): ?int => $row->getId(), $recalculatedRows));
            self::assertEqualsCanonicalizing(
                $analyzedColorIds,
                array_map(static fn ($color): ?int => $color->getId(), $artwork->getColors()->toArray()),
            );
        } finally {
            $container->get(Connection::class)->delete('artwork_color_analysis', ['artwork_id' => $artwork->getId()]);
            $container->get(Connection::class)->delete('artwork', ['id' => $artwork->getId()]);
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
            $entityManager->clear();
        }
    }

    private function createColorTestImage(string $path): void
    {
        $image = imagecreatetruecolor(100, 100);
        $colors = [
            imagecolorallocate($image, 36, 86, 166),
            imagecolorallocate($image, 17, 17, 17),
            imagecolorallocate($image, 245, 245, 245),
            imagecolorallocate($image, 229, 193, 0),
        ];
        imagefilledrectangle($image, 0, 0, 39, 99, $colors[0]);
        imagefilledrectangle($image, 40, 0, 69, 99, $colors[1]);
        imagefilledrectangle($image, 70, 0, 89, 99, $colors[2]);
        imagefilledrectangle($image, 90, 0, 99, 99, $colors[3]);
        imagepng($image, $path);
        imagedestroy($image);
    }
}
