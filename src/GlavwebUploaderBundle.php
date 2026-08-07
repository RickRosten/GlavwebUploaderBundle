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
     * @param array<string, mixed> $config
     */
    private function applyMappingsDefaults(array $config): array
    {
        $mappings = &$config['mappings'];

        foreach ($mappings as &$contextConfig) {
            $extendDefaults = $contextConfig['extend_defaults'];
            $defaults = $extendDefaults ? $config['mappings_defaults'] : self::DEFAULT_MAPPINGS_VALUES;

            foreach ($defaults as $defaultKey => $defaultValue) {
                $value = $contextConfig[$defaultKey] ?? null;

                if ($extendDefaults && \is_array($value) && \is_array($defaultValue)) {
                    $contextConfig[$defaultKey] = array_unique(array_merge($defaultValue, $value));
                }

                if (null === $value) {
                    $contextConfig[$defaultKey] = $defaultValue;
                }
            }
        }

        return $config;
    }
}
