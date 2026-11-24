<?php

namespace App\MessageHandler;

use App\Message\AnalyzeArtworkColors;
use App\Repository\ArtworkRepository;
use App\Service\AI\ColorAnalysisService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AnalyzeArtworkColorsHandler
{
    public function __construct(
        private readonly ArtworkRepository $artworkRepository,
        private readonly ColorAnalysisService $colorAnalysisService,
    ) {
    }

    public function __invoke(AnalyzeArtworkColors $message): void
    {
        $artwork = $this->artworkRepository->find($message->artworkId);
        if ($artwork === null) {
            return;
        }

        if (!$this->colorAnalysisService->isUpToDate($artwork)) {
            $this->colorAnalysisService->analyzeAndPersist($artwork);
        }
    }
}
