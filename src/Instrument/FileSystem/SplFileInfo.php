<?php
declare(strict_types=1);

/*
 * @author Martin Fris <rasta@lj.sk>
 */

namespace Go\Instrument\FileSystem;

use SplFileInfo as CoreSplFileInfo;

/**
 *
 */
final class SplFileInfo extends CoreSplFileInfo
{

    /**
     * @param string $file_name
     */
    public function __construct($file_name)
    {
        $file_name = str_replace(DIRECTORY_SEPARATOR, '/', $file_name);

        parent::__construct($file_name);
    }
}
