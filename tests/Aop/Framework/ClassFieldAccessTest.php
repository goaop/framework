<?php

declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\Intercept\FieldAccessType;
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

    public function testReadInvocationWithoutBackedValueFail(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Typed property Go\Aop\Framework\ClassFieldAccess::$value must not be accessed before initialization');
        $this->classField->__invoke($this, FieldAccessType::READ);
    }

    public function testWriteInvocationWithoutBackedValueDoesNotFail(): void
    {
        $newValue = 'updated';
        $result = $this->classField->__invoke($this, FieldAccessType::WRITE, $newValue);

        $this->assertSame('updated', $result);
    }
}
