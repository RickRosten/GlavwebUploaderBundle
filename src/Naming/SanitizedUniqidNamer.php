<?php

declare(strict_types=1);

namespace Glavweb\UploaderBundle\Naming;

use Glavweb\UploaderBundle\File\FileInterface;
use Glavweb\UploaderBundle\Naming\NamerInterface;

/**
 * Class SanitizedUniqidNamer.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class SanitizedUniqidNamer implements NamerInterface
{
    /**
     * @return string
     *
     * @throws \RuntimeException
     */
    public function name(FileInterface $file)
    {
        $clientOriginalName = $file->getClientOriginalName();

        $extension = $file->getExtension();
        if (!$extension) {
            throw new \RuntimeException('The extension cannot be guessed.');
        }

        $baseName = substr($clientOriginalName, 0, (strlen($extension) + 1) * -1);
        $baseName = preg_replace('/\W/isu', '_', $baseName);
        //        $baseName = mb_strtolower($baseName, 'utf-8');

        $replace = [
            'jpeg' => 'jpg',
        ];
        $extension = strtr($extension, $replace);

        return sprintf('%s.%s', uniqid($baseName.'_', false), $extension);
    }
}

class_alias(SanitizedUniqidNamer::class, GuideFileNamer::class);
