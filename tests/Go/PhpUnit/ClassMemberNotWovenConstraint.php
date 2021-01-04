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

use InvalidArgumentException;
use PHPUnit\Framework\Constraint\Constraint;

/**
 *Asserts that class member is not woven for given class.
 */
final class ClassMemberNotWovenConstraint extends Constraint
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
        if (!$other instanceof ClassAdvisorIdentifier) {
            throw new InvalidArgumentException(sprintf('Expected instance of "%s", got "%s".', ClassAdvisorIdentifier::class, is_object($other) ? get_class($other) : gettype($other)));
        }

        $reflectionClass         = ProxyClassReflectionHelper::createReflectionClass($other->getClass(), $this->configuration);
        $wovenAdvisorIdentifiers = $reflectionClass->getStaticPropertyValue('__joinPoints', null);
        $target                  = $other->getTarget();

        if (null === $wovenAdvisorIdentifiers) { // there are no advisor identifiers
            return true;
        }

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
    public function toString(): string
    {
        return 'class member not woven.';
    }
}
