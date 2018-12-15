<?php
declare(strict_types=1);

/*
 * @author Martin Fris <rasta@lj.sk>
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
