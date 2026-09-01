<?php

declare(strict_types=1);

namespace Glavweb\UploaderBundle\Naming;

use Glavweb\UploaderBundle\Exception\ValidationException;
use Glavweb\UploaderBundle\File\FileInterface;
use Glavweb\UploaderBundle\Naming\NamerInterface;

/**
 * Class OriginalExtUniqueNamer.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class OriginalExtUniqueNamer implements NamerInterface
{
    private ?array $allowedMimeTypes;

    public function __construct(?array $allowedMimeTypes = null)
    {
        $this->allowedMimeTypes = $allowedMimeTypes;
    }

    /**
     * @return string
     *
     * @throws \RuntimeException
     */
    public function name(FileInterface $file)
    {
        $extension = pathinfo($file->getClientOriginalName(), \PATHINFO_EXTENSION);

        if ($this->allowedMimeTypes) {
            $key = strtolower($extension);
            if (!key_exists($key, $this->allowedMimeTypes)) {
                throw new ValidationException("Неверное расширение файла \"$extension\".");
            }

            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, $this->allowedMimeTypes[$key])) {
                throw new ValidationException("Расширение \"$extension\" не соответствует типу файла \"$mimeType\".");
            }
        }

        $filename = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        $uniqid = uniqid($filename.'_');

        return $extension ? sprintf('%s.%s', $uniqid, $extension) : $uniqid;
    }
}
