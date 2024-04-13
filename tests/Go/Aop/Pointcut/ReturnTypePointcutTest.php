<?php

namespace Go\Aop\Pointcut;

use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Pointcut;
use Go\Stubs\First;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

final class ReturnTypePointcutTest extends TestCase
{
    /**
     * @param (string&non-empty-string) $typeName
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('returnTypeMatchesDataProvider')]
    public function testMatches(string $typeName, ReflectionFunction|ReflectionMethod $reflector, bool $expectedMatch): void
    {
        $pointcut = new ReturnTypePointcut($typeName);
        $context  = new ReflectionClass(ReturnTypePointcutTest::class);
        $result   = $pointcut->matches($context, $reflector);

        self::assertSame($expectedMatch, $result);
    }

    public static function returnTypeMatchesDataProvider(): array
    {
        return [
            'Exact match (int)' => ['int', new ReflectionFunction('strlen'), true],
            'Star match (bool)' => ['b*l', new ReflectionMethod(ReturnTypePointcut::class, 'matches'), true],
            'Question match (int)' => ['?nt', new ReflectionMethod(ReturnTypePointcut::class, 'getKind'), true],
            'No match (int)' => ['array', new ReflectionFunction('strlen'), false],
        ];
    }

    public function testAlwaysMatchesWithoutReflectorInstance(): void
    {
        $pointcut = new ReturnTypePointcut('void');

        $reflectionClass = new ReflectionClass(self::class);
        $this->assertTrue($pointcut->matches($reflectionClass));
    }

    public function testNeverMatchesForReflectionProperties(): void
    {
        $pointcut = new ReturnTypePointcut('int');
        $reflectionClass = new ReflectionClass(First::class);

        $this->assertFalse($pointcut->matches(
            $reflectionClass,
            $reflectionClass->getProperty('public')
        ));
    }

    public function testNeverMatchesWithoutReturnType(): void
    {
        $pointcut = new ReturnTypePointcut('int');
        $reflectionClass = new ReflectionClass(Joinpoint::class);

        $this->assertFalse($pointcut->matches(
            $reflectionClass,
            $reflectionClass->getMethod('proceed')
        ));
    }

    public function testThrowsInvalidArgumentExceptionForEmptyType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReturnTypePointcut('');
    }

    public function testGetKind(): void
    {
        $pointcut = new ReturnTypePointcut('test');

        $this->assertTrue(($pointcut->getKind() & Pointcut::KIND_FUNCTION) > 0, 'Pointcut should be for functions');
        $this->assertTrue(($pointcut->getKind() & Pointcut::KIND_METHOD) > 0, 'Pointcut should be for methods');
    }
}