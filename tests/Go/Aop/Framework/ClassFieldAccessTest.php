<?php
declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\Support\AnnotationAccess;
use PHPUnit\Framework\TestCase;

class ClassFieldAccessTest extends TestCase
{
    /**
     * @var ClassFieldAccess
     */
    protected $classField;

    public function setUp(): void
    {
        $this->classField = new ClassFieldAccess(__CLASS__, 'classField', []);
    }

    public function testClassFiledReturnsProperty(): void
    {
        $this->assertEquals(__CLASS__, $this->classField->getField()->class);
        $this->assertEquals('classField', $this->classField->getField()->name);
    }

    public function testProvidesAccessToAnnotations(): void
    {
        $this->assertInstanceOf(AnnotationAccess::class, $this->classField->getField());
    }
}
