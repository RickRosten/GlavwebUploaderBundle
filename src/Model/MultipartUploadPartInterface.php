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

/**
 * Interface MultipartUploadPartInterface.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
interface MultipartUploadPartInterface
{
    public function getNumber(): int;

    public function getData(): array;

    public function getMultipartUpload(): MultipartUploadInterface;
}
