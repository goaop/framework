<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\PhpUnit;

use Composer\Autoload\ClassLoader;
use Go\Core\AspectContainer;
use Go\Instrument\PathResolver;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * Asserts that class is not woven.
 */
final class ClassIsNotWovenConstraint extends Constraint
{
    private array $configuration;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function matches($other): bool
    {
        $filename = $this->findOriginalSourceFile($other);
        if ($filename === false) {
            return true;
        }

        // Cache mirrors the original directory structure.
        // Woven trait file uses the source-relative path with an AopProxied suffix.
        $appDir     = PathResolver::realpath($this->configuration['appDir']);
        $suffix     = substr($filename, strlen($appDir));
        $wovenPath  = $this->configuration['cacheDir'] . substr($suffix, 0, -4) . AspectContainer::AOP_PROXIED_SUFFIX . '.php';

        // Proxy file follows FQCN-based path (mirrors PSR-4/PSR-0 namespace structure)
        $proxyRelativePath = str_replace('\\', DIRECTORY_SEPARATOR, $other) . '.php';
        $proxyFileExists   = file_exists($this->configuration['cacheDir'] . DIRECTORY_SEPARATOR . $proxyRelativePath);

        // if any of files exists, assert has to fail
        return !file_exists($wovenPath) && !$proxyFileExists;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return 'is not woven class.';
    }

    /**
     * Returns the original source file path for the given class via Composer's ClassLoader,
     * regardless of whether the class is already loaded in memory (possibly as an AOP proxy).
     */
    private function findOriginalSourceFile(string $className): string|false
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && isset($autoloader[0]) && $autoloader[0] instanceof ClassLoader) {
                $file = $autoloader[0]->findFile($className);
                if ($file !== false) {
                    return realpath($file) ?: $file;
                }
            }
        }

        return false;
    }
}
