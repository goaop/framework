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
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class OrPointcutTest extends TestCase
{
    /**
     * Tests that filter combined different kinds of filters
     */
    public function testKindIsCombined(): void
    {
        $first = $this->createMock(Pointcut::class);
        $first
            ->method('getKind')
            ->willReturn(Pointcut::KIND_METHOD | Pointcut::KIND_PROPERTY);

        $second = $this->createMock(Pointcut::class);
        $second
            ->method('getKind')
            ->willReturn(Pointcut::KIND_METHOD | Pointcut::KIND_FUNCTION);

        $filter   = new OrPointcut($first, $second);
        $expected = Pointcut::KIND_METHOD | Pointcut::KIND_FUNCTION | Pointcut::KIND_PROPERTY;
        $this->assertEquals($expected, $filter->getKind());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('logicCases')]
    public function testMatches(Pointcut $first, Pointcut $second, bool $expected): void
    {
        $filter = new OrPointcut($first, $second);
        $result = $filter->matches(
            new ReflectionClass(self::class),
            new ReflectionMethod(self::class, __FUNCTION__)
            /* anything */
        );
        $this->assertSame($expected, $result);
    }

    public static function logicCases(): array
    {
        $true  = new TruePointcut();
        $false = new NotPointcut($true);
        return [
            [$false, $false, false],
            [$false, $true, true],
            [$true, $false, true],
            [$true, $true, true]
        ];
    }
}
