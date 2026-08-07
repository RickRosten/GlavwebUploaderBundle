<?php

/*
 * This file is part of the Glavweb UploaderBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\UploaderBundle\File;

use Glavweb\UploaderBundle\Exception\FileCopyException;
use Glavweb\UploaderBundle\Util\FileUtils;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class FilesystemFile.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class FilesystemFile implements FileInterface
{
    public function __construct(private File $file, private readonly ?string $originalName = null)
    {
    }

    public function getExtension(): string
    {
        return $this->file->getExtension();
    }

    public function getSize(): int|false
    {
        return $this->file->getSize();
    }

    public function getPathname(): string
    {
        return $this->file->getPathname();
    }

    public function getPath(): string
    {
        return $this->file->getPath();
    }

    public function getMimeType(): ?string
    {
        return $this->file->getMimeType();
    }

    public function getBasename(): string
    {
        return $this->file->getBasename();
    }

    public function getClientOriginalName(): string
    {
        if ($this->originalName) {
            return $this->originalName;
        }

        if ($this->file instanceof UploadedFile) {
            return $this->file->getClientOriginalName();
        }

        return $this->file->getBasename();
    }

    public function guessExtension(): ?string
    {
        return $this->file->guessExtension();
    }

    public function isImage(): ?bool
    {
        return false !== getimagesize($this->getPathname());
    }

    public function getWidth(): ?int
    {
        $imageSize = getimagesize($this->getPathname());

        return false !== $imageSize ? $imageSize[0] : null;
    }

    public function getHeight(): ?int
    {
        $imageSize = getimagesize($this->getPathname());

        return false !== $imageSize ? $imageSize[1] : null;
    }

    public function move(string $directory, ?string $name = null): static
    {
        $this->file = $this->file->move($directory, $name);

        return $this;
    }

    /**
     * @throws FileCopyException
     */
    public function copy(?string $directory = null, ?string $name = null): FileInterface
    {
        $newPath = null;

        if ($directory) {
            if (!$name) {
                $name = $this->getBasename();
            }

            $newPath = \sprintf('%s/%s', $directory, $name);
        }

        if ($newPath) {
            if (file_exists($newPath)) {
                throw new FileCopyException($this, $newPath, 'File already exists');
            }
        } else {
            $directory = $this->getPath();

            $fileName = FileUtils::generateFileCopyBasename($this, static fn (string $name): bool => !file_exists(FileUtils::path($directory, $name)));

            $newPath = FileUtils::path($directory, $fileName);
        }

        $fileInfo = new \SplFileInfo($newPath);
        $target = $fileInfo->getPathname();

        $targetDirectory = \dirname($target);
        if (!is_dir($targetDirectory)) {
            @mkdir($targetDirectory, 0777, true);
        }

        $error = null;
        set_error_handler(static function ($type, $msg) use (&$error): bool {
            $error = $msg;

            return true;
        });
        $copied = copy($this->getPathname(), $target);
        restore_error_handler();

        if (!$copied) {
            throw new FileException(\sprintf('Could not copy the file "%s" to "%s" (%s).', $this->getPathname(), $target, strip_tags($error)));
        }

        @chmod($target, 0666 & ~umask());

        return new self(new File($target), $this->getClientOriginalName());
    }
}
