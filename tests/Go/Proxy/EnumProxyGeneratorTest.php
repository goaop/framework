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

namespace Go\Proxy;

use Go\Proxy\Part\JoinPointPropertyGenerator;
use Go\Stubs\StubBackedEnum;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for EnumProxyGenerator — the generator used when a PHP enum
 * has applicable AOP advices.
 *
 * Like TraitProxyGenerator, EnumProxyGenerator does NOT use a class-level
 * $__joinPoints property (PHP enums cannot have properties). Instead it uses
 * per-method static $__joinPoint caching and calls EnumProxyGenerator::getJoinPoint().
 * There is also no injectJoinPoints() tail call in the generated output.
 */
class EnumProxyGeneratorTest extends TestCase
{
    /**
     * A proxy for an intercepted instance method on an enum must:
     * - declare an enum (not a class or trait)
     * - alias the intercepted method as private __aop__<method>
     * - override the method with a per-method static joinpoint dispatch body
     * - call EnumProxyGenerator::getJoinPoint (not ClassProxyGenerator::injectJoinPoints)
     * - dispatch via __invoke($this, ...) for instance methods
     */
    public function testGenerateProxyEnumMethod(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $traitName       = 'Go\\Stubs\\StubBackedEnum__AopProxied';
        $classAdvices    = [
            'method' => [
                'label' => ['advisor.StubBackedEnum->label'],
            ],
        ];

        $generator = new EnumProxyGenerator($reflectionClass, $traitName, $classAdvices, false);
        $output    = "<?php\n" . $generator->generate();

        // Must emit an enum, not a class or trait
        $this->assertStringContainsString('enum StubBackedEnum', $output);
        $this->assertStringNotContainsString('class StubBackedEnum', $output);
        $this->assertStringNotContainsString('trait StubBackedEnum', $output);

        // Must include \Go\Aop\Proxy interface
        $this->assertStringContainsString('\Go\Aop\Proxy', $output);

        // Trait use block with alias must be present
        $this->assertStringContainsString('StubBackedEnum__AopProxied', $output);
        $this->assertStringContainsString('__aop__label', $output);

        // Per-method static joinpoint caching
        $this->assertStringContainsString('static $__joinPoint', $output);
        $this->assertStringContainsString('EnumProxyGenerator::getJoinPoint', $output);

        // Correct join point type and method name
        $this->assertStringContainsString("'method'", $output);
        $this->assertStringContainsString("'label'", $output);

        // Instance method dispatch: $this as the first argument
        $this->assertStringContainsString('__invoke($this', $output);
    }

    /**
     * A proxy for an intercepted static method on an enum must dispatch via static::class.
     */
    public function testGenerateProxyEnumWithStaticMethod(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $traitName       = 'Go\\Stubs\\StubBackedEnum__AopProxied';
        $classAdvices    = [
            'static' => [
                'fromLabel' => ['advisor.StubBackedEnum->fromLabel'],
            ],
        ];

        $generator = new EnumProxyGenerator($reflectionClass, $traitName, $classAdvices, false);
        $output    = "<?php\n" . $generator->generate();

        $this->assertStringContainsString('__aop__fromLabel', $output);
        $this->assertStringContainsString("'static'", $output);
        $this->assertStringContainsString("'fromLabel'", $output);

        // Static dispatch: static::class as the first argument
        $this->assertStringContainsString('static::class', $output);
        $this->assertStringContainsString('__invoke(static::class', $output);
    }

    /**
     * Enum cases from the original enum must be re-declared in the proxy enum.
     * Cases cannot live in traits, so they are copied directly to the proxy.
     */
    public function testGeneratePreservesEnumCases(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $classAdvices    = [
            'method' => ['label' => ['advisor']],
        ];

        $generator = new EnumProxyGenerator($reflectionClass, 'Go\\Stubs\\StubBackedEnum__AopProxied', $classAdvices, false);
        $output    = "<?php\n" . $generator->generate();

        $this->assertStringContainsString("case Active = 'active'", $output);
        $this->assertStringContainsString("case Inactive = 'inactive'", $output);
    }

    /**
     * The backed type (`: string`) must be preserved in the proxy enum declaration.
     */
    public function testGenerateBackedEnumPreservesType(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $classAdvices    = [
            'method' => ['label' => ['advisor']],
        ];

        $generator = new EnumProxyGenerator($reflectionClass, 'Go\\Stubs\\StubBackedEnum__AopProxied', $classAdvices, false);
        $output    = "<?php\n" . $generator->generate();

        // The backed type must appear in the enum declaration
        $this->assertMatchesRegularExpression('/enum\s+StubBackedEnum\s*:\s*string/', $output);
    }

    /**
     * EnumProxyGenerator must NOT emit injectJoinPoints or $__joinPoints.
     * PHP enums cannot have properties; the per-method static variable pattern is used instead.
     */
    public function testGenerateDoesNotEmitClassProxyMechanism(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $classAdvices    = [
            'method' => ['label' => ['advisor']],
        ];

        $generator = new EnumProxyGenerator($reflectionClass, 'Go\\Stubs\\StubBackedEnum__AopProxied', $classAdvices, false);
        $output    = $generator->generate();

        $this->assertStringNotContainsString('injectJoinPoints', $output);
        $this->assertStringNotContainsString(JoinPointPropertyGenerator::NAME, $output);
        $this->assertStringNotContainsString('$__joinPoints', $output);
    }

    /**
     * UnitEnum and BackedEnum must NOT appear in the proxy enum's implements clause.
     *
     * These are built-in PHP interfaces applied automatically to any `enum` declaration.
     * Listing them explicitly in a namespaced file resolves them as e.g. Ns\UnitEnum
     * instead of the global \UnitEnum, causing a fatal "Interface not found" error.
     * \Go\Aop\Proxy must still be present.
     */
    public function testGenerateDoesNotIncludeBuiltinEnumInterfaces(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $classAdvices    = [
            'method' => ['label' => ['advisor']],
        ];

        $generator = new EnumProxyGenerator($reflectionClass, 'Go\\Stubs\\StubBackedEnum__AopProxied', $classAdvices, false);
        $output    = "<?php\n" . $generator->generate();

        // Extract the implements clause from the enum declaration line and check it directly.
        // We cannot do a plain assertStringNotContains('BackedEnum') because 'BackedEnum' is
        // also a substring of the stub class name 'StubBackedEnum__AopProxied'.
        preg_match('/^enum\s+\w+\s*(?::\s*\w+\s*)?implements\s+([^{]+)/m', $output, $matches);
        $implementsClause = $matches[1] ?? '';

        $this->assertStringNotContainsString('UnitEnum', $implementsClause);
        $this->assertStringNotContainsString('BackedEnum', $implementsClause);
        $this->assertStringContainsString('\Go\Aop\Proxy', $implementsClause);
    }

    /**
     * Built-in enum methods (cases, from, tryFrom) must be filtered out and never intercepted.
     * They are synthesised by PHP and cannot be aliased via trait use blocks.
     */
    public function testGenerateFiltersOutBuiltinEnumMethods(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $classAdvices    = [
            'method' => [
                'label' => ['advisor'],
                'cases' => ['advisor'],       // built-in, must be ignored
                'from'  => ['advisor'],       // built-in, must be ignored
            ],
            'static' => [
                'tryFrom' => ['advisor'],     // built-in, must be ignored
            ],
        ];

        $generator = new EnumProxyGenerator($reflectionClass, 'Go\\Stubs\\StubBackedEnum__AopProxied', $classAdvices, false);
        $output    = "<?php\n" . $generator->generate();

        // label is intercepted; built-ins are not
        $this->assertStringContainsString('__aop__label', $output);
        $this->assertStringNotContainsString('__aop__cases', $output);
        $this->assertStringNotContainsString('__aop__from', $output);
        $this->assertStringNotContainsString('__aop__tryFrom', $output);
    }
}
