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
 * Interface MultipartUploadManagerInterface.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
interface MultipartUploadManagerInterface
{
    public function has(string $key): bool;

    public function get(string $key): MultipartUploadInterface;

    /**
     * @return MultipartUploadInterface[]
     */
    public function list(): array;

    public function create(string $key, string $externalId): MultipartUploadInterface;

    public function delete(MultipartUploadInterface $multipartUpload): void;

    public function addPart(MultipartUploadInterface $multipartUpload, int $number, array $data = []): MultipartUploadPartInterface;

    public function countParts(string $key): int;
}
