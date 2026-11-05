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

namespace Go\Instrument\ClassLoading\Warmup;

use ErrorException;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\ParserReflection\ReflectionEngine;
use Throwable;

/**
 * Weaves one source file into the cache in the current process
 *
 * The transformation pipeline must be registered first, see SourceTransformingLoader::ensureRegistered().
 *
 * @internal Used by the cache warmer and its worker processes
 */
final class FileWarmer
{
    /**
     * @param int $cachedFiles Number of parsed files the reflection engine keeps in memory: it keeps every file by
     *                         default, so a warmup of a few thousand files would exhaust any memory limit. More files
     *                         save re-parsing parent classes, fewer save memory
     */
    public function __construct(int $cachedFiles = AspectKernel::DEFAULT_PARSED_FILES_LIMIT)
    {
        ReflectionEngine::setMaximumCachedFiles($cachedFiles);
    }

    /**
     * Weaves the file through the source transforming stream filter, which writes the cache
     *
     * @return string|null Error message, or null when the file was processed
     */
    public function warmUp(string $path): ?string
    {
        // Warnings raised while weaving fail the file, unless they are silenced by error_reporting() or `@`
        set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if ((error_reporting() & $errno) === 0) {
                return false;
            }
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            file_get_contents(
                FilterInjectorTransformer::PHP_FILTER_READ . SourceTransformingLoader::FILTER_IDENTIFIER . '/resource=' . $path,
            );

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        } finally {
            restore_error_handler();
        }
    }
}
