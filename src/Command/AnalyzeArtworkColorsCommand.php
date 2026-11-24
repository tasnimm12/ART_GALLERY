<?php

namespace App\Command;

use App\Message\AnalyzeArtworkColors;
use App\Repository\ArtworkRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:artworks:analyze-colors',
    description: 'Queue local color analysis for existing artworks with local images',
)]
final class AnalyzeArtworkColorsCommand extends Command
{
    public function __construct(
        private readonly ArtworkRepository $artworkRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $artworkIds = $this->artworkRepository->findIdsWithLocalImageCandidates();

        foreach ($artworkIds as $artworkId) {
            $this->messageBus->dispatch(new AnalyzeArtworkColors($artworkId));
        }

        $io->success(sprintf('%d analyse(s) mise(s) en file.', count($artworkIds)));

        return Command::SUCCESS;
    }
}
