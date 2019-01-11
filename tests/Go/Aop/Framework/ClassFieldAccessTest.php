<?php
declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\Support\AnnotationAccess;

class ClassFieldAccessTest extends \PHPUnit\Framework\TestCase
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
        $this->assertInstanceOf(AnnotationAccess::class, $this->classField->getField());
    }
}
