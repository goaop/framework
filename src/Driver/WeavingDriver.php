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

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

/**
 * A weaving driver decides HOW the classes of the application get their advices woven in
 *
 * Everything else is shared: aspects, pointcuts, the advice matcher, the advisor cache and the
 * join-point runtime. The kernel's `driver` option names the class of the driver to use; the
 * kernel instantiates it (no constructor arguments), registers it in the container under this
 * interface and boots it once from AspectKernel::init(). The built-in default is the stream
 * filter driver (Go\Driver\StreamFilter\StreamFilterDriver); other drivers live in packages of
 * their own (the stream wrapper driver, the ffi driver built on lisachenko/z-engine) and plug
 * in through this interface only.
 *
 * A driver that weaves by transforming the source of a file through the transformer chain
 * (AspectKernel::registerTransformerServices()) implements SourceTransformingDriver, so the
 * kernel registers the chain for it and the cache warmer can build its cache ahead of time.
 */
interface WeavingDriver
{
    /**
     * Hooks the driver into the class loading of the application
     *
     * Called once by AspectKernel::init(): the kernel options are normalized, the framework
     * services are registered, no aspect is loaded yet and configureAop() runs right after.
     * The driver registers the services it needs and takes over class loading (wrapping
     * composer's loader, registering a stream filter or wrapper, hooking the engine).
     *
     * @throws \Go\Aop\Exception\InvalidConfigurationException When the host or the kernel options do not fit the driver
     */
    public function boot(AspectKernel $kernel, AspectContainer $container): void;
}
