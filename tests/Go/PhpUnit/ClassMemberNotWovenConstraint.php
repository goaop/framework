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

use Go\TestUtils\AdvisorIdentifiersExtractor;
use PHPUnit_Framework_Constraint as Constraint;
use ReflectionClass;
use Go\Instrument\PathResolver;

/**
 *Asserts that class member is not woven for given class.
 */
class ClassMemberNotWovenConstraint extends Constraint
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
        if (!$other instanceof ClassAdvisorIdentifier) {
            throw new \InvalidArgumentException(sprintf('Expected instance of "%s", got "%s".', ClassAdvisorIdentifier::class, is_object($other) ? get_class($other) : gettype($other)));
        }

        $wovenAdvisorIdentifiers = $this->getWovenAdvisorIdentifiers($other->getClass());

        $target = $other->getTarget();

        if (!isset($wovenAdvisorIdentifiers[$target])) {
            return true;
        }

        if (!isset($wovenAdvisorIdentifiers[$target][$other->getSubject()])) {
            return true;
        }

        if (null === $other->getAdvisorIdentifier()) {
            return false; // if advisor identifier is not specified, that means that any matches, so weaving exists.
        }

        return !in_array($other->getAdvisorIdentifier(), $wovenAdvisorIdentifiers[$target][$other->getSubject()], true);
    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        return 'join point does not exists.';
    }

    /**
     * Get woven advisor identifiers.
     *
     * @param string $class
     * @return array
     */
    private function getWovenAdvisorIdentifiers($class)
    {
        ClassLocator::initialize($this->configuration);

        $advisorIdentifiers = (new ReflectionClass($class))->getStaticPropertyValue('__joinPoints')->getValue();

        ClassLocator::restore();

        return $advisorIdentifiers;
    }
}
