<?php

declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\Support\AnnotationAccess;
use PHPUnit\Framework\TestCase;

class ClassFieldAccessTest extends TestCase
{
    protected ClassFieldAccess $classField;

    public function setUp(): void
    {
        $this->classField = new ClassFieldAccess([], self::class, 'classField');
    }

    public function testClassFiledReturnsProperty(): void
    {
        $this->assertEquals(self::class, $this->classField->getField()->class);
        $this->assertEquals('classField', $this->classField->getField()->name);
    }

    public function testProvidesAccessToAnnotations(): void
    {
        $this->assertInstanceOf(AnnotationAccess::class, $this->classField->getField());
    }
}
