<?php

namespace Go\Aop\Pointcut;

use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Pointcut;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Stubs\First;
use Go\Tests\TestProject\Application\ClassWithComplexTypes;
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
        $unionMethod        = new ReflectionMethod(ClassWithComplexTypes::class, 'publicMethodWithUnionTypeReturn');
        $intersectionMethod = new ReflectionMethod(ClassWithComplexTypes::class, 'publicMethodWithIntersectionTypeReturn');
        $dnfMethod          = new ReflectionMethod(ClassWithComplexTypes::class, 'publicMethodWithDNFTypeReturn');
        $nullableMethod     = new ReflectionMethod(CachePathManager::class, 'queryCacheState');

        return [
            'Exact match (int)' => ['int', new ReflectionFunction('strlen'), true],
            'Star match (bool)' => ['b*l', new ReflectionMethod(ReturnTypePointcut::class, 'matches'), true],
            'Question mark is not a wildcard' => ['?nt', new ReflectionFunction('strlen'), false],
            'No match (int)' => ['array', new ReflectionFunction('strlen'), false],

            // Union return types (Exception|Closure)
            'Union exact match' => ['Exception|Closure', $unionMethod, true],
            'Union match is order-insensitive' => ['Closure|Exception', $unionMethod, true],
            'Union member matched by single-type pattern' => ['Exception', $unionMethod, true],
            'Union member matched by single-type wildcard' => ['Exc*', $unionMethod, true],
            'Union member with wildcard in composite pattern' => ['Exc*|Closure', $unionMethod, true],
            'Union pattern with extra member does not match' => ['Exception|Closure|null', $unionMethod, false],
            'Union pattern with missing member does not match' => ['Exception|Iterator', $unionMethod, false],

            // Intersection return types (Exception&Countable)
            'Intersection exact match' => ['Exception&Countable', $intersectionMethod, true],
            'Intersection match is order-insensitive' => ['Countable&Exception', $intersectionMethod, true],
            'Intersection member matched by single-type pattern' => ['Countable', $intersectionMethod, true],
            'Intersection not matched by union pattern' => ['Exception|Countable', $intersectionMethod, false],

            // DNF return types (Iterator|(Exception&Countable))
            'DNF exact match' => ['Iterator|(Exception&Countable)', $dnfMethod, true],
            'DNF match is group-order-insensitive' => ['(Exception&Countable)|Iterator', $dnfMethod, true],
            'DNF matches parenthesis-free pattern' => ['Exception&Countable|Iterator', $dnfMethod, true],
            'DNF group member matched by single-type pattern' => ['Countable', $dnfMethod, true],
            'DNF flattened union pattern does not match' => ['Iterator|Exception|Countable', $dnfMethod, false],

            // Nullable return types (?array is equivalent to array|null)
            'Nullable actual matched by plain pattern' => ['array', $nullableMethod, true],
            'Nullable actual matched by union with null' => ['array|null', $nullableMethod, true],
            'Nullable actual matched by null pattern' => ['null', $nullableMethod, true],
            'Nullable actual not matched by other union' => ['array|false', $nullableMethod, false],
            'Nullable pattern matches nullable actual' => ['?array', $nullableMethod, true],
            'Nullable pattern does not match plain actual' => ['?int', new ReflectionFunction('strlen'), false],
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