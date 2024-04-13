<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Stubs\FirstStatic;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class NotPointcutTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('logicCases')]
    public function testMatches(Pointcut $first, bool $expected): void
    {
        $filter = new NotPointcut($first);
        $result = $filter->matches(
            new ReflectionClass(self::class),
            new ReflectionMethod(self::class, __FUNCTION__)
        );
        $this->assertSame($expected, $result);
    }

    public static function logicCases(): \Generator
    {
        $true  = new TruePointcut();
        $false = new NotPointcut($true);

        yield [$false, true];
        yield [$true, false];
    }

    public function testAlwaysMatchesWithoutReflectorInstance(): void
    {
        $truePointcut  = new TruePointcut();
        $falsePointcut = new NotPointcut($truePointcut);

        $reflectionClass = new ReflectionClass(FirstStatic::class);
        $this->assertTrue($falsePointcut->matches($reflectionClass));
    }
}
