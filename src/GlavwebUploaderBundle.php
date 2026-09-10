<?php

/*
 * This file is part of the Glavweb UploaderBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\UploaderBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Class GlavwebUploaderBundle.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class GlavwebUploaderBundle extends AbstractBundle
{
    public const DEFAULT_MAPPINGS_VALUES = [
        'route_prefix' => '',
        'max_size' => \PHP_INT_MAX,
        'max_files' => \PHP_INT_MAX,
        'use_orphanage' => false,
        'namer' => 'glavweb_uploader.namer.uniqid',
        'providers' => [],
        'allowed_mimetypes' => [],
        'disallowed_mimetypes' => [],
        'attachment' => false,
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import(__DIR__.'/../config/definition.php');
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->setParameter('glavweb_uploader.temp_directory', $config['temp_directory']);

        $configurator->import(__DIR__.'/../config/services.yaml');

        $s3ClientId = $container->getParameter('glavweb_uploader.storage.s3.client');
        if (\is_string($s3ClientId) && '' !== $s3ClientId) {
            $container->setAlias('glavweb_uploader.storage.s3.client', $s3ClientId);
        }

        if (!empty($config['mappings_defaults'])) {
            $config = $this->applyMappingsDefaults($config);
        }

        $container->setParameter('glavweb_uploader.config', $config);
    }

    /**
     * Applies mappings_defaults to every mapping: performs a deep merge
     * with fallback to the default values.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function applyMappingsDefaults(array $config): array
    {
        $mappings = &$config['mappings'];

        foreach ($mappings as &$contextConfig) {
            $extendDefaults = $contextConfig['extend_defaults'];
            $defaults = $extendDefaults ? $config['mappings_defaults'] : self::DEFAULT_MAPPINGS_VALUES;

            foreach ($defaults as $defaultKey => $defaultValue) {
                $contextConfig[$defaultKey] = $this->mergeDefaultValue(
                    $contextConfig[$defaultKey] ?? null,
                    $defaultValue,
                    $extendDefaults
                );
            }
        }

        return $config;
    }

    /**
     * Merges the mapping value with the default one:
     * null in the mapping means fallback to the default.
     */
    private function mergeDefaultValue(mixed $value, mixed $defaultValue, bool $extendDefaults): mixed
    {
        if (null === $value) {
            return $defaultValue;
        }

        if ($extendDefaults && \is_array($value) && \is_array($defaultValue)) {
            return $this->mergeArrays($defaultValue, $value);
        }

        return $value;
    }

    /**
     * Recursively (deep) merges the default array with the mapping value.
     * Mapping values have priority. An explicitly specified empty array
     * in the mapping fully overrides the default value; null in the
     * mapping means fallback to the default.
     *
     * Numeric (list) arrays are merged with duplicates removed and keys
     * renumbered — they must never become associative.
     */
    private function mergeArrays(array $defaultValue, array $value): array
    {
        if ([] === $value) {
            return [];
        }

        if ([] === $defaultValue) {
            return $value;
        }

        if (\array_is_list($value) || \array_is_list($defaultValue)) {
            // array_values() removes the gaps left by array_unique() — the list stays a list
            return \array_values(\array_unique(\array_merge($defaultValue, $value)));
        }

        $merged = $defaultValue;

        foreach ($value as $key => $val) {
            if (null === $val) {
                // null in the mapping value — keep the default
                continue;
            }

            if (\is_array($val) && isset($merged[$key]) && \is_array($merged[$key])) {
                $merged[$key] = $this->mergeArrays($merged[$key], $val);
            } else {
                $merged[$key] = $val;
            }
        }

        return $merged;
    }
}
