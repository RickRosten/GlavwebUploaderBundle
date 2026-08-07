<?php

/*
 * This file is part of the Glavweb UploaderBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\UploaderBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Glavweb\UploaderBundle\Model\MultipartUploadInterface;
use Glavweb\UploaderBundle\Model\MultipartUploadPartInterface;

/**
 * Class MultipartUpload.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
#[ORM\Table(name: 'glavweb_multipart_upload')]
#[ORM\Entity]
class MultipartUpload implements MultipartUploadInterface
{
    #[ORM\Column(name: 'id', type: 'string')]
    #[ORM\Id]
    private string $id;

    #[ORM\Column(name: 'key', type: 'string')]
    private ?string $key = null;

    #[ORM\Column(name: 'last_modified_at', type: 'datetimetz')]
    private ?\DateTimeInterface $lastModifiedAt = null;

    #[ORM\OneToMany(
        targetEntity: MultipartUploadPart::class,
        indexBy: 'number',
        mappedBy: 'multipartUpload',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $parts;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->parts = new ArrayCollection();
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id;
    }

    #[\Override]
    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    #[\Override]
    public function getLastModifiedAt(): \DateTimeInterface
    {
        return $this->lastModifiedAt;
    }

    public function setLastModifiedAt(\DateTimeInterface $lastModifiedAt): static
    {
        $this->lastModifiedAt = $lastModifiedAt;

        return $this;
    }

    /**
     * @return Collection<int, MultipartUploadPart>
     */
    public function getPartsCollection(): Collection
    {
        return $this->parts;
    }

    #[\Override]
    public function getParts(): array
    {
        return $this->parts->toArray();
    }

    #[\Override]
    public function addPart(MultipartUploadPartInterface $part): void
    {
        $this->parts->add($part);
    }
}
