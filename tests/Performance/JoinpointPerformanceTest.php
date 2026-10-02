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

namespace Go\Performance;

use Closure;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\DynamicTraitAliasMethodInvocation;
use Go\Aop\Framework\ReflectionConstructorInvocation;
use Go\Aop\Framework\ReflectionFunctionInvocation;
use Go\Aop\Framework\StaticTraitAliasMethodInvocation;
use Go\Aop\Intercept\FieldAccessType;
use Go\Aop\Intercept\Joinpoint;
use Go\Stubs\TraitAliasProxy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Performance of the core joinpoint classes, which run on every intercepted call
 *
 * Each case runs a joinpoint `__invoke()` in a loop and compares the time per call with a plain call doing the
 * same work without AOP. The ratio is checked against a budget, so the result does not depend on the machine.
 * Excluded from the default run: `composer test:performance`. Run it before and after every change of
 * `__invoke()`/`proceed()` in src/Aop/Framework and keep the ratios from growing.
 */
#[Group('performance')]
final class JoinpointPerformanceTest extends TestCase
{
    private const int ITERATIONS = 100_000;

    private const int ROUNDS = 7;

    /**
     * @return iterable<string, array{Closure(): mixed, Closure(): mixed, float}>
     */
    public static function joinpoints(): iterable
    {
        $around = [new AroundInterceptor(static fn(Joinpoint $joinpoint): mixed => $joinpoint->proceed())];

        foreach (['no advice' => [], 'around advice' => $around] as $adviceName => $advices) {
            $instance       = new TraitAliasProxy();
            $methodCallable = $instance->getCallableFor('publicMethod');
            $method         = new DynamicTraitAliasMethodInvocation($advices, TraitAliasProxy::class, 'publicMethod', $methodCallable);
            yield "method, {$adviceName}" => [
                static fn(): mixed => $methodCallable(),
                static fn(): mixed => $method($instance),
                $advices === [] ? 14.0 : 25.0,
            ];

            $staticCallable = TraitAliasProxy::getStaticCallableFor('staticPublicMethod');
            $staticMethod   = new StaticTraitAliasMethodInvocation($advices, TraitAliasProxy::class, 'staticPublicMethod', $staticCallable);
            yield "static method, {$adviceName}" => [
                static fn(): mixed => $staticCallable(),
                static fn(): mixed => $staticMethod(TraitAliasProxy::class),
                $advices === [] ? 25.0 : 30.0,
            ];

            $function = new ReflectionFunctionInvocation($advices, 'strlen', \strlen(...));
            yield "function, {$adviceName}" => [
                static fn(): mixed => \strlen('value'),
                static fn(): mixed => $function(['value']),
                $advices === [] ? 20.0 : 30.0,
            ];

            $target      = new PerformanceTarget();
            $fieldAccess = new ClassFieldAccess($advices, PerformanceTarget::class, 'value');
            yield "field read, {$adviceName}" => [
                static fn(): mixed => $target->value,
                static function () use ($fieldAccess, $target): mixed {
                    $value = $target->value;

                    return $fieldAccess($target, FieldAccessType::READ, $value);
                },
                $advices === [] ? 40.0 : 55.0,
            ];
            yield "field write, {$adviceName}" => [
                static fn(): mixed => $target->value = 'new',
                static function () use ($fieldAccess, $target): mixed {
                    $newValue = 'new';
                    $value    = $target->value;

                    return $fieldAccess($target, FieldAccessType::WRITE, $newValue, $value);
                },
                $advices === [] ? 35.0 : 40.0,
            ];

            $constructor = new ReflectionConstructorInvocation($advices, PerformanceTarget::class);
            yield "constructor, {$adviceName}" => [
                static fn(): mixed => new PerformanceTarget(),
                static fn(): mixed => $constructor(),
                $advices === [] ? 10.0 : 16.0,
            ];
        }
    }

    /**
     * @param Closure(): mixed $plainCall     The same work without AOP
     * @param Closure(): mixed $joinpointCall Call through the joinpoint
     * @param float            $maxRatio      Budget: joinpoint time per call / plain time per call
     */
    #[DataProvider('joinpoints')]
    public function testJoinpointOverheadStaysWithinBudget(Closure $plainCall, Closure $joinpointCall, float $maxRatio): void
    {
        $plainTime     = self::measure($plainCall);
        $joinpointTime = self::measure($joinpointCall);
        $ratio         = $joinpointTime / $plainTime;

        fwrite(STDERR, sprintf(
            "\n%-32s plain %7.1f ns, joinpoint %7.1f ns, ratio %5.1f (budget %4.1f)",
            $this->dataName(),
            $plainTime,
            $joinpointTime,
            $ratio,
            $maxRatio,
        ));
        $this->assertLessThanOrEqual($maxRatio, $ratio);
    }

    /**
     * Returns the best time per call over several rounds, in nanoseconds
     *
     * @param Closure(): mixed $call
     */
    private static function measure(Closure $call): float
    {
        // Warm-up
        for ($i = 0; $i < 1_000; $i++) {
            $call();
        }
        $best = PHP_INT_MAX;
        for ($round = 0; $round < self::ROUNDS; $round++) {
            $start = hrtime(true);
            for ($i = 0; $i < self::ITERATIONS; $i++) {
                $call();
            }
            $best = min($best, hrtime(true) - $start);
        }

        return $best / self::ITERATIONS;
    }
}

/**
 * Target of the field access and constructor cases
 */
final class PerformanceTarget
{
    public string $value = 'initial';
}
