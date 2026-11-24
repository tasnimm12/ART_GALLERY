<?php

namespace App\Tests\Service;

use App\Entity\Artwork;
use App\Entity\ArtworkImage;
use App\Service\ArtworkImageStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ArtworkImageStorageTest extends TestCase
{
    private string $uploadDirectory;
    private string $sourceFile;

    protected function setUp(): void
    {
        $this->uploadDirectory = sys_get_temp_dir().'/aag-artwork-images-'.bin2hex(random_bytes(8));
        $this->sourceFile = sys_get_temp_dir().'/aag-upload-'.bin2hex(random_bytes(8)).'.png';
        file_put_contents(
            $this->sourceFile,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jNxsAAAAASUVORK5CYII=', true),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->uploadDirectory, $this->sourceFile]);
    }

    public function testUploadedImageUsesSafeArtworkFilenameAndCanBeDeleted(): void
    {
        $storage = new ArtworkImageStorage($this->uploadDirectory, new AsciiSlugger());
        $artwork = (new Artwork())->setTitle('Œuvre de test');
        $image = (new ArtworkImage())->setImageFile(
            new UploadedFile($this->sourceFile, 'source.png', 'image/png', null, true),
        );

        $storage->store($artwork, $image);

        $filePath = $image->getFilePath();
        self::assertNotNull($filePath);
        self::assertMatchesRegularExpression('/^uploads\/artworks\/oeuvre-de-test-[a-f0-9]+\.(?:jpg|png|webp)$/', $filePath);
        self::assertFileExists($this->uploadDirectory.'/'.basename($filePath));
        self::assertNull($image->getImageFile());

        $storage->delete($filePath);

        self::assertFileDoesNotExist($this->uploadDirectory.'/'.basename($filePath));
    }

    public function testExternalImagePathsAreNotDeletedFromTheUploadDirectory(): void
    {
        $storage = new ArtworkImageStorage($this->uploadDirectory, new AsciiSlugger());
        $storage->delete('https://example.test/artwork.jpg');

        self::assertDirectoryDoesNotExist($this->uploadDirectory);
    }
}
