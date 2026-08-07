<?php

/*
 * This file is part of the Glavweb UploaderBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\UploaderBundle\Storage;

use Glavweb\UploaderBundle\Exception\FileCopyException;
use Glavweb\UploaderBundle\File\FileInterface;
use Glavweb\UploaderBundle\File\FileMetadata;
use Glavweb\UploaderBundle\File\FilesystemFile;
use Glavweb\UploaderBundle\File\StorageFile;
use Glavweb\UploaderBundle\Util\CropImage;
use Glavweb\UploaderBundle\Util\FileUtils;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Visibility;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class FlysystemStorage.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class FlysystemStorage extends LocalStorage
{
    private FilesystemOperator $filesystem;

    public function __construct(FilesystemOperator $filesystem, string $tempDirectoryPath)
    {
        parent::__construct(new Filesystem(), $tempDirectoryPath);

        $this->filesystem = $filesystem;
    }

    /**
     * @throws FilesystemException
     */
    public function upload(FileInterface $file, string $directory, ?string $name = null, bool $attachment = false): FileInterface
    {
        /* @var File $file */
        if (null === $name) {
            $name = $file->getBasename();
        }

        $path = \sprintf('%s/%s', $directory, $name);

        try {
            $source = fopen($file->getPathname(), 'r');

            $this->filesystem->writeStream($path, $source, [
                'visibility' => Visibility::PUBLIC,
                'mimetype' => $file->getMimeType(),
            ]);
        } finally {
            if (isset($source) && \is_resource($source)) {
                fclose($source);
            }
        }

        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();

        $storageFile = new StorageFile($this, $path, true);
        $storageFile->setSize($size);
        $storageFile->setOriginalName($originalName);
        $storageFile->setMimeType($file->getMimeType());
        $storageFile->setWidth($file->getWidth());
        $storageFile->setHeight($file->getHeight());

        $symfonyFilesystem = new Filesystem();
        $symfonyFilesystem->remove($file->getPathname());

        $storageFile->fetchMetadata();

        return $storageFile;
    }

    public function uploadTmpFileByLink(string $link): FileInterface
    {
        $file = FileUtils::getTempFileByUrl($link);

        return new FilesystemFile($file);
    }

    public function uploadFiles(array $files, string $directory): array
    {
        $return = [];
        foreach ($files as $file) {
            $return[] = $this->upload($file, $directory);
        }

        return $return;
    }

    /**
     * @throws FilesystemException
     */
    public function clearOldFiles($directory, $lifetime): void
    {
        /** @var StorageFile $file */
        foreach ($this->getFilesByDirectory($directory) as $file) {
            $nowTimestamp = new \DateTime()->getTimestamp();
            $fileTimestamp = $file->getLastModifiedAt()->getTimestamp();

            if (($nowTimestamp - $fileTimestamp) > $lifetime) {
                $this->removeFile($file);
            }
        }
    }

    /**
     * @throws FilesystemException
     */
    public function removeFile(FileInterface $file): void
    {
        $path = \sprintf('%s/%s', $file->getPath(), $file->getBasename());

        $this->filesystem->delete($path);
    }

    public function cropImage(FileInterface $file, array $cropData): string
    {
        try {
            $pathname = $file->getPathname();
            $sourceFile = $this->filesystem->readStream($pathname);
            $tempFile = tmpfile();

            stream_copy_to_stream($sourceFile, $tempFile);

            $tempFilePathname = stream_get_meta_data($tempFile)['uri'];

            $cropResult = CropImage::crop($tempFilePathname, $tempFilePathname, $cropData);

            $this->filesystem->writeStream($pathname, $tempFile);
            if ($cropResult) {
                return FileUtils::saveFileWithNewVersion($file);
            }

            return $pathname;
        } finally {
            if (isset($sourceFile) && \is_resource($sourceFile)) {
                fclose($sourceFile);
            }

            if (isset($tempFile) && \is_resource($tempFile)) {
                fclose($tempFile);
            }
        }
    }

    /**
     * @throws FilesystemException
     */
    public function moveFile(FileInterface $file, string $newPath): void
    {
        $this->filesystem->move($file->getPathname(), $newPath);
    }

    /**
     * @throws FilesystemException
     * @throws FileCopyException
     */
    public function copyFile(FileInterface $file, ?string $newPath = null): FileInterface
    {
        $path = $file->getPathname();

        if ($newPath) {
            if ($this->filesystem->has($newPath)) {
                throw new FileCopyException($file, $newPath, 'File already exists');
            }
        } else {
            $fileName = FileUtils::generateFileCopyBasename($file, static fn (string $path): bool => !$this->filesystem->has(FileUtils::path($file->getPath(), $path)));
            $newPath = FileUtils::path($file->getPath(), $fileName);
        }

        $this->filesystem->copy($path, $newPath);

        return new StorageFile($this, $newPath, true);
    }

    /**
     * @return StorageFile[]
     *
     * @throws FilesystemException
     */
    public function getFilesByDirectory(string $directory, ?array $onlyFileNames = null): array
    {
        $files = [];
        $listing = $this->filesystem->listContents($directory);

        foreach ($listing as $item) {
            $path = $item['path'];
            $basename = FileUtils::basename($path);

            if ($onlyFileNames && !\in_array($basename, $onlyFileNames, true)) {
                continue;
            }

            $storageFile = new StorageFile($this, $path, true);
            $storageFile->setSize($item['file_size']);
            $storageFile->setLastModifiedAt((new \DateTime())->setTimestamp($item['last_modified']));

            $files[] = $storageFile;
        }

        return $files;
    }

    public function getFile($directory, $name): FileInterface
    {
        $path = \sprintf('%s/%s', $directory, $name);

        return new StorageFile($this, $path, true);
    }

    /**
     * @throws FilesystemException
     */
    public function isFile($directory, $name): bool
    {
        $path = \sprintf('%s/%s', $directory, $name);

        return $this->filesystem->has($path);
    }

    /**
     * @throws FilesystemException
     */
    public function getSize(StorageFile $file): int
    {
        return $this->filesystem->fileSize($file->getPathname());
    }

    /**
     * @throws FilesystemException
     */
    public function getTimestamp(StorageFile $file): int
    {
        return $this->filesystem->lastModified($file->getPathname());
    }

    /**
     * @throws FilesystemException
     */
    public function getMimeType(StorageFile $file): string
    {
        return $this->filesystem->mimeType($file->getPathname());
    }

    /**
     * @throws FilesystemException
     */
    public function getMetadata(string $filePathName): FileMetadata
    {
        $size = $this->filesystem->fileSize($filePathName);
        $timestamp = $this->filesystem->lastModified($filePathName);
        $mimetype = $this->filesystem->mimeType($filePathName);

        $metadata = new FileMetadata();
        $metadata->size = $size;
        $metadata->mimeType = $mimetype;
        $metadata->modificationTime = (new \DateTime())->setTimestamp($timestamp);

        return $metadata;
    }
}
