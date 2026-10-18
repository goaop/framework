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

namespace Go\Driver\StreamFilter;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Driver\SourceTransformingDriver;
use Go\Instrument\ClassLoading\AopComposerLoader;

/**
 * The default weaving driver: source transformation at load time through a PHP stream filter
 *
 * A wrapper around composer's class loader (AopComposerLoader) redirects the file of an advised
 * class through the `php://filter` stream filter (SourceTransformingLoader), which serves the
 * cached woven source or runs the transformer chain on a cache miss. Pure PHP, no extension.
 */
final class StreamFilterDriver implements SourceTransformingDriver
{
    public function boot(AspectKernel $kernel, AspectContainer $container): void
    {
        AopComposerLoader::init($kernel->getOptions(), $container);
    }
}
