<?php

declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\Support\AnnotationAccess;
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
        $this->assertEquals(self::class, $this->invocation->getMethod()->class);
        $this->assertEquals('setUp', $this->invocation->getMethod()->name);
    }

    public function testProvidesAccessToAnnotations(): void
    {
        $this->assertInstanceOf(AnnotationAccess::class, $this->invocation->getMethod());
    }

    public function testInstanceIsInitialized(): void
    {
        $this->expectNotToPerformAssertions();
        $o = new class extends AbstractMethodInvocation {

            protected static string $propertyName = 'scope';
            public function __construct()
            {
                parent::__construct([new AroundInterceptor(function () {})], '\Go\Aop\Framework\AbstractMethodInvocationTest', 'testInstanceIsInitialized');
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
                return 'testScope';
            }

            public function proceed()
            {
                if ($this->level < 3) {
                    $this->__invoke('testInstance');
                }
            }
        };

        $o->__invoke('testInstance');
    }
}
