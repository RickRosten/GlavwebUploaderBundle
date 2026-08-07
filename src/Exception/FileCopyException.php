<?php

/*
 * This file is part of the Glavweb UploaderBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\UploaderBundle\Exception;

use Glavweb\UploaderBundle\File\FileInterface;

/**
 * Class FileCopyException.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class FileCopyException extends Exception
{
    public function __construct(FileInterface $file, string $path, string $message)
    {
        parent::__construct("Can't create copy of file \"{$file->getPathname()}\" with new path \"$path\": $message");
    }
}
