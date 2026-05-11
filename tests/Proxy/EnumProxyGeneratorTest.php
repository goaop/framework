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

use Go\Stubs\StubBackedEnum;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for EnumProxyGenerator — the generator used when a PHP enum
 * has applicable AOP advices.
 *
 * EnumProxyGenerator uses per-method static $__joinPoint caching and calls
 * InterceptorInjector, the same pattern used by all proxy generators.
 * PHP enums cannot have properties, so there is no class-level state.
 */
class EnumProxyGeneratorTest extends TestCase
{
    /**
     * A proxy for an intercepted instance method on an enum must:
     * - declare an enum (not a class or trait)
     * - alias the intercepted method as private __aop__<method>
     * - override the method with a per-method static joinpoint dispatch body
     * - call InterceptorInjector for per-method joinpoint resolution
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
        $this->assertStringContainsString('InterceptorInjector::forMethod', $output);

        // Correct method name
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
     * EnumProxyGenerator must NOT emit legacy injectJoinPoints or $__joinPoints patterns.
     * All proxy generators now use per-method static $__joinPoint caching.
     */
    public function testGenerateDoesNotEmitLegacyJoinPointMechanism(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $classAdvices    = [
            'method' => ['label' => ['advisor']],
        ];

        $generator = new EnumProxyGenerator($reflectionClass, 'Go\\Stubs\\StubBackedEnum__AopProxied', $classAdvices, false);
        $output    = $generator->generate();

        $this->assertStringNotContainsString('injectJoinPoints', $output);
        $this->assertStringNotContainsString('__joinPoints', $output);
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
     * When the trait and the proxy enum share the same namespace, the generated use-block
     * must reference the trait by its short (unqualified) name, not the FQCN.
     */
    public function testTraitAdoptionUsesShortNameWhenSameNamespace(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $classAdvices    = [
            'method' => [
                'label' => ['advisor'],
            ],
        ];

        // Trait in the same namespace as the proxy enum (Go\Stubs)
        $traitFqcn = 'Go\\Stubs\\StubBackedEnum__AopProxied';
        $generator = new EnumProxyGenerator($reflectionClass, $traitFqcn, $classAdvices, false);
        $output    = "<?php\n" . $generator->generate();

        // Must use the short (unqualified) trait name
        $this->assertStringContainsString('use StubBackedEnum__AopProxied {', $output);
        $this->assertStringContainsString('StubBackedEnum__AopProxied::label as private __aop__label', $output);
        $this->assertStringNotContainsString('\\Go\\Stubs\\StubBackedEnum__AopProxied', $output);
    }

    /**
     * When the trait is in a different namespace from the proxy enum, the generated use-block
     * must keep the FQCN so PHP can resolve the trait correctly.
     */
    public function testTraitAdoptionUsesFqcnWhenDifferentNamespace(): void
    {
        $reflectionClass = new ReflectionClass(StubBackedEnum::class);
        $classAdvices    = [
            'method' => [
                'label' => ['advisor'],
            ],
        ];

        // Trait in a different namespace from the proxy enum (proxy is in Go\Stubs)
        $traitFqcn = 'Other\\Namespace\\StubBackedEnum__AopProxied';
        $generator = new EnumProxyGenerator($reflectionClass, $traitFqcn, $classAdvices, false);
        $output    = "<?php\n" . $generator->generate();

        // Must use the FQCN for the trait name
        $this->assertStringContainsString('use \\Other\\Namespace\\StubBackedEnum__AopProxied {', $output);
        $this->assertStringContainsString('\\Other\\Namespace\\StubBackedEnum__AopProxied::label as private __aop__label', $output);
        $this->assertStringNotContainsString('use StubBackedEnum__AopProxied {', $output);
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
