<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class ArtworkDeletionService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ArtworkImageStorage $imageStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{deleted: int, failedImageCleanup: int}
     */
    public function deleteAll(): array
    {
        $this->connection->beginTransaction();

        try {
            $artworks = $this->connection->fetchAllAssociative('SELECT id, photo FROM artwork FOR UPDATE');
            if ($artworks === []) {
                $this->connection->commit();

                return ['deleted' => 0, 'failedImageCleanup' => 0];
            }

            $imagePaths = array_values(array_filter(array_column($artworks, 'photo'), static fn ($path): bool => is_string($path) && $path !== ''));
            array_push(
                $imagePaths,
                ...$this->connection->fetchFirstColumn(
                    'SELECT image.file_path FROM artwork_image image INNER JOIN artwork ON artwork.id = image.artwork_id WHERE image.file_path IS NOT NULL',
                ),
            );

            $this->connection->executeStatement('UPDATE order_item SET artwork_id = NULL WHERE artwork_id IS NOT NULL');
            $this->connection->executeStatement(
                "UPDATE auction SET closed_at = CASE WHEN status IN ('SCHEDULED', 'ACTIVE') THEN COALESCE(closed_at, CURRENT_TIMESTAMP) ELSE closed_at END, status = CASE WHEN status IN ('SCHEDULED', 'ACTIVE') THEN 'CANCELLED' ELSE status END, artwork_id = NULL WHERE artwork_id IS NOT NULL",
            );

            $this->connection->executeStatement('DELETE FROM direct_sale');
            $this->connection->executeStatement('DELETE FROM artwork_image');
            $this->connection->executeStatement('DELETE FROM artwork_color_analysis');
            $this->connection->executeStatement('DELETE FROM favorite');
            $this->connection->executeStatement('DELETE FROM artwork_color_link');
            $this->connection->executeStatement('DELETE FROM artwork');
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        $failedImageCleanup = 0;
        foreach (array_unique($imagePaths) as $imagePath) {
            try {
                $this->imageStorage->delete((string) $imagePath);
            } catch (\Throwable $exception) {
                ++$failedImageCleanup;
                $this->logger->error('Unable to remove artwork image after artwork deletion.', [
                    'imagePath' => $imagePath,
                    'exception' => $exception,
                ]);
            }
        }

        return ['deleted' => count($artworks), 'failedImageCleanup' => $failedImageCleanup];
    }
}
