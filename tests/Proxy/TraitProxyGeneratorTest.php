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

use Go\Stubs\TraitAliasProxied;
use Go\Stubs\TraitWithClassTypedProperty;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for TraitProxyGenerator — the generator used when a PHP trait
 * (not a class) has applicable AOP advices.
 *
 * Unlike ClassProxyGenerator which generates a proxy *class*, TraitProxyGenerator
 * generates a child *trait* that:
 *  - uses the renamed original trait (Foo__AopProxied)
 *  - aliases each intercepted method as private __aop__<method>
 *  - overrides each intercepted method with a per-method static joinpoint dispatch
 */
class TraitProxyGeneratorTest extends TestCase
{
    /**
     * A trait proxy for an intercepted instance method must:
     * - declare a trait (not a class)
     * - alias the intercepted method as private __aop__<method>
     * - override the method with a per-method static $__joinPoint body
     * - call InterceptorInjector (not ClassProxyGenerator::injectJoinPoints)
     * - dispatch via __invoke($this, ...) for instance methods
     */
    public function testGenerateTraitWithInterceptedInstanceMethod(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'method' => [
                'publicMethod' => ['advisor.TraitAliasProxied->publicMethod'],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxied__AopProxied',
            $traitAdvices,
            false
        );

        $output = "<?php\n" . $generator->generate();

        // Must emit a trait, not a class
        $this->assertStringContainsString('trait TraitAliasProxied', $output);
        $this->assertStringNotContainsString('class TraitAliasProxied', $output);

        // Parent trait and private alias must appear in the use block
        $this->assertStringContainsString('TraitAliasProxied__AopProxied', $output);
        $this->assertStringContainsString('__aop__publicMethod', $output);

        // Method body must use per-method static joinpoint caching
        $this->assertStringContainsString('static $__joinPoint', $output);
        $this->assertStringContainsString('InterceptorInjector::forMethod', $output);

        // Correct method name
        $this->assertStringContainsString("'publicMethod'", $output);

        // Instance method dispatch: $this as the first argument
        $this->assertStringContainsString('__invoke($this', $output);
    }

    /**
     * A trait proxy for an intercepted static method must dispatch via static::class.
     */
    public function testGenerateTraitWithInterceptedStaticMethod(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'static' => [
                'staticPublicMethod' => ['advisor.TraitAliasProxied->staticPublicMethod'],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxied__AopProxied',
            $traitAdvices,
            false
        );

        $output = "<?php\n" . $generator->generate();

        $this->assertStringContainsString('trait TraitAliasProxied', $output);
        $this->assertStringContainsString('__aop__staticPublicMethod', $output);

        $this->assertStringContainsString("'staticPublicMethod'", $output);

        // Static dispatch: static::class as the first argument
        $this->assertStringContainsString('static::class', $output);
        $this->assertStringContainsString('__invoke(static::class', $output);
    }

    /**
     * Multiple methods (instance and static) are all aliased and overridden.
     */
    public function testGenerateTraitWithMultipleInterceptedMethods(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'method' => [
                'publicMethod'    => ['advisor1'],
                'protectedMethod' => ['advisor2'],
            ],
            'static' => [
                'staticPublicMethod' => ['advisor3'],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxied__AopProxied',
            $traitAdvices,
            false
        );

        $output = "<?php\n" . $generator->generate();

        $this->assertStringContainsString('__aop__publicMethod', $output);
        $this->assertStringContainsString('__aop__protectedMethod', $output);
        $this->assertStringContainsString('__aop__staticPublicMethod', $output);

        // Three separate injector calls (one per intercepted method)
        $this->assertSame(2, substr_count($output, 'InterceptorInjector::forMethod'));
        $this->assertSame(1, substr_count($output, 'InterceptorInjector::forStaticMethod'));
        $this->assertStringContainsString("forMethod(self::class, 'publicMethod'", $output);
        $this->assertStringContainsString("forMethod(self::class, 'protectedMethod'", $output);
        $this->assertStringContainsString("forStaticMethod(self::class, 'staticPublicMethod'", $output);
    }

    /**
     * TraitProxyGenerator::generate() must NOT emit legacy injectJoinPoints or $__joinPoints
     * patterns. All proxy generators now use per-method static $__joinPoint caching.
     */
    public function testGenerateDoesNotEmitLegacyJoinPointMechanism(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'method' => ['publicMethod' => ['advisor']],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxied__AopProxied',
            $traitAdvices,
            false
        );

        $output = $generator->generate();

        $this->assertStringNotContainsString('injectJoinPoints', $output);
        $this->assertStringNotContainsString('__joinPoints', $output);
        $this->assertStringNotContainsString('ClassProxyGenerator', $output);
    }

    /**
     * TraitProxyGenerator uses per-method static $__joinPoint caching via InterceptorInjector,
     * the same pattern used by all proxy generators.
     */
    public function testMethodBodyUsesPerMethodStaticCaching(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'method' => ['publicMethod' => ['advisor']],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxied__AopProxied',
            $traitAdvices,
            false
        );

        $output = $generator->generate();

        // Direct static init pattern: no null check needed
        $this->assertStringContainsString('static $__joinPoint', $output);
        $this->assertStringNotContainsString('if ($__joinPoint === null)', $output);

        // ClassProxyGenerator-style shared array must NOT appear
        $this->assertStringNotContainsString('$__joinPoints[', $output);
    }

    public function testGenerateTraitWithInterceptedProperty(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'prop' => [
                'public' => ['advisor.TraitAliasProxied->public'],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxied__AopProxied',
            $traitAdvices,
            false
        );

        $output = "<?php\n" . $generator->generate();

        $this->assertStringContainsString('public int $public = 326 {', $output);
        $this->assertStringContainsString('static $__joinPoint = \\Go\\Aop\\Framework\\InterceptorInjector::forProperty', $output);
        $this->assertStringContainsString("InterceptorInjector::forProperty(self::class, 'public'", $output);
        $this->assertStringContainsString('FieldAccessType::READ', $output);
        $this->assertStringContainsString('FieldAccessType::WRITE', $output);
        $this->assertStringNotContainsString('$__joinPoints[', $output);
    }

    public function testGenerateTraitWithClassTypedPropertyUsesFullyQualifiedTypeInFieldAccessPhpDoc(): void
    {
        $reflectionTrait = new ReflectionClass(TraitWithClassTypedProperty::class);
        $traitAdvices    = [
            'prop' => [
                'privateProperty' => ['advisor.TraitWithClassTypedProperty->privateProperty'],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitWithClassTypedProperty__AopProxied',
            $traitAdvices,
            false
        );

        $output = "<?php\n" . $generator->generate();

        $this->assertStringContainsString(
            "/** @var \\Go\\Aop\\Intercept\\FieldAccess<self, \\Exception> \$__joinPoint */",
            $output
        );
    }
}
