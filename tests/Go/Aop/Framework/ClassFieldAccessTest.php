<?php
declare(strict_types = 1);

namespace Go\Aop\Framework;

class ClassFieldAccessTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClassFieldAccess
     */
    protected $classField;

    public function setUp()
    {
        $this->classField = new ClassFieldAccess(__CLASS__, 'classField', []);
    }

    public function testClassFiledReturnsProperty()
    {
        $this->assertEquals(__CLASS__, $this->classField->getField()->class);
        $this->assertEquals('classField', $this->classField->getField()->name);
    }

    public function testStaticPartEqualsToReflectionMethod()
    {
        $this->assertInstanceOf('ReflectionProperty', $this->classField->getStaticPart());
    }

    public function testProvidesAccessToAnnotations()
    {
        $field = $this->classField->getField();

        $this->assertTrue(method_exists($field, 'getAnnotation'));
        $this->assertTrue(method_exists($field, 'getAnnotations'));
    }
}
