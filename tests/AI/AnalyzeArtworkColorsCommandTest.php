<?php

namespace App\Tests\AI;

use App\Command\AnalyzeArtworkColorsCommand;
use App\Message\AnalyzeArtworkColors;
use App\Repository\ArtworkRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class AnalyzeArtworkColorsCommandTest extends TestCase
{
    public function testQueuesEachArtworkIdReturnedByTheRepository(): void
    {
        $artworkRepository = $this->createMock(ArtworkRepository::class);
        $artworkRepository->expects(self::once())
            ->method('findIdsWithLocalImageCandidates')
            ->willReturn([12, 28]);

        $queuedIds = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::isInstanceOf(AnalyzeArtworkColors::class))
            ->willReturnCallback(static function (AnalyzeArtworkColors $message) use (&$queuedIds): Envelope {
                $queuedIds[] = $message->artworkId;

                return new Envelope($message);
            });

        $command = new AnalyzeArtworkColorsCommand($artworkRepository, $messageBus);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        self::assertSame([12, 28], $queuedIds);
        self::assertStringContainsString('2 analyse(s) mise(s) en file.', $tester->getDisplay());
    }
}
