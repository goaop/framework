<?php

declare(strict_types=1);

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

    public function testReadInvocationWithBackedValueReturnsOriginalValue(): void
    {
        $originalValue = 'original';
        $result = $this->classField->__invoke($this, FieldAccessType::READ, $originalValue);

        $this->assertSame('original', $result);
        $this->assertSame('original', $this->classField->getValue());
    }

    public function testGetAccessTypeReturnsTypeUsedDuringInvocation(): void
    {
        $value = 'foo';
        $this->classField->__invoke($this, FieldAccessType::READ, $value);

        $this->assertSame(FieldAccessType::READ, $this->classField->getAccessType());
    }

    public function testGetValueToSetReturnsNewValueForWriteAccess(): void
    {
        $newValue = 'updated-value';
        $this->classField->__invoke($this, FieldAccessType::WRITE, $newValue);

        $this->assertSame('updated-value', $this->classField->getValueToSet());
    }

    public function testGetValueToSetThrowsForReadAccessType(): void
    {
        $value = 'foo';
        $this->classField->__invoke($this, FieldAccessType::READ, $value);

        $this->expectException(\Go\Aop\AspectException::class);
        $this->expectExceptionMessage('Value to set is not available for READ access type');
        $this->classField->getValueToSet();
    }

    public function testGetThisReturnsBoundInstance(): void
    {
        $value = 'foo';
        $this->classField->__invoke($this, FieldAccessType::READ, $value);

        $this->assertSame($this, $this->classField->getThis());
    }

    public function testIsDynamicReturnsTrue(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the declared return type)
        $this->assertTrue($this->classField->isDynamic());
    }

    public function testGetScopeReturnsClassOfBoundInstance(): void
    {
        $value = 'foo';
        $this->classField->__invoke($this, FieldAccessType::READ, $value);

        $this->assertSame(self::class, $this->classField->getScope());
    }

    public function testToStringDescribesReadAccess(): void
    {
        $value = 'foo';
        $this->classField->__invoke($this, FieldAccessType::READ, $value);

        $this->assertSame(
            sprintf('get(%s->classField)', self::class),
            (string) $this->classField,
        );
    }

    public function testToStringDescribesWriteAccess(): void
    {
        $newValue = 'foo';
        $this->classField->__invoke($this, FieldAccessType::WRITE, $newValue);

        $this->assertSame(
            sprintf('set(%s->classField)', self::class),
            (string) $this->classField,
        );
    }

    public function testProceedInvokesInterceptorChainBeforeReturningPropertyValue(): void
    {
        $calls   = [];
        $advice  = new AroundInterceptor(function (\Go\Aop\Intercept\FieldAccess $fieldAccess) use (&$calls): mixed {
            $calls[] = $fieldAccess->getAccessType();

            return $fieldAccess->proceed();
        });

        $classField = new ClassFieldAccess([$advice], self::class, 'classField');
        $value      = 'intercepted';
        $result     = $classField->__invoke($this, FieldAccessType::READ, $value);

        $this->assertSame('intercepted', $result);
        $this->assertSame([FieldAccessType::READ], $calls);
    }
}
