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

namespace Go\Functional;

use Go\Tests\TestProject\Application\BackedEnum;
use Go\Tests\TestProject\Application\SimpleEnum;

/**
 * Functional tests for PHP 8.1+ enum weaving.
 *
 * Verifies that:
 * - Both unit (pure) enums and backed enums are woven correctly.
 * - Instance methods and static methods are intercepted.
 * - Built-in enum methods (cases, from, tryFrom) are never intercepted.
 * - Initialization joinpoints are never woven for enums.
 * - Enums with additional features (interface, trait, constant, attribute) work.
 */
class EnumWeavingTest extends BaseFunctionalTestCase
{
    /**
     * A pure unit enum with a method is woven when an applicable aspect exists.
     */
    public function testSimpleUnitEnumIsWoven(): void
    {
        $this->assertClassIsWoven(SimpleEnum::class);
    }

    /**
     * A backed string enum is woven when an applicable aspect exists.
     */
    public function testBackedStringEnumIsWoven(): void
    {
        $this->assertClassIsWoven(BackedEnum::class);
    }

    /**
     * An instance method on a unit enum is intercepted by the matching pointcut.
     */
    public function testSimpleEnumInstanceMethodIsIntercepted(): void
    {
        $this->assertMethodWoven(
            SimpleEnum::class,
            'doSomething',
            'Go\\Tests\\TestProject\\Aspect\\EnumMethodAspect->afterSimpleEnumMethod'
        );
    }

    /**
     * A static method on a unit enum is intercepted by the matching pointcut.
     */
    public function testSimpleEnumStaticMethodIsIntercepted(): void
    {
        $this->assertStaticMethodWoven(
            SimpleEnum::class,
            'doSomethingStatic',
            'Go\\Tests\\TestProject\\Aspect\\EnumMethodAspect->afterSimpleEnumStaticMethod'
        );
    }

    /**
     * Instance methods on a backed enum (with interface + trait + constant + attribute) are intercepted.
     */
    public function testBackedEnumInstanceMethodsAreIntercepted(): void
    {
        $this->assertMethodWoven(
            BackedEnum::class,
            'doSomething',
            'Go\\Tests\\TestProject\\Aspect\\EnumMethodAspect->afterBackedEnumMethod'
        );
        $this->assertMethodWoven(
            BackedEnum::class,
            'label',
            'Go\\Tests\\TestProject\\Aspect\\EnumMethodAspect->afterBackedEnumMethod'
        );
    }

    /**
     * A static method on a backed enum is intercepted by the matching pointcut.
     */
    public function testBackedEnumStaticMethodIsIntercepted(): void
    {
        $this->assertStaticMethodWoven(
            BackedEnum::class,
            'doSomethingStatic',
            'Go\\Tests\\TestProject\\Aspect\\EnumMethodAspect->afterBackedEnumStaticMethod'
        );
    }

    /**
     * Built-in PHP enum methods (cases, from, tryFrom) are never intercepted.
     * They are synthesised by PHP and cannot be aliased via trait use.
     */
    public function testBuiltinEnumMethodsAreNeverIntercepted(): void
    {
        $this->assertMethodNotWoven(BackedEnum::class, 'cases');
        $this->assertMethodNotWoven(BackedEnum::class, 'from');
        $this->assertMethodNotWoven(BackedEnum::class, 'tryFrom');
    }

    /**
     * Initialization joinpoints are never woven for enums.
     * PHP enums cannot be instantiated with `new`, so allowing initialization
     * interception would cause "Cannot instantiate enum X" fatal errors.
     */
    public function testEnumInitializationIsNeverWoven(): void
    {
        $this->assertClassInitializationNotWoven(SimpleEnum::class);
        $this->assertClassInitializationNotWoven(BackedEnum::class);
    }
}
