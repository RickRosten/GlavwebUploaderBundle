<?php

/*
 * This file is part of the Glavweb UploaderBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\UploaderBundle\Model;

use DateTimeInterface;

/**
 * Interface MultipartUploadInterface.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
interface MultipartUploadInterface
{
    public function getId(): string;

    public function getKey(): string;

    public function getLastModifiedAt(): DateTimeInterface;

    /**
     * @return MultipartUploadPartInterface[]
     */
    public function getParts(): array;

    public function addPart(MultipartUploadPartInterface $part): void;
}
