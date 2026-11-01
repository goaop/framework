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

namespace Go\Console;

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Core\AspectKernel;

/**
 * Boots the aspect kernel of an application by executing its loader file
 *
 * Shared by the console commands and the cache warmup workers, so a worker boots exactly like the command.
 *
 * @internal
 */
final class KernelLoader
{
    /**
     * Executes the loader file and returns the kernel it initialized
     *
     * @param string $loader Path to the PHP file that initializes the aspect kernel, resolved against the include path
     */
    public static function load(string $loader): AspectKernel
    {
        $path = self::resolve($loader);

        // The loader is executed only to boot the kernel: anything it prints is discarded
        ob_start();
        try {
            include_once $path;
        } finally {
            ob_end_clean();
        }

        if (!class_exists(AspectKernel::class, false)) {
            throw InvalidConfigurationException::kernelNotConfiguredInLoader($path);
        }

        return AspectKernel::getInstance();
    }

    /**
     * Returns the absolute path of a loader file
     */
    public static function resolve(string $loader): string
    {
        $path = stream_resolve_include_path($loader);
        if ($path === false || !is_readable($path)) {
            throw InvalidConfigurationException::invalidLoaderPath($loader);
        }

        return $path;
    }
}
