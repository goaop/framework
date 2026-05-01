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
        // Woven trait file uses a PSR-4 layout: <cacheDir>/<Namespace/ClassName__AopProxied>.php
        $wovenRelativePath = str_replace('\\', DIRECTORY_SEPARATOR, $other) . '__AopProxied.php';
        $transformedFileExists = file_exists($this->configuration['cacheDir'] . DIRECTORY_SEPARATOR . $wovenRelativePath);

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
