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

namespace Go\Aop\Exception;

use RuntimeException;

/**
 * Failure while a joinpoint is executed: invalid field access, advice lookup or invocation state
 */
final class InvocationException extends RuntimeException implements ExceptionInterface
{
    public static function propertyNotInitialized(string $className, string $fieldName): self
    {
        return new self(sprintf('Property %s::$%s is not initialized yet', $className, $fieldName));
    }

    public static function typedPropertyNotInitialized(string $className, string $fieldName): self
    {
        return new self(sprintf('Typed property %s::$%s must not be accessed before initialization', $className, $fieldName));
    }

    public static function valueToSetUnavailable(): self
    {
        return new self('Value to set is not available for READ access type');
    }

    public static function advisorWithoutClosure(string $advisorId): self
    {
        return new self("Advisor {$advisorId} does not expose a closure advice");
    }

    public static function closureScopeUnresolvable(): self
    {
        return new self('Cannot determine the scope class of the closure');
    }
}
