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

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use Glavweb\UploaderBundle\Entity\MultipartUpload;
use Glavweb\UploaderBundle\Entity\MultipartUploadPart;

/**
 * Class MultipartUploadManager.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class MultipartUploadManager implements MultipartUploadManagerInterface
{
    private EntityManagerInterface $em;

    public function __construct(Registry $doctrine)
    {
        $this->em = $doctrine->getManager();

        \assert($this->em instanceof EntityManagerInterface);
    }

    #[\Override]
    public function has(string $key): bool
    {
        $countQuery = $this->em->createQueryBuilder()
                               ->select('count(t.key)')
                               ->from(MultipartUpload::class, 't')
                               ->where('t.key = :key')
                               ->setParameter('key', $key)
                               ->getQuery();

        $count = $countQuery->getSingleScalarResult();

        return $count > 0;
    }

    #[\Override]
    public function get(string $key): MultipartUploadInterface
    {
        return $this->em->getRepository(MultipartUpload::class)->findOneBy(['key' => $key]);
    }

    #[\Override]
    public function list(): array
    {
        return $this->em->getRepository(MultipartUpload::class)->findAll();
    }

    #[\Override]
    public function create(string $key, string $externalId): MultipartUploadInterface
    {
        $multipartUpload = new MultipartUpload($externalId);
        $multipartUpload->setKey($key);
        $multipartUpload->setLastModifiedAt(new \DateTimeImmutable());

        $this->em->persist($multipartUpload);
        $this->em->flush();

        return $multipartUpload;
    }

    #[\Override]
    public function delete(MultipartUploadInterface $multipartUpload): void
    {
        $this->em->remove($multipartUpload);
        $this->em->flush();
    }

    #[\Override]
    public function addPart(MultipartUploadInterface $multipartUpload, int $number, array $data = []): MultipartUploadPartInterface
    {
        \assert($multipartUpload instanceof MultipartUpload);

        if ($multipartUpload->getPartsCollection()->containsKey($number)) {
            $multipartUpload->getPartsCollection()->remove($number);
            $this->em->flush();
        }

        $part = new MultipartUploadPart();
        $part
            ->setNumber($number)
            ->setData($data)
            ->setMultipartUpload($multipartUpload);

        $multipartUpload->addPart($part);
        $multipartUpload->setLastModifiedAt(new \DateTimeImmutable());

        $this->em->persist($multipartUpload);
        $this->em->flush();

        return $part;
    }

    #[\Override]
    public function countParts(string $key): int
    {
        $multipartUpload = $this->get($key);
        \assert($multipartUpload instanceof MultipartUpload);

        return $multipartUpload->getPartsCollection()->count();
    }
}
