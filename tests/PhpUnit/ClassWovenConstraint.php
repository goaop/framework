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

use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionClass;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * Asserts that class is not woven.
 */
final class ClassWovenConstraint extends Constraint
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
        $filename = (new ReflectionClass($other))->getFileName();
        $suffix   = substr($filename, strlen(PathResolver::realpath($this->configuration['appDir'])));

        $transformedFileExists = file_exists($this->configuration['cacheDir'] . $suffix);

        // Proxy files use a PSR-4 layout: <cacheDir>/<Namespace/ClassName>.php
        $proxyRelativePath = str_replace('\\', DIRECTORY_SEPARATOR, $other) . '.php';
        $proxyFileExists   = file_exists($this->configuration['cacheDir'] . DIRECTORY_SEPARATOR . $proxyRelativePath);

        // if any of files is missing, assert has to fail
        return $transformedFileExists && $proxyFileExists;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return 'is woven class.';
    }
}
