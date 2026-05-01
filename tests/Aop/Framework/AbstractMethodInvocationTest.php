<?php

declare(strict_types = 1);

namespace Go\Aop\Framework;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AbstractMethodInvocationTest extends TestCase
{
    protected AbstractMethodInvocation $invocation;

    public function setUp(): void
    {
        $this->invocation = $this->getMockBuilder(AbstractMethodInvocation::class)
            ->setConstructorArgs([[], self::class, __FUNCTION__, static fn() => null])
            ->onlyMethods(['proceed', 'isDynamic', 'getThis', 'getScope'])
            ->getMock();
    }

    public function testInvocationReturnsMethod(): void
    {
        $this->assertEquals(self::class, $this->invocation->getMethod()->class);
        $this->assertEquals('setUp', $this->invocation->getMethod()->name);
    }

    /**
     * @link https://github.com/goaop/framework/issues/481
     */
    public function testInstanceIsInitialized(): void
    {
        $o = new class extends AbstractMethodInvocation {

            public function __construct()
            {
                parent::__construct([new AroundInterceptor(function () {})], AbstractMethodInvocationTest::class, 'testInstanceIsInitialized', static fn() => null);
            }

            public function isDynamic(): bool
            {
                return false;
            }

            public function getThis(): ?object
            {
                return null;
            }

            public function getScope(): string
            {
                return self::class;
            }

            public function proceed(): string
            {
                return $this->reflectionMethod->getName();
            }
        };

        $result = $o->proceed();
        $this->assertEquals('testInstanceIsInitialized', $result);
    }
}
