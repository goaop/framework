<?php
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
use PHPUnit_Framework_Constraint as Constraint;

/**
 * Asserts that class is not woven.
 */
final class ClassIsNotWovenConstraint extends Constraint
{
    /**
     * @var array
     */
    private $configuration;

    public function __construct(array $configuration)
    {
        parent::__construct();
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function matches($other)
    {
        $filename = (new ReflectionClass($other))->getFileName();
        $suffix   = substr($filename, strlen(PathResolver::realpath($this->configuration['appDir'])));

        $transformedFileExists = file_exists($this->configuration['cacheDir'] . $suffix);
        $proxyFileExists       = file_exists($this->configuration['cacheDir'] . '/_proxies' . $suffix);

        // if any of files exists, assert has to fail
        $classIsNotWoven = !$transformedFileExists && !$proxyFileExists;

        return $classIsNotWoven;
    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        return 'is not woven class.';
    }
}
