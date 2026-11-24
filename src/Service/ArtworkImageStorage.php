<?php

namespace App\Service;

use App\Entity\Artwork;
use App\Entity\ArtworkImage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ArtworkImageStorage
{
    public function __construct(
        private readonly string $uploadDirectory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function store(Artwork $artwork, ArtworkImage $image): void
    {
        $file = $image->getImageFile();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Seuls les fichiers JPG, PNG et WebP peuvent être enregistrés.'),
        };

        if (!is_dir($this->uploadDirectory) && !mkdir($this->uploadDirectory, 0775, true) && !is_dir($this->uploadDirectory)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier d’images "%s".', $this->uploadDirectory));
        }

        $slug = strtolower((string) $this->slugger->slug($artwork->getTitle()));
        $slug = substr($slug, 0, 180);
        $filename = sprintf('%s-%s.%s', $slug !== '' ? $slug : 'oeuvre', uniqid(), $extension);
        $file->move($this->uploadDirectory, $filename);

        $image->setFilePath('uploads/artworks/'.$filename);
        $image->setImageFile(null);
    }

    public function delete(string $filePath): void
    {
        $normalized = str_replace('\\', '/', $filePath);
        if (str_contains($normalized, '://')) {
            return;
        }

        $filename = basename($normalized);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return;
        }

        $isManagedPath = in_array($normalized, [
            $filename,
            'uploads/artworks/'.$filename,
            '/uploads/artworks/'.$filename,
        ], true);

        if (!$isManagedPath) {
            return;
        }

        $path = $this->uploadDirectory.DIRECTORY_SEPARATOR.$filename;
        if (is_link($path)) {
            throw new \RuntimeException(sprintf('Le fichier image "%s" est un lien symbolique et n’a pas été supprimé.', $path));
        }

        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException(sprintf('Impossible de supprimer le fichier image "%s".', $path));
        }
    }
}
