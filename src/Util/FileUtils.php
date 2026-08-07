<?php

namespace Glavweb\UploaderBundle\Util;

use Glavweb\UploaderBundle\Exception\Base64DecodingException;
use Glavweb\UploaderBundle\Exception\FileNotFoundException;
use Glavweb\UploaderBundle\File\FileInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Class FileUtils.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class FileUtils
{
    public static function path(string ...$parts): string
    {
        return implode(\DIRECTORY_SEPARATOR, $parts);
    }

    public static function appendExtension(string $filePath, ?string $extension): string
    {
        if ($extension) {
            return $filePath.'.'.$extension;
        }

        return $filePath;
    }

    public static function generateFileCopyBasename(FileInterface $file, ?callable $isNameAllowed = null): string
    {
        $fileInfo = pathinfo($file->getPathname());

        $name = $fileInfo['filename'];
        $extension = $fileInfo['extension'] ?? null;
        $newCopyNumber = 0;
        $regexp = '/_copy(?:_(\d+))?$/';
        $originalName = $name;

        preg_match($regexp, $name, $matches);

        if ($matches) {
            [$match, $copyNumber] = $matches;

            if ($match) {
                if ($copyNumber) {
                    $newCopyNumber = (int) $copyNumber + 1;
                } else {
                    $newCopyNumber = 1;
                }

                $originalName = preg_replace($regexp, '', $name);
            }
        }

        do {
            $newName = $originalName.'_copy'.($newCopyNumber > 0 ? '_'.$newCopyNumber : '');
            $newNameWithExtension = self::appendExtension($newName, $extension);
            ++$newCopyNumber;
        } while ($isNameAllowed && !$isNameAllowed($newNameWithExtension));

        return $newNameWithExtension;
    }

    public static function saveFileWithNewVersion(FileInterface $file): string
    {
        $pathParts = pathinfo($file->getPathname());
        $directory = $pathParts['dirname'];

        $filenameFarts = explode('_', $pathParts['filename']);
        if (\count($filenameFarts) > 1) {
            ++$filenameFarts[1];
        } else {
            $filenameFarts[1] = '1';
        }

        $fileName = implode('_', $filenameFarts).'.'.$pathParts['extension'];

        $newFile = $file->move($directory, $fileName);

        return $newFile->getPathname();
    }

    /**
     * @throws Base64DecodingException
     * @throws FileNotFoundException
     */
    public static function getTempFileByUrl(string $link): ?File
    {
        $source = null;
        $target = null;
        $path = tempnam(sys_get_temp_dir(), 'gup');

        try {
            if (StringUtils::isUrl($link)) {
                $source = fopen($link, 'r');
                if (!$source) {
                    throw new FileNotFoundException(\sprintf("File '%s' not found", $link));
                }

                $target = fopen($path, 'w');

                if (!stream_copy_to_stream($source, $target)) {
                    throw new \RuntimeException('Stream copy failed');
                }
            } else {
                $fileContents = base64_decode($link);
                if ('' === $fileContents || '0' === $fileContents) {
                    throw new Base64DecodingException("File can't be decoded from string");
                }

                $target = fopen($path, 'w');

                if (!fwrite($target, $fileContents)) {
                    throw new \RuntimeException('Unable to write content into temporal file');
                }
            }

            $path = stream_get_meta_data($target)['uri'];
        } finally {
            if (\is_resource($source)) {
                fclose($source);
            }

            if (\is_resource($target)) {
                fclose($target);
            }
        }

        return new File($path);
    }

    /**
     * Fix bug with cyrillic symbols.
     */
    public static function basename(string $fileName): string
    {
        return substr(strrchr($fileName, \DIRECTORY_SEPARATOR), 1) ?: $fileName;
    }
}
