<?php
declare(strict_types = 1);

namespace Go\Aop\Framework;

class AbstractMethodInvocationTest extends \PHPUnit_Framework_TestCase
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

    public function testInvocationReturnsMethod()
    {
        $this->assertEquals(__CLASS__, $this->invocation->getMethod()->class);
        $this->assertEquals('setUp', $this->invocation->getMethod()->name);
    }

    public function testStaticPartEqualsToReflectionMethod()
    {
        $this->assertInstanceOf('ReflectionMethod', $this->invocation->getStaticPart());
    }
}
