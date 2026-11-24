<?php

namespace App\Tests\AI;

use App\Repository\ArtworkColorRepository;
use App\Service\AI\ColorAnalysisException;
use App\Service\AI\ColorAnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ColorAnalysisServiceTest extends TestCase
{
    public function testDominantPixelClustersAreClassifiedAndPercentagesTotalOneHundred(): void
    {
        $service = new ColorAnalysisService(
            sys_get_temp_dir(),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ArtworkColorRepository::class),
        );

        $pixels = array_merge(
            array_fill(0, 42, [36, 86, 166]),
            array_fill(0, 28, [17, 17, 17]),
            array_fill(0, 18, [245, 245, 245]),
            array_fill(0, 12, [229, 193, 0]),
        );
        $result = $service->analyzePixels($pixels);

        self::assertSame(['bleu', 'noir', 'blanc', 'jaune'], array_column($result, 'slug'));
        self::assertSame(100.0, array_sum(array_column($result, 'percentage')));
        self::assertSame([42.0, 28.0, 18.0, 12.0], array_column($result, 'percentage'));
        self::assertSame('#2456A6', $result[0]['hex']);
    }

    public function testEmptyPixelSetIsRejectedExplicitly(): void
    {
        $service = new ColorAnalysisService(
            sys_get_temp_dir(),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ArtworkColorRepository::class),
        );

        $this->expectException(ColorAnalysisException::class);
        $service->analyzePixels([]);
    }
}
