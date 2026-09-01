<?php

declare(strict_types=1);

namespace Glavweb\UploaderBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Glavweb\UploaderBundle\Entity\Media;

/**
 * Class MediaOrphanageEventSubscriber.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class MediaOrphanageDoctrineListener
{
    public function prePersist(PrePersistEventArgs $eventArgs): void
    {
        $this->process($eventArgs);
    }

    public function preUpdate(PreUpdateEventArgs $eventArgs): void
    {
        $this->process($eventArgs);
    }

    private function process(LifecycleEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();
        $className = $object::class;

        if (str_starts_with($className, 'Glavweb\\UploaderBundle\\Entity\\')) {
            return;
        }

        $metadata = $eventArgs->getObjectManager()->getClassMetadata($className);

        foreach ($metadata->getAssociationMappings() as $associationMapping) {
            if (Media::class == $associationMapping->targetEntity) {
                $value = $metadata->getFieldValue($object, $associationMapping->fieldName);
                if ($value instanceof Media) {
                    $value->setIsOrphan(false);
                }

                if (is_iterable($value)) {
                    foreach ($value as $item) {
                        $item->setIsOrphan(false);
                    }
                }
            }
        }
    }
}
