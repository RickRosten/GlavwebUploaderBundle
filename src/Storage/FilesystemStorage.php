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

use Glavweb\UploaderBundle\Exception\Base64DecodingException;
use Glavweb\UploaderBundle\Exception\CropImageException;
use Glavweb\UploaderBundle\Exception\FileNotFoundException;
use Glavweb\UploaderBundle\File\FileInterface;
use Glavweb\UploaderBundle\File\FileMetadata;
use Glavweb\UploaderBundle\File\FilesystemFile;
use Glavweb\UploaderBundle\Util\CropImage;
use Glavweb\UploaderBundle\Util\FileUtils;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mime\MimeTypes;

/**
 * Class FilesystemStorage.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class FilesystemStorage extends LocalStorage
{
    public function __construct(string $tempDirectoryPath)
    {
        parent::__construct(new Filesystem(), $tempDirectoryPath);
    }

    public function upload(FileInterface $file, string $directory, ?string $name = null, bool $attachment = false): FileInterface
    {
        /* @var File $file */
        if (null === $name) {
            $name = $file->getBasename();
        }

        $path = \sprintf('%s/%s', $directory, $name);
        $targetName = FileUtils::basename($path);
        $targetDir = \dirname($path);

        return $file->move($targetDir, $targetName);
    }

    /**
     * @throws Base64DecodingException
     * @throws FileNotFoundException
     */
    public function uploadTmpFileByLink(string $link): FileInterface
    {
        $file = FileUtils::getTempFileByUrl($link);

        return new FilesystemFile($file);
    }

    /**
     * @return FileInterface[]
     */
    public function uploadFiles(array $files, string $directory): array
    {
        $return = [];
        foreach ($files as $file) {
            $return[] = $this->upload($file, $directory);
        }

        return $return;
    }

    /**
     * @return FilesystemFile[]
     */
    public function getFilesByDirectory(string $directory, ?array $onlyFileNames = null): array
    {
        $finder = new Finder();

        try {
            $finder->in($directory)->files();
        } catch (\InvalidArgumentException) {
            // catch non-existing directory exception.
            // This can happen if getFilesByDirectory is called and no file has yet been uploaded

            // push empty array into the finder so we can emulate no files found
            $finder->append([]);
        }

        // filter
        if ($onlyFileNames) {
            $finder->filter(static fn ($file): bool => \in_array($file->getFilename(), $onlyFileNames));
        }

        $files = [];
        foreach ($finder as $file) {
            /* @var File $file */
            $files[] = new FilesystemFile(new File($file->getPathname()));
        }

        return $files;
    }

    public function clearOldFiles($directory, $lifetime): void
    {
        $filesystem = new Filesystem();
        $finder = new Finder();

        try {
            $finder->in($directory)->date('<='.-1 * (int) $lifetime.'seconds')->files();
        } catch (\InvalidArgumentException) {
            // the finder will throw an exception of type InvalidArgumentException
            // if the directory he should search in does not exist
            // in that case we don't have anything to clean
            return;
        }

        foreach ($finder as $file) {
            $filesystem->remove($file);
        }
    }

    /**
     * @return FilesystemFile
     */
    public function getFile($directory, $name): FileInterface
    {
        $path = \sprintf('%s/%s', $directory, $name);
        $file = new File($path);

        return new FilesystemFile($file);
    }

    public function isFile($directory, $name): bool
    {
        $path = \sprintf('%s/%s', $directory, $name);

        return is_file($path);
    }

    public function removeFile(FileInterface $file): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($file->getPathname());
    }

    public function copyFile(FileInterface $file, ?string $newPath = null): FileInterface
    {
        if ($file instanceof FilesystemFile) {
            if ($newPath) {
                $fileInfo = new \SplFileInfo($newPath);

                return $file->copy($fileInfo->getPath(), $fileInfo->getBasename());
            }

            return $file->copy();
        }

        throw new \InvalidArgumentException('FilesystemStorage::copyFile() supports only FilesystemFile.');
    }

    /**
     * @throws CropImageException
     */
    public function cropImage(FileInterface $file, array $cropData): string
    {
        $pathname = $file->getPathname();
        $cropResult = CropImage::crop($pathname, $pathname, $cropData);
        if ($cropResult) {
            return FileUtils::saveFileWithNewVersion($file);
        }

        return $pathname;
    }

    public function moveFile(FileInterface $file, string $newPath): void
    {
        $filesystem = new Filesystem();
        $filesystem->rename($file->getPathname(), $newPath);
    }

    public function getMetadata(string $filePathName): FileMetadata
    {
        $metadata = new FileMetadata();
        $metadata->size = $this->getSize($filePathName);
        $metadata->mimeType = $this->getMimeType($filePathName);
        $metadata->modificationTime = new \DateTimeImmutable('@'.$this->getTimestamp($filePathName));
        $metadata->isImage = false;

        $imageSize = getimagesize($filePathName);
        if (false !== $imageSize) {
            $metadata->isImage = true;
            [$metadata->width, $metadata->height] = $imageSize;
        }

        return $metadata;
    }

    public function getMimeType(string $filePathName): ?string
    {
        return MimeTypes::getDefault()->guessMimeType($filePathName) ?: null;
    }

    public function getTimestamp(string $filePathName): int
    {
        return \filemtime($filePathName);
    }

    public function getSize(string $filePathName): int
    {
        return \filesize($filePathName);
    }
}
