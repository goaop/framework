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

use Go\TestUtils\JoinPointsExtractor;
use PHPUnit_Framework_Constraint as Constraint;
use ReflectionClass;
use Go\Instrument\PathResolver;

/**
 * Asserts that join point exists for given class.
 */
class JoinPointExistsConstraint extends Constraint
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
        if (!$other instanceof JoinPoint) {
            throw new \InvalidArgumentException(sprintf('Expected instance of "%s", got "%s".', JoinPoint::class, is_object($other) ? get_class($other) : gettype($other)));
        }

        $joinPoints = JoinPointsExtractor::extractJoinPoints($this->getPathToProxy($other->getClass()));

        $access = $other->isStatic() ? 'static' : 'method';

        if (!isset($joinPoints[$access])) {
            return false;
        }

        if (!isset($joinPoints[$access][$other->getMethod()])) {
            return false;
        }

        $exists     = false;
        $validIndex = (($index = $other->getIndex()) === null);

        foreach ($joinPoints[$access][$other->getMethod()] as $position => $expression) {
            if ($other->getJoinPoint() === $expression) {
                $exists = true;

                if (null !== $index && $position === $index) {
                    $validIndex = true;
                }

                break;
            }
        }

        return $exists && $validIndex;
    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        return 'join point exists.';
    }

    /**
     * Get path to proxied class.
     *
     * @param string $class Full qualified class name which is subject of weaving
     *
     * @return string Path to proxy class.
     */
    private function getPathToProxy($class)
    {
        $filename = (new ReflectionClass($class))->getFileName();
        $suffix   = substr($filename, strlen(PathResolver::realpath($this->configuration['appDir'])));

        return $this->configuration['cacheDir'] . '/_proxies' . $suffix;
    }
}
