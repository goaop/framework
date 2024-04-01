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
}
