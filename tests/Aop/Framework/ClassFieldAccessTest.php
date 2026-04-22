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

    public function testReadInvocationWithoutBackedValueDoesNotFail(): void
    {
        $value = $this->classField->__invoke($this, FieldAccessType::READ);

        $this->assertNull($value);
    }

    public function testWriteInvocationWithoutBackedValueDoesNotFail(): void
    {
        $newValue = 'updated';
        $result = $this->classField->__invoke($this, FieldAccessType::WRITE, $newValue);

        $this->assertSame('updated', $result);
    }
}
