<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Composer\Autoload\ClassLoader;

/**
 * A wrapper the kernel registers in place of composer's class loader
 *
 * Both weaving drivers decorate composer's loader (the stream driver to redirect advised files
 * through the stream filter, the z-engine driver to weave a class right after it was loaded),
 * so tools that look for a ClassLoader in spl_autoload_functions() unwrap either through this
 * interface.
 */
interface ComposerLoaderDecorator
{
    /**
     * Returns the wrapped composer class loader
     */
    public function getOriginalLoader(): ClassLoader;
}
