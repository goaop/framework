<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer\Weaving;

use Go\Aop\Framework\GeneratedInterceptor;
use Go\Core\AspectContainer;

/**
 * Names of the members of a class-like whose declarations the weaving moves from the woven trait to the proxy
 *
 * Decouples the source editing from the layout of the advice list: the trait converter only needs to know which
 * methods get a trait alias and which properties are re-declared by the proxy.
 *
 * @internal
 */
final readonly class InterceptedMembers
{
    private function __construct(
        /** @var list<string> Intercepted dynamic and static methods, aliased in the proxy */
        public array $methodNames,
        /** @var array<string, int> Intercepted properties as keys, re-declared by the proxy */
        private array $propertyNames,
    ) {}

    /**
     * @param array<string, array<string, list<string|GeneratedInterceptor>>> $advices Sorted advices of the class
     */
    public static function fromAdvices(array $advices): self
    {
        return new self(
            [
                ...array_keys($advices[AspectContainer::METHOD_PREFIX] ?? []),
                ...array_keys($advices[AspectContainer::STATIC_METHOD_PREFIX] ?? []),
            ],
            array_flip(array_keys($advices[AspectContainer::PROPERTY_PREFIX] ?? [])),
        );
    }

    /**
     * Checks whether any property is intercepted
     */
    public function hasProperties(): bool
    {
        return $this->propertyNames !== [];
    }

    /**
     * Checks whether the property is intercepted
     */
    public function hasProperty(string $propertyName): bool
    {
        return isset($this->propertyNames[$propertyName]);
    }
}
