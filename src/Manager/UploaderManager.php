<?php

/*
 * This file is part of the Glavweb UploaderBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\UploaderBundle\Manager;

use Glavweb\UploaderBundle\Driver\AttributeDriver;
use Glavweb\UploaderBundle\Exception\CropImageException;
use Glavweb\UploaderBundle\Exception\ProviderNotFoundException;
use Glavweb\UploaderBundle\File\FileInterface;
use Glavweb\UploaderBundle\File\FileMetadata;
use Glavweb\UploaderBundle\Model\MediaInterface;
use Glavweb\UploaderBundle\Model\ModelManagerInterface;
use Glavweb\UploaderBundle\Naming\NamerInterface;
use Glavweb\UploaderBundle\Provider\ImageProvider;
use Glavweb\UploaderBundle\Provider\ProviderFileInterface;
use Glavweb\UploaderBundle\Provider\ProviderInterface;
use Glavweb\UploaderBundle\Provider\ProviderTypes;
use Glavweb\UploaderBundle\Storage\StorageInterface;
use Glavweb\UploaderBundle\Util\FileUtils;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class UploaderManager.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class UploaderManager
{
    protected ?ModelManagerInterface $modelManager = null;

    protected ?StorageInterface $storage = null;

    private ?array $providers = null;

    /**
     * @var string[]
     */
    private array $blackListExtensions = [
        'php',
    ];

    private ?AttributeDriver $driverAnnotation = null;

    private ?RequestStack $requestStack = null;

    public function __construct(
        private array $config,
        private readonly ContainerInterface $container,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getStorage(): StorageInterface
    {
        if (!$this->storage instanceof StorageInterface) {
            $this->storage = $this->container->get($this->config['storage']);
        }

        return $this->storage;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getDriverAnnotation(): AttributeDriver
    {
        if (!$this->driverAnnotation instanceof AttributeDriver) {
            $this->driverAnnotation = $this->container->get('glavweb_uploader.data_driver.attribute');
        }

        return $this->driverAnnotation;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getRequestStack(): RequestStack
    {
        if (!$this->requestStack instanceof RequestStack) {
            $this->requestStack = $this->container->get('request_stack');
        }

        return $this->requestStack;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getModelManager(): ModelManagerInterface
    {
        if (!$this->modelManager instanceof ModelManagerInterface) {
            $this->modelManager = $this->container->get($this->config['model_manager']);
        }

        return $this->modelManager;
    }

    /**
     * Upload file.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ProviderNotFoundException
     */
    public function upload(FileInterface|string $link, string $context, string $requestId): array
    {
        $this->providers = null;
        $provider = $this->getProvider($context, $link);

        if (!$provider instanceof ProviderInterface) {
            throw new ProviderNotFoundException('Provider not found.');
        }

        $useOrphanage = $this->getContextConfig($context, 'use_orphanage');
        $thumbnailPath = null;
        $contentPath = null;
        $name = $provider->getName();
        $uploadedFile = null;

        if ($provider instanceof ProviderFileInterface) {
            $uploadedFile = $this->uploadFile($provider->getFile(), $context);

            $contentPath = FileUtils::basename($uploadedFile->getPathname());
            if ($provider instanceof ImageProvider) {
                $thumbnailPath = $contentPath;
            }

            if ($link != $provider->getFile()) {
                $name = $uploadedFile->getClientOriginalName();
            }
        }

        if (!$thumbnailPath) {
            $thumbnailUrl = $provider->getThumbnailUrl();
            if ($thumbnailUrl) {
                $tmpFile = $this->getStorage()->uploadTmpFileByLink($thumbnailUrl);
                $uploadedFile = $this->uploadFile($tmpFile, $context);

                $thumbnailPath = FileUtils::basename($uploadedFile->getPathname());
            }
        }

        $media = $this->getModelManager()->createMedia();
        $media->setContext($context);
        $media->setProviderName($provider->getProviderName());
        $media->setProviderReference($provider->getProviderReference());
        $media->setContentPath($contentPath);
        $media->setThumbnailPath($thumbnailPath);
        $media->setName($name);
        $media->setDescription($provider->getDescription());
        $media->setWidth($provider->getWidth());
        $media->setHeight($provider->getHeight());
        $media->setContentType($provider->getContentType());
        $media->setContentSize($provider->getContentSize());
        $media->setIsOrphan($useOrphanage);
        $media->setRequestId($requestId);
        $this->getModelManager()->updateMedia($media);

        return [
            'uploadedFile' => $uploadedFile,
            'media' => $media,
        ];
    }

    /**
     * Upload file.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function uploadFile(FileInterface $file, string $context): FileInterface
    {
        $directory = $this->getContextConfig($context, 'upload_directory');

        // Upload file
        /** @var NamerInterface $namer */
        $namer = $this->container->get($this->getContextConfig($context, 'namer'));
        $name = $namer->name($file);

        $this->checkHackingName($name);

        return $this->getStorage()->upload($file, $directory, $name);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getProvider(string $context, FileInterface|string $link): ?ProviderInterface
    {
        if (\is_string($link)) {
            $providers = $this->getProviders($context, ProviderTypes::LINK);
            foreach ($providers as $provider) {
                if ($provider->checkLink($link)) {
                    $provider->parse($link);

                    return $provider;
                }
            }

            $link = $this->getStorage()->uploadTmpFileByLink($link);
        }

        $providers = $this->getProviders($context, ProviderTypes::FILE);
        foreach ($providers as $provider) {
            if ($provider->checkLink($link)) {
                $provider->parse($link);

                return $provider;
            }
        }

        return null;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getProviders(string $context, ?string $type = null): array
    {
        if (null === $this->providers) {
            $providers = [];
            foreach ($this->getContextConfig($context, 'providers') as $providerName) {
                $provider = $this->container->get($providerName);
                if (!$provider instanceof ProviderInterface) {
                    throw new \RuntimeException('Class "'.$provider::class.'" is not provider.');
                }

                $providers[$provider->getProviderType()][] = $provider;
            }

            $this->providers = $providers;
        }

        if ($type) {
            return $this->providers[$type] ?? [];
        }

        return $this->providers;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ProviderNotFoundException
     */
    public function getProviderByName(string $name): ProviderInterface
    {
        $provider = null;
        if ($this->container->has($name)) {
            $provider = $this->container->get($name);
        }

        if (!$provider instanceof ProviderInterface) {
            throw new ProviderNotFoundException('Provider not found.');
        }

        return $provider;
    }

    /**
     * @return MediaInterface[] Array of uploaded media entities
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handleUpload(string $requestId): array
    {
        $this->eventDispatcher->addListener(KernelEvents::TERMINATE, fn () => $this->removeMarkedMedia($requestId));
        $this->renameMarkedMedia($requestId);

        return $this->uploadOrphans($requestId);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function copyMedia(MediaInterface $media): MediaInterface
    {
        $directory = $this->getContextConfig($media->getContext(), 'upload_directory');
        $name = $media->getContentPath();
        $thumbnailPath = $media->getThumbnailPath();

        $file = $this->getStorage()->getFile($directory, $name);
        $fileCopy = $file->copy();
        $copyName = $fileCopy->getBasename();

        $thumbnailPath = $thumbnailPath === $name ? $copyName : $thumbnailPath;

        $mediaCopy = (clone $media)
            ->setContentPath($copyName)
            ->setThumbnailPath($thumbnailPath)
            ->setToken(null)
            ->setRequestId(null)
            ->setIsOrphan(false);

        $this->getModelManager()->updateMedia($mediaCopy);

        return $mediaCopy;
    }

    /**
     * @param MediaInterface[] $medias    Array of medias
     * @param array            $positions Array of positions medias like as [mediaId, mediaId,  ...]
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortMedias(array $medias, array $positions): void
    {
        $this->getModelManager()->sortMedias($medias, $positions);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function uploadOrphans(string $requestId): array
    {
        $medias = $this->getModelManager()->findOrphans($requestId);

        // update file models
        $uploadMedias = [];
        foreach ($medias as $media) {
            $media->setIsOrphan(false);
            $media->setRequestId(null);

            $uploadMedias[] = $media;
        }

        return $uploadMedias;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function removeMarkedMedia(string $requestId): void
    {
        $this->getModelManager()->removeMarkedMedia($requestId);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function renameMarkedMedia(string $requestId): void
    {
        $this->getModelManager()->renameMarkedMedia($requestId);
    }

    /**
     * Clear orphanage.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function clearOrphanage(): void
    {
        $this->getModelManager()->removeOrphans($this->config['orphanage']['lifetime']);

        $this->getStorage()->cleanup();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function removeFileFromStorage(FileInterface $file): void
    {
        $this->getStorage()->removeFile($file);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function removeMediaFromStorage(MediaInterface $media): void
    {
        $storage = $this->getStorage();
        $context = $media->getContext();

        $directory = $this->getContextConfig($context, 'upload_directory');

        $files = [];
        if (($contentPath = $media->getContentPath()) && $storage->isFile($directory, $contentPath)) {
            $files[] = $storage->getFile($directory, $contentPath);
        }

        if ($thumbnailPath = $media->getThumbnailPath() && $thumbnailPath != $contentPath && $storage->isFile($directory, $contentPath)) {
            $files[] = $storage->getFile($directory, $thumbnailPath);
        }

        foreach ($files as $file) {
            $storage->removeFile($file);
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function getContextConfig(string $context, ?string $option = null)
    {
        if (!isset($this->config['mappings'][$context])) {
            throw new \RuntimeException('Context "'.$context.'" not defined.');
        }

        $contextConfig = $this->config['mappings'][$context];

        if ($option) {
            if (!isset($contextConfig[$option])) {
                throw new \RuntimeException('Context "'.$context.'" option "'.$option.'" not defined.');
            }

            return $contextConfig[$option];
        }

        return $contextConfig;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    protected function checkHackingName($name): void
    {
        $pathinfo = pathinfo((string) $name);
        $extension = $pathinfo['extension'] ?? null;

        if (!$extension) {
            throw new \RuntimeException('Extension not found.');
        }

        if (\in_array($extension, $this->blackListExtensions)) {
            throw new \RuntimeException('Extension "'.$extension.'" not supported.');
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws CropImageException
     * @throws NotFoundExceptionInterface
     */
    public function cropImage(MediaInterface $media, array $cropData): void
    {
        $storage = $this->getStorage();
        $context = $media->getContext();
        $contentPath = $media->getContentPath();
        $directory = $this->getContextConfig($context, 'upload_directory');

        if ('glavweb_uploader.provider.image' !== $media->getProviderName()) {
            throw new CropImageException('The provider name must be "glavweb_uploader.provider.image".');
        }

        if (!$storage->isFile($directory, $contentPath)) {
            throw new CropImageException('File is not found.');
        }

        $file = $storage->getFile($directory, $contentPath);

        // update file name
        $newFilename = $this->getStorage()->cropImage($file, $cropData);
        $newContentPath = FileUtils::basename($newFilename);
        $media->setContentPath($newContentPath);
        $media->setThumbnailPath($newContentPath);

        $this->getModelManager()->updateMedia($media);
    }

    public function isChunkUpload(Request $request): bool
    {
        return (bool) $request->getPayload()->get($this->config['chunk_upload']['total_count_request_parameter']);
    }

    /**
     * @throws \Exception
     */
    public function handleChunkUpload(Request $request, File $file): ?FileInterface
    {
        $config = $this->config['chunk_upload'];
        $payload = $request->getPayload();
        $fileId = $payload->get($config['file_id_request_parameter']);
        $chunkIndex = $payload->get($config['current_index_request_parameter']);
        $chunkTotal = $payload->get($config['total_count_request_parameter']);

        $this->getStorage()->addFileChunk($file, $fileId, $chunkIndex);

        if ($this->getStorage()->hasAllFileChunks($fileId, $chunkTotal)) {
            $imageWidth = $payload->get($config['image_width_request_parameter']);
            $imageHeight = $payload->get($config['image_height_request_parameter']);
            $mimeType = $payload->get($config['type_request_parameter']);

            $metadata = new FileMetadata();
            $metadata->originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : null;
            $metadata->mimeType = $mimeType;
            $metadata->width = $imageWidth ? (int) $imageWidth : 0;
            $metadata->height = $imageHeight ? (int) $imageHeight : 0;
            $metadata->isImage = str_starts_with((string) $mimeType, 'image');

            return $this->getStorage()->concatFileChunks($file, $metadata, $fileId);
        }

        return null;
    }
}
