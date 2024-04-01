<?php

declare(strict_types = 1);

namespace Go\Aop\Framework;

use PHPUnit\Framework\TestCase;

class AbstractMethodInvocationTest extends TestCase
{
    protected AbstractMethodInvocation $invocation;

    public function setUp(): void
    {
        $this->invocation = $this->getMockForAbstractClass(
            AbstractMethodInvocation::class,
            [[], self::class, __FUNCTION__]
        );
    }

    public function testInvocationReturnsMethod(): void
    {
        // AbstractMethodInvocation uses prototype methods to avoid hard-coded class sufixes
        $this->assertEquals(parent::class, $this->invocation->getMethod()->class);
        $this->assertEquals('setUp', $this->invocation->getMethod()->name);
    }

    /**
     * @link https://github.com/goaop/framework/issues/481
     */
    public function testInstanceIsInitialized(): void
    {
        $this->expectNotToPerformAssertions();

        $o = new class extends AbstractMethodInvocation {

            protected static string $propertyName = 'scope';

            /**
             * @var (string&class-string) Class name scope for static invocation
             */
            protected string $scope;

            public function __construct()
            {
                parent::__construct([new AroundInterceptor(function () {})], AbstractMethodInvocationTest::class, 'testInstanceIsInitialized');
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

            public function proceed(): void
            {
                if ($this->level < 3) {
                    $this->__invoke($this->scope);
                }
            }
        };

        $o->__invoke($o::class);
    }
}
