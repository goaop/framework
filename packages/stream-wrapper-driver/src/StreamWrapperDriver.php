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

namespace Go\Driver\StreamWrapper;

use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Driver\SourceTransformingDriver;
use Go\Driver\StreamWrapper\Exception\StreamWrapperConfigurationException;

/**
 * Weaving driver that replaces the `file://` stream wrapper: every file included from within the weaving scope
 * is transformed at load time, whoever includes it
 *
 * Mechanism: {@see AopStreamWrapper} takes the place of PHP's native `file://` wrapper
 * (`stream_wrapper_unregister('file')` + `stream_wrapper_register('file', AopStreamWrapper::class)`). An include
 * of a file within the scope (appDir, includePaths, excludePaths) runs the same cache-or-transform pipeline as the
 * stream filter driver ({@see \Go\Instrument\ClassLoading\SourceTransformingLoader::transformSource()}) and is
 * served from memory under its original path; every other file operation is delegated to the native wrapper.
 * The driver covers every join point the stream filter driver covers, with the same transformer chain, caches
 * and warmup (`cache:warmup:aop`).
 *
 * Trade-offs against the stream filter driver:
 * - Every file operation of the process (fopen, stat, is_file, file_exists, opendir, rename, ...) now passes
 *   through userland PHP and the restore/re-register dance of the wrapper, so work that is not an include is
 *   slower than with the native wrapper. The include path itself costs one extra read of the file.
 * - In exchange the woven sources are cached by opcache under their original path (a `php://filter` include
 *   never is), and includes are intercepted by nature: no composer loader wrapper, no rewriting of
 *   include/require statements. {@see Features::INTERCEPT_INCLUDES} is therefore refused: it would make the
 *   FilterInjectorTransformer rewrite includes into `php://filter` URIs, which bypass the wrapper and opcache.
 * - Without a wrapped composer loader, {@see \Go\Instrument\ClassLoading\AopComposerLoader::findOriginalFile()}
 *   (used by the kernel in debug mode to track the files of aspects without loading them) finds nothing, so the
 *   kernel falls back to reflection there: the aspect classes are loaded when they are registered (debug only).
 */
final class StreamWrapperDriver implements SourceTransformingDriver
{
    /**
     * @throws StreamWrapperConfigurationException When the kernel options ask for the include interception
     */
    public function boot(AspectKernel $kernel, AspectContainer $container): void
    {
        $options = $kernel->getOptions();
        if (($options['features'] & Features::INTERCEPT_INCLUDES) !== 0) {
            throw StreamWrapperConfigurationException::includeInterceptionImplied();
        }

        AopStreamWrapper::register($container, $options);
    }
}
