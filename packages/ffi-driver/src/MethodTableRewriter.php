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

namespace Go\Driver\Ffi;

use Go\Driver\Ffi\Exception\UnsupportedJoinpointException;
use Go\Aop\Framework\AbstractMethodInvocation;
use Go\Aop\Proxy;
use ReflectionException;
use ReflectionMethod;
use ZEngine\Reflection\ReflectionClass as EngineClass;

/**
 * Rewires the advised methods of a loaded class to the dispatcher bodies of its donor trait
 *
 * The whole engine contract is z-engine's public reflection API:
 *  - a method the class declares itself keeps its body under the private alias
 *    `<method>OriginalAlias` and gets the dispatcher body swapped in place
 *    (`ReflectionMethod::redefine($donorMethod, preserveAs: ...)`): the published entry pointer,
 *    the constructor/magic slots, subclass buckets and warmed inline caches all stay valid, the
 *    dispatcher calls `$this-><method>OriginalAlias(...)` / `self::...` like a trait-based proxy;
 *  - a method the class only inherits gets an own override adopting the dispatcher body
 *    (`ReflectionClass::addMethod($method, $donorMethod)`), whose dispatcher reaches the
 *    original through `parent::<method>(...)`;
 *  - the rewired class is marked with the `Go\Aop\Proxy` interface
 *    (`ReflectionClass::addInterfaces()`), like a generated proxy class: code asking
 *    `instanceof Proxy` to tell a woven object (the Doctrine bridge, fluent-interface guards)
 *    gets the same answer from both drivers.
 *
 * An opcache-shared class is copied out of shared memory by z-engine on the first mutation;
 * the class reflection is therefore re-resolved from the class table before every method.
 *
 * @internal Driver service, not a public extension point
 */
final class MethodTableRewriter
{
    /**
     * @param class-string $className      Loaded class to rewire
     * @param class-string $donorClassName Loaded donor trait declaring the dispatcher methods
     * @param list<string> $methods        Advised method names (any case)
     *
     * @throws UnsupportedJoinpointException When the engine refuses a mutation (the class stays
     *                                       partially woven: the methods rewired before the refusal keep dispatching)
     */
    public function rewire(string $className, string $donorClassName, array $methods): void
    {
        $donor = new EngineClass($donorClassName);
        foreach ($methods as $methodName) {
            // Re-resolved per method: the first mutation of an opcache-shared class repoints the
            // class-table bucket at a writable per-process copy
            $class = EngineClass::fromClassTable($className);
            if ($class === null) {
                throw UnsupportedJoinpointException::classNotInEngineClassTable($className);
            }
            $native = new ReflectionMethod($className, $methodName);
            try {
                $donorMethod = $donor->getMethod($methodName);
                if ($native->getDeclaringClass()->getName() === $className) {
                    $class->getMethod($methodName)->redefine(
                        $donorMethod,
                        preserveAs: $methodName . AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX,
                    );
                } else {
                    $class->addMethod($methodName, $donorMethod);
                }
            } catch (ReflectionException $refusal) {
                throw UnsupportedJoinpointException::engineRefusal($className, $refusal);
            }
        }

        // Re-resolved once more: the first mutation above may have copied the class out of shared memory
        $class = EngineClass::fromClassTable($className);
        if ($class !== null && !in_array(Proxy::class, $class->getInterfaceNames(), true)) {
            try {
                $class->addInterfaces(Proxy::class);
            } catch (ReflectionException $refusal) {
                throw UnsupportedJoinpointException::engineRefusal($className, $refusal);
            }
        }
    }
}
