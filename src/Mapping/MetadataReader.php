<?php

declare(strict_types=1);

namespace Glavweb\UploaderBundle\Mapping;

use Glavweb\UploaderBundle\Mapping\Attribute\Uploadable;
use Glavweb\UploaderBundle\Mapping\Attribute\UploadableField;

/**
 * Читает #[Uploadable] / #[UploadableField] Glavweb Uploader (замена Vich MetadataReader).
 */
class MetadataReader
{
    public function isUploadable(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $reflection = new \ReflectionClass($class);
        do {
            if ([] !== $reflection->getAttributes(Uploadable::class)) {
                return true;
            }
            $reflection = $reflection->getParentClass();
        } while ($reflection instanceof \ReflectionClass);

        return false;
    }

    /**
     * @return array<string, array{mapping: string, fileNameProperty: string}>
     */
    public function getUploadableFields(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }

        $fields = [];
        $reflection = new \ReflectionClass($class);
        do {
            foreach ($reflection->getProperties() as $property) {
                if (isset($fields[$property->getName()])) {
                    continue;
                }

                $attributes = $property->getAttributes(UploadableField::class);
                if ([] === $attributes) {
                    continue;
                }

                /** @var UploadableField $attribute */
                $attribute = $attributes[0]->newInstance();
                $fields[$property->getName()] = [
                    'mapping' => $attribute->getMapping(),
                    'fileNameProperty' => $property->getName(),
                ];
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection instanceof \ReflectionClass);

        return $fields;
    }
}
