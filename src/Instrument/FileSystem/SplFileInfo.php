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
