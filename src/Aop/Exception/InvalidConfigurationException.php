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

namespace Go\Aop\Exception;

use Go\Core\AspectContainer;
use InvalidArgumentException;

/**
 * Invalid kernel options or state, container registrations, console arguments or other user configuration
 */
class InvalidConfigurationException extends InvalidArgumentException implements ExceptionInterface
{
    public static function kernelNotCreated(): self
    {
        return new self('Aspect kernel is not initialized yet, call init() on the kernel of the application first');
    }

    public static function kernelNotInitialized(string $kernelClass): self
    {
        return new self($kernelClass . ' is not initialized yet, call init() first');
    }

    public static function kernelNotConfiguredInLoader(string $loaderPath): self
    {
        return new self("Kernel was not initialized yet, please configure it in the {$loaderPath}");
    }

    public static function unknownFeature(int $feature): self
    {
        return new self(sprintf('Unknown feature %d, use Go\\Aop\\Features constants', $feature));
    }

    /**
     * @param list<string> $knownOptions
     */
    public static function unknownOption(string $option, ?string $suggestion, array $knownOptions): self
    {
        return new self(sprintf(
            'Unknown kernel option "%s"%s. Known options are: %s.',
            $option,
            $suggestion !== null ? sprintf(', did you mean "%s"?', $suggestion) : '',
            implode(', ', $knownOptions),
        ));
    }

    public static function missingCacheDir(): self
    {
        return new self('You need to provide valid cache directory for Go! AOP framework.');
    }

    public static function invalidCacheFileMode(mixed $cacheFileMode): self
    {
        return new self(sprintf(
            'Option "cacheFileMode" must be an integer permission mask between 0600 and 0777 '
            . 'that grants the owner read and write access, got %s.',
            is_int($cacheFileMode) ? sprintf('0%o', $cacheFileMode) : get_debug_type($cacheFileMode),
        ));
    }

    public static function invalidFeatures(mixed $features): self
    {
        return new self(sprintf(
            'Option "features" must be a combination of Go\\Aop\\Features constants, got %s.',
            is_int($features) ? (string) $features : get_debug_type($features),
        ));
    }

    public static function containerClassNotFound(mixed $containerClass): self
    {
        return new self(sprintf(
            'Container class %s does not exist.',
            is_string($containerClass) ? '"' . $containerClass . '"' : get_debug_type($containerClass),
        ));
    }

    public static function containerClassNotAspectContainer(string $containerClass): self
    {
        return new self(sprintf('Container class "%s" must extend %s.', $containerClass, AspectContainer::class));
    }

    public static function invalidLazyServiceId(string $id): self
    {
        return new self("Lazy service id must be a valid class name, \"{$id}\" given");
    }

    public static function cacheDirectoryNotCreatable(string $cacheDir, string $cacheRootDir): self
    {
        return new self(
            "Can not create a directory {$cacheDir} for the cache.\n"
            . "                            Parent directory {$cacheRootDir} is not writable or not exist.",
        );
    }

    public static function cacheDirectoryNotWritable(string $cacheDir): self
    {
        return new self("Cache directory {$cacheDir} is not writable");
    }

    public static function cacheWarmerWithoutCacheDir(): self
    {
        return new self('Cache warmer require the `cacheDir` options to be configured');
    }

    public static function pathOutsideRoot(string $path, string $rootDirectory): self
    {
        return new self(sprintf('Path %s is not in %s', $path, $rootDirectory));
    }

    public static function loaderArgumentNotString(): self
    {
        return new self('Loader argument must be a string');
    }

    public static function invalidLoaderPath(string $loader): self
    {
        return new self("Invalid loader path: {$loader}");
    }

    public static function nativeLazyObjectsRequired(string $entityClass, string $propertyName): self
    {
        return new self(sprintf(
            'Mapped property %s::$%s of a woven entity is intercepted by an aspect, which requires '
            . 'Doctrine native lazy objects: call $configuration->enableNativeLazyObjects(true).',
            $entityClass,
            $propertyName,
        ));
    }

    public static function entityDirectoryNotFound(string $directory): self
    {
        return new self(sprintf('Entity directory "%s" does not exist.', $directory));
    }
}
