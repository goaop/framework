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
use Go\Stubs\TraitAliasProxied;
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
     * - call TraitProxyGenerator::getJoinPoint (not ClassProxyGenerator::injectJoinPoints)
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
        $this->assertStringContainsString('TraitProxyGenerator::getJoinPoint', $output);

        // Correct join point type and method name
        $this->assertStringContainsString("'method'", $output);
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

        // Static join point type
        $this->assertStringContainsString("'static'", $output);
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

        // Three separate getJoinPoint calls (one per intercepted method)
        $this->assertSame(3, substr_count($output, 'TraitProxyGenerator::getJoinPoint'));
    }

    /**
     * TraitProxyGenerator::generate() must NOT emit injectJoinPoints or $__joinPoints:
     * those are ClassProxyGenerator concerns only. Traits use per-method static caching.
     */
    public function testGenerateDoesNotEmitClassProxyMechanism(): void
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
        $this->assertStringNotContainsString(JoinPointPropertyGenerator::NAME, $output);
        $this->assertStringNotContainsString('ClassProxyGenerator', $output);
    }

    /**
     * TraitProxyGenerator uses per-method static $__joinPoint caching (not a shared array).
     * This is the key structural difference from ClassProxyGenerator's $__joinPoints array.
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

        // Lazy init pattern: check once, create once
        $this->assertStringContainsString('static $__joinPoint', $output);
        $this->assertStringContainsString('if ($__joinPoint === null)', $output);

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
        $this->assertStringContainsString('static $__joinPoint;', $output);
        $this->assertStringContainsString("TraitProxyGenerator::getJoinPoint(__CLASS__, 'prop', 'public'", $output);
        $this->assertStringContainsString('FieldAccessType::READ', $output);
        $this->assertStringContainsString('FieldAccessType::WRITE', $output);
        $this->assertStringNotContainsString('$__joinPoints[', $output);
    }
}
