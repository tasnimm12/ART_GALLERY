<?php

namespace App\EventListener;

use App\Entity\Artwork;
use App\Entity\ArtworkImage;
use App\Service\ArtworkImageStorage;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;

class ArtworkImageFileListener
{
    /** @var list<string> */
    private array $pendingDeletes = [];

    public function __construct(private readonly ArtworkImageStorage $storage)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof ArtworkImage && $entity->getFilePath() !== null) {
                $this->pendingDeletes[] = $entity->getFilePath();
            } elseif ($entity instanceof Artwork && $entity->getPhoto() !== null) {
                $this->pendingDeletes[] = $entity->getPhoto();
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            if ($entity instanceof ArtworkImage && isset($changeSet['filePath']) && $changeSet['filePath'][0] !== $changeSet['filePath'][1]) {
                $oldFilePath = $changeSet['filePath'][0];
                if (is_string($oldFilePath)) {
                    $this->pendingDeletes[] = $oldFilePath;
                }
            } elseif ($entity instanceof Artwork && isset($changeSet['photo']) && $changeSet['photo'][0] !== $changeSet['photo'][1]) {
                $oldFilePath = $changeSet['photo'][0];
                if (is_string($oldFilePath)) {
                    $this->pendingDeletes[] = $oldFilePath;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $paths = array_unique($this->pendingDeletes);
        $this->pendingDeletes = [];

        foreach ($paths as $path) {
            $this->storage->delete($path);
        }
    }
}
