<?php

/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\FileSystem;

use IteratorIterator;

/**
 *
 */
final class NormalizeIterator extends IteratorIterator
{

    /**
     * @return SplFileInfo
     */
    public function current()
    {
        /* @var $symfonyFileInfo \Symfony\Component\Finder\SplFileInfo */
        $symfonyFileInfo = parent::current();
        $originalPath = $symfonyFileInfo->getPathname();
        $newPath = str_replace(DIRECTORY_SEPARATOR, '/', $originalPath);

        return new SplFileInfo($newPath);
    }
}
