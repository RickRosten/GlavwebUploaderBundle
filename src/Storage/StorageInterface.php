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

use Glavweb\UploaderBundle\Exception\CropImageException;
use Glavweb\UploaderBundle\Exception\FileCopyException;
use Glavweb\UploaderBundle\File\FileInterface;
use Glavweb\UploaderBundle\File\FileMetadata;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Interface StorageInterface.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
interface StorageInterface
{
    /**
     * Uploads a File instance to the configured storage.
     */
    public function upload(FileInterface $file, string $directory, ?string $name, bool $attachment = false): FileInterface;

    public function uploadTmpFileByLink(string $link): FileInterface;

    public function uploadFiles(array $files, string $directory): array;

    public function getFilesByDirectory(string $directory, ?array $onlyFileNames = null): array;

    public function clearOldFiles($directory, $lifetime);

    public function getFile($directory, $name): FileInterface;

    public function isFile($directory, $name): bool;

    public function removeFile(FileInterface $file);

    /**
     * @throws FileCopyException
     */
    public function copyFile(FileInterface $file, ?string $newPath = null): FileInterface;

    public function moveFile(FileInterface $file, string $newPath): void;

    /**
     * @throws CropImageException
     */
    public function cropImage(FileInterface $file, array $cropData): string;

    public function getMetadata(string $filePathName): FileMetadata;

    public function addFileChunk(File $file, string $fileId, int $chunkIndex): void;

    public function hasAllFileChunks(string $fileId, int $chunkTotal): bool;

    public function concatFileChunks(File $file, FileMetadata $metadata, string $fileId): FileInterface;

    /**
     * Cleanup trash files.
     */
    public function cleanup(): void;
}
