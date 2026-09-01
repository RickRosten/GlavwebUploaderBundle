<?php

declare(strict_types=1);

namespace Glavweb\UploaderBundle\Naming;

use Glavweb\UploaderBundle\File\FileInterface;
use Glavweb\UploaderBundle\Naming\NamerInterface;

/**
 * Class OriginalNamer.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class OriginalNamer implements NamerInterface
{
    /**
     * {@inheritDoc}
     */
    public function name(FileInterface $file)
    {
        return $file->getFilename();
    }
}
