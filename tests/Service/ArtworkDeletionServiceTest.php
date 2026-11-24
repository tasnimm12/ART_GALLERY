<?php

namespace App\Tests\Service;

use App\Service\ArtworkDeletionService;
use App\Service\ArtworkImageStorage;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ArtworkDeletionServiceTest extends TestCase
{
    public function testDeleteAllDetachesHistoryDeletesArtworkOwnedRowsAndCleansImagesAfterCommit(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->with('SELECT id, photo FROM artwork FOR UPDATE')
            ->willReturn([
                ['id' => 12, 'photo' => 'uploads/artworks/portrait.jpg'],
                ['id' => 19, 'photo' => null],
            ]);
        $connection->expects(self::once())->method('fetchFirstColumn')
            ->willReturn(['uploads/artworks/detail.webp']);
        $connection->expects(self::exactly(8))->method('executeStatement')
            ->willReturnCallback(static fn (): int => 1);
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $imageStorage = $this->getMockBuilder(ArtworkImageStorage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $imageStorage->expects(self::exactly(2))->method('delete');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $result = (new ArtworkDeletionService($connection, $imageStorage, $logger))->deleteAll();

        self::assertSame(['deleted' => 2, 'failedImageCleanup' => 0], $result);
    }

    public function testDeleteAllRollsBackAndDoesNotCleanImagesWhenADatabaseDeleteFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->willReturn([['id' => 12, 'photo' => 'uploads/artworks/portrait.jpg']]);
        $connection->expects(self::once())->method('fetchFirstColumn')
            ->willReturn(['uploads/artworks/detail.webp']);
        $executedStatements = [];
        $connection->expects(self::exactly(4))->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;
                if (str_contains($sql, 'DELETE FROM artwork_image')) {
                    throw new \RuntimeException('Database delete failed.');
                }

                return 1;
            });
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('rollBack');

        $imageStorage = $this->getMockBuilder(ArtworkImageStorage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $imageStorage->expects(self::never())->method('delete');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database delete failed.');

        try {
            (new ArtworkDeletionService($connection, $imageStorage, $this->createMock(LoggerInterface::class)))->deleteAll();
        } finally {
            self::assertCount(4, $executedStatements);
        }
    }
}
