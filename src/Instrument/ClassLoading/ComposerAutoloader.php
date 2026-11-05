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
use Go\Aop\Exception\InvalidConfigurationException;
use ReflectionClass;

/**
 * The Composer autoloader of the application, as the kernel of this process wrapped it
 *
 * Build-time tooling (the opcache.preload script, the opcache file cache primer) needs what the autoloader knows:
 * its `vendor` directory, so a generated script can require `autoload.php` again, its class map and the files it
 * includes on every request.
 *
 * @internal
 */
final class ComposerAutoloader
{
    private function __construct(
        /** Composer's own class loader, unwrapped from AopComposerLoader */
        public readonly ClassLoader $classLoader,
        /** Directory holding `autoload.php` and `composer/` */
        public readonly string $vendorDir,
    ) {}

    /**
     * Returns the autoloader of this process: the Composer loader wrapped by the kernel, or the first registered one
     *
     * @throws InvalidConfigurationException When no Composer class loader is registered
     */
    public static function ofProcess(): self
    {
        $classLoader = null;
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof AopComposerLoader) {
                $classLoader = $loader[0]->getOriginalLoader();
                break;
            }
        }
        $registeredLoaders = class_exists(ClassLoader::class, false) ? ClassLoader::getRegisteredLoaders() : [];
        if ($classLoader === null) {
            $classLoader = $registeredLoaders[array_key_first($registeredLoaders) ?? ''] ?? null;
        }
        if ($classLoader === null) {
            throw InvalidConfigurationException::composerAutoloaderNotFound();
        }

        // Composer registers every loader under its vendor directory; a loader created by hand has none, then
        // the installed ClassLoader class itself tells where `vendor/composer/` lies
        $vendorDir = array_search($classLoader, $registeredLoaders, true);
        if (!is_string($vendorDir)) {
            $vendorDir = dirname((string) new ReflectionClass(ClassLoader::class)->getFileName(), 2);
        }

        return new self($classLoader, $vendorDir);
    }

    /**
     * Path of `autoload.php`, which a generated script requires to register this autoloader
     */
    public string $autoloadFile {
        get => $this->vendorDir . '/autoload.php';
    }

    /**
     * Files Composer includes on every request (the `files` autoload type), none when the install has none
     *
     * @return list<string>
     */
    public function autoloadedFiles(): array
    {
        $autoloadFiles = $this->vendorDir . '/composer/autoload_files.php';
        if (!is_file($autoloadFiles)) {
            return [];
        }
        $files = include $autoloadFiles;

        return is_array($files) ? array_values(array_filter($files, is_string(...))) : [];
    }
}
