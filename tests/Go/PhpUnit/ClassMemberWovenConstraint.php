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
 * Asserts that class member is woven for given class.
 */
final class ClassMemberWovenConstraint extends Constraint
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
            throw new \InvalidArgumentException(sprintf('Expected instance of "%s", got "%s".', ClassAdvisorIdentifier::class, is_object($other) ? get_class($other) : gettype($other)));
        }

        $reflectionClass         = ProxyClassReflectionHelper::createReflectionClass($other->getClass(), $this->configuration);
        $wovenAdvisorIdentifiers = $reflectionClass->getStaticPropertyValue('__joinPoints', null);
        $target                  = $other->getTarget();

        if (null === $wovenAdvisorIdentifiers) { // there are no advisor identifiers
            return false;
        }

        if (!isset($wovenAdvisorIdentifiers[$target])) {
            return false;
        }

        if (!isset($wovenAdvisorIdentifiers[$target][$other->getSubject()])) {
            return false;
        }

        if (null === $other->getAdvisorIdentifier()) {
            return true; // if advisor identifier is not specified, that means that any matches, so weaving exists.
        }

        $index        = $other->getIndex();
        $advisorIndex = array_search($other->getAdvisorIdentifier(), $wovenAdvisorIdentifiers[$target][$other->getSubject()], true);
        $isIndexValid = ($index === null) || ($advisorIndex === $index);

        return $advisorIndex !== false && $isIndexValid;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return 'class member woven.';
    }
}
