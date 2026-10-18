<?php

declare(strict_types=1);

namespace Go\Stubs\Driver;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Driver\SourceTransformingDriver;

/**
 * A source-transforming driver that hooks nothing: the kernel registers the transformer chain for it
 */
final class TransformingDriver implements SourceTransformingDriver
{
    public function boot(AspectKernel $kernel, AspectContainer $container): void {}
}
