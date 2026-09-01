<?php

declare(strict_types=1);

namespace Glavweb\UploaderBundle\Helper;

use Glavweb\UploaderBundle\Model\MediaInterface;

/**
 * URL файла через Glavweb MediaHelper (замена Vich UploaderHelper).
 */
class UploaderHelper
{
    public function __construct(
        private readonly MediaHelper $mediaHelper,
    ) {
    }

    public function asset(object $object, string $fieldName, ?string $className = null): ?string
    {
        $getter = 'get'.ucfirst($fieldName);
        if (!method_exists($object, $getter)) {
            return null;
        }

        $value = $object->$getter();
        if (!$value instanceof MediaInterface) {
            return null;
        }

        return $this->mediaHelper->getContentPath($value, true);
    }
}
