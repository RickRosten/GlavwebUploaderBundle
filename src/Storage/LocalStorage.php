<?php

namespace Glavweb\UploaderBundle\Storage;

use Glavweb\UploaderBundle\File\FileMetadata;
use Glavweb\UploaderBundle\File\FileInterface;
use Glavweb\UploaderBundle\File\FilesystemFile;
use Glavweb\UploaderBundle\Util\FileUtils;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Class LocalStorage.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
abstract class LocalStorage implements StorageInterface
{
    private Filesystem $filesystem;

    private string $tempDirectory;

    public function __construct(Filesystem $filesystem, string $tempDirectoryPath)
    {
        $this->filesystem = $filesystem;
        $this->tempDirectory = $tempDirectoryPath;
    }

    public function addFileChunk(File $file, string $fileId, int $chunkIndex): void
    {
        $chunksDirectoryPath = $this->getChunksDirectoryPath($fileId);
        $targetPath = $chunksDirectoryPath.\DIRECTORY_SEPARATOR.$chunkIndex;

        $this->filesystem->mkdir($chunksDirectoryPath);
        $this->filesystem->rename($file->getRealPath(), $targetPath);
    }

    public function hasAllFileChunks(string $fileId, int $chunkTotal): bool
    {
        $finder = new Finder();

        $chunksDirectoryPath = $this->getChunksDirectoryPath($fileId);

        $finder->in($chunksDirectoryPath)->files();

        if ($finder->count() === $chunkTotal) {
            foreach ($finder as $chunk) {
                if (!is_readable($chunk->getRealPath())) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    public function concatFileChunks(File $file, FileMetadata $metadata, string $fileId): FileInterface
    {
        $finder = new Finder();

        $chunksDirectoryPath = $this->getChunksDirectoryPath($fileId);
        $fileDirectoryPath = $this->getConcatenatedFileDirectoryPath();
        $filePath = FileUtils::path($fileDirectoryPath, $fileId);

        $finder->in($chunksDirectoryPath)->files()->sortByName(true);

        $this->filesystem->mkdir($fileDirectoryPath);

        try {
            $target = fopen($filePath, 'ab');

            foreach ($finder as $chunk) {
                try {
                    $source = fopen($chunk->getRealPath(), 'rb');
                    stream_copy_to_stream($source, $target);

                    $this->filesystem->remove($chunk->getRealPath());
                } finally {
                    if (isset($source) && \is_resource($source)) {
                        fclose($source);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->filesystem->remove($filePath);

            throw $e;
        } finally {
            if (isset($target) && \is_resource($target)) {
                fclose($target);
            }

            $this->filesystem->remove($chunksDirectoryPath);
        }

        return new FilesystemFile(new File($filePath), $metadata->originalName);
    }

    public function cleanup(): void
    {
        $oldFilesFinder = new Finder();
        $oldFilesFinder->in($this->getChunkedUploadDirectoryPath())
            ->files()
            ->date('before 1 hour ago');

        $this->filesystem->remove(iterator_to_array($oldFilesFinder));

        $emptyDirFinder = new Finder();
        $emptyDirFinder->in($this->getChunkedUploadDirectoryPath())
            ->directories()
            ->filter(static function (\SplFileInfo $dirInfo): bool {
                $dirFinder = new Finder();
                $dirFinder->in($dirInfo->getRealPath())->files();

                return !$dirFinder->count();
            });

        $this->filesystem->remove(iterator_to_array($emptyDirFinder));
    }

    private function getConcatenatedFileDirectoryPath(): string
    {
        return FileUtils::path($this->getChunkedUploadDirectoryPath(), 'files');
    }

    private function getChunksDirectoryPath(string $fileId): string
    {
        return FileUtils::path($this->getChunkedUploadDirectoryPath(), 'chunks', $fileId);
    }

    private function getChunkedUploadDirectoryPath(): string
    {
        return FileUtils::path($this->tempDirectory, 'chunked-upload');
    }
}
