<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

/*
 * @author Martin Fris <rasta@lj.sk>
 */

namespace Symfony\Component\Finder;

/**
 * This helper function overrides the PHP glob() function so it is able to be run with virtual file system,
 * which is supported by Webmozart\Glob\Glob
 *
 * The signature stays compatible with how Symfony Finder invokes glob():
 * a string pattern plus an int bitmask of GLOB_* flags.
 *
 * @return string[]
 */
function glob(string $pattern, int $flags = 0): array
{
    return \Webmozart\Glob\Glob::glob($pattern, $flags);
}
