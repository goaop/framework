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

use \PHPUnit_Framework_Constraint as Constraint;
use \ReflectionClass;
use Go\Instrument\PathResolver;

/**
 * Asserts that class is not woven.
 */
class ClassWovenConstraint extends Constraint
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

        return
            file_exists($this->configuration['cacheDir'] . $suffix)
            &&
            file_exists($this->configuration['cacheDir'] . '/_proxies' . $suffix);
    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        return 'is woven class.';
    }
}
