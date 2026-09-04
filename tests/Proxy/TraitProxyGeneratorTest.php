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

use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
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
 *  - uses the renamed original trait (FooOriginal)
 *  - aliases each intercepted method as private <method>Original
 *  - overrides each intercepted method with a per-method static joinpoint dispatch
 */
class TraitProxyGeneratorTest extends TestCase
{
    /**
     * A trait proxy for an intercepted instance method must:
     * - declare a trait (not a class)
     * - alias the intercepted method as private <method>Original
     * - override the method with a per-method static $__joinPoint body
     * - call InterceptorInjector (not ClassProxyGenerator::injectJoinPoints)
     * - dispatch via __invoke($this, ...) for instance methods
     */
    public function testGenerateTraitWithInterceptedInstanceMethod(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'method' => [
                'publicMethod' => [self::testAdvice('advisor.TraitAliasProxied->publicMethod')],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxiedOriginal',
            $traitAdvices,
        );

        $output = "<?php\n" . $generator->generate();

        // Must emit a trait, not a class
        $this->assertStringContainsString('trait TraitAliasProxied', $output);
        $this->assertStringNotContainsString('class TraitAliasProxied', $output);

        // Parent trait and private alias must appear in the use block
        $this->assertStringContainsString('TraitAliasProxiedOriginal', $output);
        $this->assertStringContainsString('publicMethodOriginal', $output);

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
                'staticPublicMethod' => [self::testAdvice('advisor.TraitAliasProxied->staticPublicMethod')],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxiedOriginal',
            $traitAdvices,
        );

        $output = "<?php\n" . $generator->generate();

        $this->assertStringContainsString('trait TraitAliasProxied', $output);
        $this->assertStringContainsString('staticPublicMethodOriginal', $output);

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
                'publicMethod'    => [self::testAdvice('advisor1')],
                'protectedMethod' => [self::testAdvice('advisor2')],
            ],
            'static' => [
                'staticPublicMethod' => [self::testAdvice('advisor3')],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxiedOriginal',
            $traitAdvices,
        );

        $output = "<?php\n" . $generator->generate();

        $this->assertStringContainsString('publicMethodOriginal', $output);
        $this->assertStringContainsString('protectedMethodOriginal', $output);
        $this->assertStringContainsString('staticPublicMethodOriginal', $output);

        // Three separate injector calls (one per intercepted method)
        $this->assertSame(2, substr_count($output, 'InterceptorInjector::forMethod'));
        $this->assertSame(1, substr_count($output, 'InterceptorInjector::forStaticMethod'));
        $this->assertStringContainsString("'publicMethod'", $output);
        $this->assertStringContainsString("'protectedMethod'", $output);
        $this->assertStringContainsString("'staticPublicMethod'", $output);
    }

    /**
     * TraitProxyGenerator::generate() must NOT emit legacy injectJoinPoints or $__joinPoints
     * patterns. All proxy generators now use per-method static $__joinPoint caching.
     */
    public function testGenerateDoesNotEmitLegacyJoinPointMechanism(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'method' => ['publicMethod' => [self::testAdvice('advisor')]],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxiedOriginal',
            $traitAdvices,
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
            'method' => ['publicMethod' => [self::testAdvice('advisor')]],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxiedOriginal',
            $traitAdvices,
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
                'public' => [self::testAdvice('advisor.TraitAliasProxied->public')],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitAliasProxiedOriginal',
            $traitAdvices,
        );

        $output = "<?php\n" . $generator->generate();

        $this->assertStringContainsString('public int $public = 326 {', $output);
        $this->assertStringContainsString('static $__joinPoint = InterceptorInjector::forProperty', $output);
        $this->assertStringContainsString("InterceptorInjector::forProperty(", $output);
        $this->assertStringContainsString('FieldAccessType::READ', $output);
        $this->assertStringContainsString('FieldAccessType::WRITE', $output);
        $this->assertStringNotContainsString('$__joinPoints[', $output);
    }

    public function testGenerateTraitWithClassTypedPropertyUsesFullyQualifiedTypeInFieldAccessPhpDoc(): void
    {
        $reflectionTrait = new ReflectionClass(TraitWithClassTypedProperty::class);
        $traitAdvices    = [
            'prop' => [
                'privateProperty' => [self::testAdvice('advisor.TraitWithClassTypedProperty->privateProperty')],
            ],
        ];

        $generator = new TraitProxyGenerator(
            $reflectionTrait,
            'Go\\Stubs\\TraitWithClassTypedPropertyOriginal',
            $traitAdvices,
        );

        $output = "<?php\n" . $generator->generate();

        $this->assertStringContainsString(
            "/** @var FieldAccess<self, \\Exception> \$__joinPoint */",
            $output,
        );
    }

    /**
     * When the parent trait and the proxy trait share the same namespace, the generated use-block
     * must reference the parent trait by its short (unqualified) name, not the FQCN.
     */
    public function testTraitAdoptionUsesShortNameWhenSameNamespace(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'method' => [
                'publicMethod' => [self::testAdvice('advisor')],
            ],
        ];

        // Parent trait in the same namespace as the proxy trait (Go\Stubs)
        $parentTraitFqcn = 'Go\\Stubs\\TraitAliasProxiedOriginal';
        $generator       = new TraitProxyGenerator($reflectionTrait, $parentTraitFqcn, $traitAdvices);
        $output          = "<?php\n" . $generator->generate();

        // Must use the short (unqualified) parent trait name
        $this->assertStringContainsString('use TraitAliasProxiedOriginal {', $output);
        $this->assertStringContainsString('TraitAliasProxiedOriginal::publicMethod as private publicMethodOriginal', $output);
        $this->assertStringNotContainsString('\\Go\\Stubs\\TraitAliasProxiedOriginal', $output);
    }

    /**
     * When the parent trait is in a different namespace from the proxy trait, the generated
     * use-block must keep the FQCN so PHP can resolve the trait correctly.
     */
    public function testTraitAdoptionUsesFqcnWhenDifferentNamespace(): void
    {
        $reflectionTrait = new ReflectionClass(TraitAliasProxied::class);
        $traitAdvices    = [
            'method' => [
                'publicMethod' => [self::testAdvice('advisor')],
            ],
        ];

        // Parent trait in a different namespace from the proxy trait (proxy is in Go\Stubs)
        $parentTraitFqcn = 'Other\\Namespace\\TraitAliasProxiedOriginal';
        $generator       = new TraitProxyGenerator($reflectionTrait, $parentTraitFqcn, $traitAdvices);
        $output          = "<?php\n" . $generator->generate();

        // Must use the FQCN for the parent trait name
        $this->assertStringContainsString('use \\Other\\Namespace\\TraitAliasProxiedOriginal {', $output);
        $this->assertStringContainsString('\\Other\\Namespace\\TraitAliasProxiedOriginal::publicMethod as private publicMethodOriginal', $output);
        $this->assertStringNotContainsString('use TraitAliasProxiedOriginal {', $output);
    }

    private static function testAdvice(string $advisorId): GeneratedInterceptor
    {
        return GeneratedInterceptor::fromAdvice($advisorId, new BeforeInterceptor(static function (): void {}));
    }
}
