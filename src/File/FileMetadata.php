<?php

namespace Glavweb\UploaderBundle\File;

/**
 * Class FileMetadata.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class FileMetadata
{
    public ?bool $isImage = null;

    public ?int $width = null;

    public ?int $height = null;

    public ?string $mimeType = null;

    public ?string $originalName = null;

    public ?int $size = null;

    public ?\DateTimeInterface $modificationTime = null;
}
