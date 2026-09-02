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

use Go\Core\AspectContainer;
use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionClass;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * @phpstan-import-type ProjectConfiguration from ProxyClassReflectionHelper
 *
 * Asserts that class is not woven.
 */
final class ClassIsNotWovenConstraint extends Constraint
{
    /** @var ProjectConfiguration */
    private array $configuration;

    /**
     * @param ProjectConfiguration $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(mixed $other): bool
    {
        assert(is_string($other) || is_object($other));
        $filename = (new ReflectionClass($other))->getFileName();
        assert(is_string($filename));
        $appDir = PathResolver::realpath($this->configuration['appDir']);
        assert(is_string($appDir));
        $suffix = substr($filename, strlen($appDir));

        $proxyFileExists       = file_exists($this->configuration['cacheDir'] . $suffix);
        $transformedFileExists = file_exists($this->configuration['cacheDir'] . str_replace('.php', AspectContainer::AOP_PROXIED_SUFFIX . '.php', $suffix));

        // if any of files exists, assert has to fail
        return !$transformedFileExists && !$proxyFileExists;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return 'is not woven class.';
    }
}
