<?php
declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\Support\AnnotationAccess;
use PHPUnit\Framework\TestCase;

class AbstractMethodInvocationTest extends TestCase
{
    /**
     * @var AbstractMethodInvocation
     */
    protected $invocation;

    public function setUp()
    {
        $this->invocation = $this->getMockForAbstractClass(
            AbstractMethodInvocation::class,
            [__CLASS__, __FUNCTION__, []]
        );
    }

    public function testInvocationReturnsMethod(): void
    {
        $this->assertEquals(__CLASS__, $this->invocation->getMethod()->class);
        $this->assertEquals('setUp', $this->invocation->getMethod()->name);
    }

    public function testProvidesAccessToAnnotations(): void
    {
        $this->assertInstanceOf(AnnotationAccess::class, $this->invocation->getMethod());
    }
}
