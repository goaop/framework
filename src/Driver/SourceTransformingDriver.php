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

namespace Go\Driver;

/**
 * A weaving driver that weaves by transforming sources: the file of a loaded class runs through
 * the transformer chain and the result is cached below the cache directory
 *
 * The kernel registers the transformer services for such a driver only
 * (AspectKernel::registerTransformerServices()), and the cache warmer (`cache:warmup:aop`,
 * `debug:weaving`) can transform the whole application ahead of time for it. A driver weaving
 * otherwise (the ffi driver rewires loaded classes in the engine) never gets the chain.
 */
interface SourceTransformingDriver extends WeavingDriver {}
