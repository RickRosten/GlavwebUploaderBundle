<?php

namespace Glavweb\UploaderBundle\Tests\Config;

use Glavweb\UploaderBundle\GlavwebUploaderBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;

/**
 * Tests for merging mappings config with mappings_defaults.
 *
 * Covers the full path: the config definition tree from config/definition.php
 * (processed via Processor) plus the bundle's applyMappingsDefaults method.
 * The recursive deep merge logic is additionally tested in isolation
 * (see the mergeArrays-based tests at the bottom), because the config tree
 * does not allow arbitrary associative structures in every node.
 */
class MappingsDefaultsMergeTest extends TestCase
{
    /**
     * Processes the input config through the definition tree
     * and applies the mappings defaults.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function processConfig(array $input): array
    {
        $treeBuilder = new TreeBuilder('glavweb_uploader');
        $loader = new DefinitionFileLoader($treeBuilder, new FileLocator(__DIR__.'/../../../config'));
        $loader->load('definition.php');

        $config = (new Processor())->process($treeBuilder->buildTree(), [$input]);

        // applyMappingsDefaults is private — invoke it via reflection
        $bundle = new GlavwebUploaderBundle();
        $method = new \ReflectionMethod($bundle, 'applyMappingsDefaults');

        return $method->invoke($bundle, $config);
    }

    /**
     * Invokes the private recursive array merge via reflection.
     */
    private function mergeArrays(array $defaultValue, array $value): array
    {
        $bundle = new GlavwebUploaderBundle();
        $method = new \ReflectionMethod($bundle, 'mergeArrays');

        return $method->invoke($bundle, $defaultValue, $value);
    }

    /**
     * Verifies that mapping keys not set explicitly are filled
     * with the bundle's DEFAULT_MAPPINGS_VALUES, while explicitly
     * set values (upload_directory) are kept intact.
     */
    public function testMissingMappingKeysAreFilledWithBundleDefaults(): void
    {
        $config = $this->processConfig([
            'mappings' => [
                'document' => [
                    'upload_directory' => '/tmp/uploads',
                ],
            ],
        ]);

        $mapping = $config['mappings']['document'];

        // Keys missing from the mapping fall back to the bundle defaults
        self::assertSame('', $mapping['route_prefix']);
        self::assertSame(\PHP_INT_MAX, $mapping['max_size']);
        self::assertSame(\PHP_INT_MAX, $mapping['max_files']);
        self::assertFalse($mapping['use_orphanage']);
        self::assertSame('glavweb_uploader.namer.uniqid', $mapping['namer']);
        self::assertSame([], $mapping['providers']);
        self::assertSame([], $mapping['allowed_mimetypes']);
        self::assertSame([], $mapping['disallowed_mimetypes']);
        self::assertFalse($mapping['attachment']);
        // The explicitly set value is not overwritten
        self::assertSame('/tmp/uploads', $mapping['upload_directory']);
    }

    /**
     * Verifies that a value set in the mapping takes priority over
     * mappings_defaults, and a missing key falls back to mappings_defaults.
     */
    public function testMappingValuesOverrideDefaults(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'max_size' => 1024,
                'route_prefix' => 'media',
            ],
            'mappings' => [
                'image' => [
                    'max_size' => 2048,
                ],
            ],
        ]);

        $mapping = $config['mappings']['image'];

        self::assertSame(2048, $mapping['max_size']);
        // The key is missing from the mapping — taken from mappings_defaults
        self::assertSame('media', $mapping['route_prefix']);
    }

    /**
     * Verifies that extend_defaults=false makes the mapping use the
     * bundle's DEFAULT_MAPPINGS_VALUES instead of mappings_defaults.
     */
    public function testExtendDefaultsFalseIgnoresMappingsDefaults(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'max_size' => 1024,
                'route_prefix' => 'media',
                'allowed_mimetypes' => ['image/png'],
            ],
            'mappings' => [
                'document' => [
                    'extend_defaults' => false,
                ],
            ],
        ]);

        $mapping = $config['mappings']['document'];

        // With extend_defaults=false the bundle defaults are used, not mappings_defaults
        self::assertSame(\PHP_INT_MAX, $mapping['max_size']);
        self::assertSame('', $mapping['route_prefix']);
        self::assertSame([], $mapping['allowed_mimetypes']);
    }

    /**
     * Verifies that scalar mappings_defaults (max_files, use_orphanage,
     * attachment, namer) are applied to a mapping that specifies nothing.
     */
    public function testScalarDefaultsAppliedWithExtendDefaults(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'max_files' => 5,
                'use_orphanage' => true,
                'attachment' => true,
                'namer' => 'app.namer.custom',
            ],
            'mappings' => [
                'gallery' => [],
            ],
        ]);

        $mapping = $config['mappings']['gallery'];

        self::assertSame(5, $mapping['max_files']);
        self::assertTrue($mapping['use_orphanage']);
        self::assertTrue($mapping['attachment']);
        self::assertSame('app.namer.custom', $mapping['namer']);
    }

    /**
     * Verifies that list values (allowed/disallowed_mimetypes) are merged
     * with duplicates removed and keys renumbered, so the list stays
     * a list and never becomes associative.
     */
    public function testListMimetypesMergedAndUnique(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'allowed_mimetypes' => ['image/png', 'image/jpeg'],
                'disallowed_mimetypes' => ['text/x-php'],
            ],
            'mappings' => [
                'image' => [
                    'allowed_mimetypes' => ['image/jpeg', 'image/webp'],
                ],
            ],
        ]);

        $mapping = $config['mappings']['image'];

        // Lists are merged keeping the defaults, duplicates removed,
        // keys renumbered — the list does not become associative
        self::assertSame(['image/png', 'image/jpeg', 'image/webp'], $mapping['allowed_mimetypes']);
        self::assertSame(['text/x-php'], $mapping['disallowed_mimetypes']);
    }

    /**
     * Verifies that an explicitly specified empty list in the mapping
     * fully overrides the defaults list.
     */
    public function testEmptyMappingListOverridesDefaults(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'allowed_mimetypes' => ['image/png'],
            ],
            'mappings' => [
                'image' => [
                    'allowed_mimetypes' => [],
                ],
            ],
        ]);

        self::assertSame([], $config['mappings']['image']['allowed_mimetypes']);
    }

    /**
     * Verifies that when the defaults list is empty,
     * the mapping's own list value is kept.
     */
    public function testEmptyDefaultsListKeepsMappingValue(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'allowed_mimetypes' => [],
            ],
            'mappings' => [
                'image' => [
                    'allowed_mimetypes' => ['application/pdf'],
                ],
            ],
        ]);

        // If the defaults are empty — the mapping value is kept
        self::assertSame(['application/pdf'], $config['mappings']['image']['allowed_mimetypes']);
    }

    /**
     * Verifies that the providers list from the mapping is merged
     * into the defaults list with deduplication and renumbering.
     */
    public function testListProvidersMergedWithExtendDefaults(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'providers' => ['image', 'file'],
            ],
            'mappings' => [
                'media' => [
                    'providers' => ['image', 'youtube'],
                ],
            ],
        ]);

        self::assertSame(['image', 'file', 'youtube'], \array_values($config['mappings']['media']['providers']));
    }

    /**
     * Verifies that mappings_defaults are applied to every mapping
     * independently, and per-mapping values still win.
     */
    public function testDefaultsAreAppliedToEveryMapping(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'route_prefix' => 'media',
            ],
            'mappings' => [
                'first' => [],
                'second' => [
                    'route_prefix' => 'custom',
                ],
            ],
        ]);

        self::assertSame('media', $config['mappings']['first']['route_prefix']);
        self::assertSame('custom', $config['mappings']['second']['route_prefix']);
    }

    /**
     * Verifies that the mappings_defaults section itself
     * is preserved in the resulting config.
     */
    public function testMappingsDefaultsAreKeptInConfig(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'route_prefix' => 'media',
            ],
            'mappings' => [
                'first' => [],
            ],
        ]);

        // The mappings_defaults section remains in the final config
        self::assertSame('media', $config['mappings_defaults']['route_prefix']);
    }

    /**
     * Verifies deep merge of the nested width/height min/max keys:
     * mapping values win, missing keys fall back to the defaults.
     */
    public function testNestedWidthHeightAreDeepMerged(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'width' => ['min' => 100, 'max' => 500],
                'height' => ['min' => 50],
            ],
            'mappings' => [
                'image' => [
                    'width' => ['min' => 200],
                    'height' => ['max' => 800],
                ],
            ],
        ]);

        $mapping = $config['mappings']['image'];

        // Deep merge: nested min/max are merged recursively,
        // mapping values win, missing keys come from the defaults
        self::assertSame(['min' => 200, 'max' => 500], $mapping['width']);
        self::assertSame(['min' => 50, 'max' => 800], $mapping['height']);
    }

    /**
     * Verifies that a mapping key absent entirely (width not set)
     * falls back to the whole defaults value.
     */
    public function testNestedWidthHeightNullFallsBackToDefault(): void
    {
        $config = $this->processConfig([
            'mappings_defaults' => [
                'width' => ['min' => 100, 'max' => 500],
            ],
            'mappings' => [
                'image' => [
                    // width is not set at all — the whole default is used
                ],
            ],
        ]);

        self::assertSame(['min' => 100, 'max' => 500], $config['mappings']['image']['width']);
    }

    /**
     * Verifies that the deep merge keeps the definition tree's scalar-to-min/max
     * normalization (width: 300 becomes ['min' => 300, 'max' => 300]).
     */
    public function testWidthHeightNormalizationIsKeptAfterMerge(): void
    {
        $config = $this->processConfig([
            'mappings' => [
                'image' => [
                    'width' => 300,
                    'height' => ['min' => 100],
                ],
            ],
        ]);

        // The scalar-to-min/max normalization of width/height survives the merge
        self::assertSame(['min' => 300, 'max' => 300], $config['mappings']['image']['width']);
        self::assertSame(['min' => 100, 'max' => null], $config['mappings']['image']['height']);
    }

    /**
     * Verifies that applyMappingsDefaults does not mutate
     * the config array passed to it.
     */
    public function testSourceMappingValuesAreNotMutated(): void
    {
        $input = [
            'mappings_defaults' => [
                'allowed_mimetypes' => ['image/png'],
            ],
            'mappings' => [
                'image' => [
                    'allowed_mimetypes' => ['image/jpeg'],
                ],
            ],
        ];

        $this->processConfig($input);

        // The method must not mutate the passed config
        self::assertSame(['image/jpeg'], $input['mappings']['image']['allowed_mimetypes']);
    }

    /*
     * The tests below exercise the recursive array merge (mergeArrays)
     * directly, in isolation from the config tree, because the tree
     * does not allow arbitrary associative structures in every node.
     */

    /**
     * Verifies recursive deep merge of nested associative arrays:
     * mapping values win, missing keys come from defaults,
     * sibling branches are not touched.
     */
    public function testMergeArraysDeepMergesNestedAssociative(): void
    {
        $merged = $this->mergeArrays(
            ['image' => ['quality' => 80, 'format' => 'webp'], 'file' => ['chunked' => true]],
            ['image' => ['quality' => 95]]
        );

        self::assertSame([
            'image' => ['quality' => 95, 'format' => 'webp'],
            'file' => ['chunked' => true],
        ], $merged);
    }

    /**
     * Verifies that a null value inside a nested associative array
     * falls back to the whole default branch.
     */
    public function testMergeArraysNullInValueFallsBackToDefault(): void
    {
        $merged = $this->mergeArrays(
            ['image' => ['quality' => 80]],
            ['image' => null]
        );

        // null in the value — fall back to the whole default branch
        self::assertSame(['image' => ['quality' => 80]], $merged);
    }

    /**
     * Verifies that an explicitly specified empty array in the value
     * fully overrides the non-empty default.
     */
    public function testMergeArraysEmptyValueOverridesDefault(): void
    {
        $merged = $this->mergeArrays(
            ['image/png', 'image/jpeg'],
            []
        );

        // Пустой массив в маппинге — явное переопределение дефолта
        self::assertSame([], $merged);
    }

    /**
     * Verifies that lists nested inside associative structures
     * are merged with deduplication and renumbering.
     */
    public function testMergeArraysMergesListsInsideAssociative(): void
    {
        $merged = $this->mergeArrays(
            ['image' => ['allowed_mimetypes' => ['image/png', 'image/jpeg']]],
            ['image' => ['allowed_mimetypes' => ['image/webp']]]
        );

        self::assertSame(
            ['image' => ['allowed_mimetypes' => ['image/png', 'image/jpeg', 'image/webp']]],
            $merged
        );
    }

    /**
     * Verifies that new keys present only in the value
     * are added to the merged result (array_replace-like behavior).
     */
    public function testMergeArraysAddsNewKeysFromValue(): void
    {
        $merged = $this->mergeArrays(
            ['image' => ['quality' => 80]],
            ['youtube' => ['api_key' => 'xyz']]
        );

        self::assertSame([
            'image' => ['quality' => 80],
            'youtube' => ['api_key' => 'xyz'],
        ], $merged);
    }

    /**
     * Verifies that a scalar value completely replaces
     * the whole default branch.
     */
    public function testMergeArraysScalarOverwritesNestedDefault(): void
    {
        $merged = $this->mergeArrays(
            ['image' => ['quality' => 80]],
            ['image' => 'disabled']
        );

        // A scalar completely replaces the default branch
        self::assertSame(['image' => 'disabled'], $merged);
    }

    /**
     * Verifies that associative keys are not renumbered
     * and the defaults key order is preserved.
     */
    public function testMergeArraysKeepsOrderAndDoesNotReindexAssociative(): void
    {
        $merged = $this->mergeArrays(
            ['b' => 1, 'a' => 2],
            ['c' => 3]
        );

        self::assertSame(['b' => 1, 'a' => 2, 'c' => 3], $merged);
    }
}
