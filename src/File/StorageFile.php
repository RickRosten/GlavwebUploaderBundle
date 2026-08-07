<?php

namespace Glavweb\UploaderBundle\File;

use Glavweb\UploaderBundle\Exception\Exception;
use Glavweb\UploaderBundle\Storage\StorageInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Class StorageFile.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class StorageFile implements FileInterface
{
    private ?int $size = null;

    private ?string $originalName = null;

    private ?string $mimeType = null;

    private bool $isImage = false;

    private ?int $height = null;

    private ?int $width = null;

    private ?\DateTimeInterface $lastModifiedAt = null;

    public function __construct(
        private readonly StorageInterface $storage,
        private string $pathname,
        private bool $uploaded,
    ) {
    }

    public function getSize(): int|false
    {
        if (!isset($this->size) && $this->isUploaded()) {
            $this->fetchMetadata();
        }

        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function setOriginalName(?string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getLastModifiedAt(): ?\DateTimeInterface
    {
        if (!isset($this->lastModifiedAt) && $this->isUploaded()) {
            $this->fetchMetadata();
        }

        return $this->lastModifiedAt;
    }

    public function setLastModifiedAt(?\DateTimeInterface $lastModifiedAt): static
    {
        $this->lastModifiedAt = $lastModifiedAt;

        return $this;
    }

    public function getPathname(): string
    {
        return $this->pathname;
    }

    public function getPath(): string
    {
        return pathinfo($this->getPathname(), \PATHINFO_DIRNAME);
    }

    public function getMimeType(): ?string
    {
        if (!isset($this->mimeType) && $this->isUploaded()) {
            $this->fetchMetadata();
        }

        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getBasename(): string
    {
        return pathinfo($this->getPathname(), \PATHINFO_BASENAME);
    }

    public function getExtension(): string
    {
        return pathinfo($this->getPathname(), \PATHINFO_EXTENSION);
    }

    public function getClientOriginalName(): string
    {
        if (!isset($this->originalName) && $this->isUploaded()) {
            $this->fetchMetadata();
        }

        return $this->originalName;
    }

    public function guessExtension(): ?string
    {
        $mimeType = $this->getMimeType();
        if (!$mimeType) {
            return null;
        }

        $extensions = MimeTypes::getDefault()->getExtensions($mimeType);

        return $extensions[0] ?? null;
    }

    public function move(string $directory, ?string $name = null): static
    {
        if (!$name) {
            $name = $this->getBasename();
        }

        $newPath = \sprintf('%s/%s', $directory, $name);

        $this->storage->moveFile($this, $newPath);

        $this->pathname = $newPath;

        return $this;
    }

    public function copy(?string $directory = null, ?string $name = null): FileInterface
    {
        $newPath = null;

        if ($directory) {
            if (!$name) {
                $name = $this->getBasename();
            }

            $newPath = \sprintf('%s/%s', $directory, $name);
        }

        return $this->storage->copyFile($this, $newPath);
    }

    /**
     * @throws Exception
     */
    public function fetchMetadata(): void
    {
        if (!$this->isUploaded()) {
            throw new Exception('File isn\'t uploaded');
        }

        $metadata = $this->storage->getMetadata($this->getPathname());

        $this->setMetadata($metadata);
    }

    public function setMetadata(FileMetadata $metadata): void
    {
        if (isset($metadata->size)) {
            $this->setSize($metadata->size);
        }

        if (isset($metadata->modificationTime)) {
            $this->setLastModifiedAt($metadata->modificationTime);
        }

        if (isset($metadata->mimeType)) {
            $this->setMimeType($metadata->mimeType);
        }

        if (isset($metadata->originalName)) {
            $this->setOriginalName($metadata->originalName);
        }

        if (isset($metadata->height)) {
            $this->setHeight($metadata->height);
        }

        if (isset($metadata->isImage)) {
            $this->setIsImage($metadata->isImage);
        }

        if (isset($metadata->width)) {
            $this->setWidth($metadata->width);
        }
    }

    public function isUploaded(): bool
    {
        return $this->uploaded;
    }

    public function setUploaded(bool $uploaded): static
    {
        $this->uploaded = $uploaded;

        return $this;
    }

    public function isImage(): ?bool
    {
        if (!isset($this->isImage) && $this->isUploaded()) {
            $this->fetchMetadata();
        }

        return $this->isImage;
    }

    public function setIsImage(bool $isImage): static
    {
        $this->isImage = $isImage;

        return $this;
    }

    public function getHeight(): ?int
    {
        if (!isset($this->height) && $this->isUploaded()) {
            $this->fetchMetadata();
        }

        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getWidth(): ?int
    {
        if (!isset($this->width) && $this->isUploaded()) {
            $this->fetchMetadata();
        }

        return $this->width;
    }

    public function setWidth(int $width): static
    {
        $this->width = $width;

        return $this;
    }
}
