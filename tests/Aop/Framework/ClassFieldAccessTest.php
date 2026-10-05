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
        $this->expectException(\Go\Aop\AspectException::class);
        $this->expectExceptionMessage('Typed property Go\Aop\Framework\ClassFieldAccessTest::$classField must not be accessed before initialization');
        $this->classField->read($this);
    }

    public function testReadInvocationWithoutBackedValueReturnsAroundAdviceResult(): void
    {
        $around     = new AroundInterceptor(static fn(): string => 'lazy value');
        $classField = new ClassFieldAccess([$around], self::class, 'classField');

        $this->assertSame('lazy value', $classField->read($this));
    }

    public function testGetValueThrowsWhenPropertyIsNotInitialized(): void
    {
        $this->classField->write($this, 'updated');

        try {
            $this->classField->getValue();
            $this->fail('An uninitialized property has no value');
        } catch (\Go\Aop\AspectException $exception) {
            $this->assertSame('Property classField is not initialized yet', $exception->getMessage());
        }
    }

    public function testWriteInvocationWithoutBackedValueDoesNotFail(): void
    {
        $result = $this->classField->write($this, 'updated');

        $this->assertSame('updated', $result);
    }

    public function testReadInvocationWithBackedValueReturnsOriginalValue(): void
    {
        $result = $this->classField->read($this, 'original');

        $this->assertSame('original', $result);
        $this->assertSame('original', $this->classField->getValue());
    }

    public function testReadInvocationWithNullValueReturnsNull(): void
    {
        $before     = new BeforeInterceptor(static function (\Go\Aop\Intercept\FieldAccess $fieldAccess): void {
            self::assertNull($fieldAccess->getValue());
        });
        $classField = new ClassFieldAccess([$before], self::class, 'classField');

        $this->assertNull($classField->read($this, null));
    }

    public function testWriteInvocationWithNullValuesReturnsNull(): void
    {
        $before     = new BeforeInterceptor(static function (\Go\Aop\Intercept\FieldAccess $fieldAccess): void {
            self::assertNull($fieldAccess->getValue());
            self::assertNull($fieldAccess->getValueToSet());
        });
        $classField = new ClassFieldAccess([$before], self::class, 'classField');

        $this->assertNull($classField->write($this, null, null));
    }

    public function testReadByReferenceReturnsReferenceToTheGivenValue(): void
    {
        $items = [1, 2];
        $value = &$this->classField->readByReference($this, $items);
        self::assertIsArray($value);
        $value[] = 3;

        $this->assertSame([1, 2, 3], $items);
    }

    public function testReadByReferenceReleasesTheGivenValueAfterAccess(): void
    {
        $firstItems  = [1];
        $secondItems = [2];
        $this->classField->readByReference($this, $firstItems);
        $this->classField->readByReference($this, $secondItems);
        $this->classField->read($this, [3]);

        $this->assertSame([1], $firstItems);
        $this->assertSame([2], $secondItems);
    }

    public function testGetAccessTypeReturnsTypeUsedDuringInvocation(): void
    {
        $this->classField->read($this, 'foo');

        $this->assertSame(FieldAccessType::Read, $this->classField->getAccessType());
    }

    public function testGetValueToSetReturnsNewValueForWriteAccess(): void
    {
        $this->classField->write($this, 'updated-value');

        $this->assertSame('updated-value', $this->classField->getValueToSet());
    }

    public function testGetValueToSetThrowsForReadAccessType(): void
    {
        $this->classField->read($this, 'foo');

        $this->expectException(\Go\Aop\AspectException::class);
        $this->expectExceptionMessage('Value to set is not available for READ access type');
        $this->classField->getValueToSet();
    }

    public function testGetThisReturnsBoundInstance(): void
    {
        $this->classField->read($this, 'foo');

        $this->assertSame($this, $this->classField->getThis());
    }

    public function testIsDynamicReturnsTrue(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the declared return type)
        $this->assertTrue($this->classField->isDynamic());
    }

    public function testGetScopeReturnsClassOfBoundInstance(): void
    {
        $this->classField->read($this, 'foo');

        $this->assertSame(self::class, $this->classField->getScope());
    }

    public function testToStringDescribesReadAccess(): void
    {
        $this->classField->read($this, 'foo');

        $this->assertSame(
            sprintf('get(%s->classField)', self::class),
            (string) $this->classField,
        );
    }

    public function testToStringDescribesWriteAccess(): void
    {
        $this->classField->write($this, 'foo');

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
        $result     = $classField->read($this, 'intercepted');

        $this->assertSame('intercepted', $result);
        $this->assertSame([FieldAccessType::Read], $calls);
    }

    public function testNestedAccessFromAdviceRestoresOuterState(): void
    {
        $other    = new self('other');
        $observed = [];
        $around   = new AroundInterceptor(function (ClassFieldAccess $access) use (&$observed, $other): mixed {
            if ($access->getThis() === $this) {
                // The advice reads the same property of another object through the shared joinpoint
                $observed['nested'] = $access->read($other, 'other value');
                $observed['this']   = $access->getThis() === $this;
                $observed['value']  = $access->getValue();
            }

            return $access->proceed();
        });
        $fieldAccess = new ClassFieldAccess([$around], self::class, 'classField');

        $result = $fieldAccess->read($this, 'outer value');

        $this->assertSame('outer value', $result);
        $this->assertSame(['nested' => 'other value', 'this' => true, 'value' => 'outer value'], $observed);
    }

    public function testNestedWriteFromAdviceRestoresOuterValues(): void
    {
        $other       = new self('other');
        $around      = new AroundInterceptor(function (ClassFieldAccess $access) use ($other): mixed {
            if ($access->getThis() === $this) {
                $access->write($other, 'other new value');
            }

            return $access->proceed();
        });
        $fieldAccess = new ClassFieldAccess([$around], self::class, 'classField');

        $result = $fieldAccess->write($this, 'outer new value', 'outer original value');

        $this->assertSame('outer new value', $result);
        $this->assertSame('outer new value', $fieldAccess->getValueToSet());
        $this->assertSame('outer original value', $fieldAccess->getValue());
    }

    public function testNestedReadByReferenceFromAdviceRestoresOuterBinding(): void
    {
        $other       = new self('other');
        $otherItems  = ['other'];
        $around      = new AroundInterceptor(function (ClassFieldAccess $access) use ($other, &$otherItems): mixed {
            if ($access->getThis() === $this) {
                // The advice reads the same array property of another object through the shared joinpoint
                $nestedItems = &$access->readByReference($other, $otherItems);
                self::assertIsArray($nestedItems);
                $nestedItems[] = 'nested';
            }

            return $access->proceed();
        });
        $fieldAccess = new ClassFieldAccess([$around], self::class, 'classField');

        $items = ['outer'];
        $value = &$fieldAccess->readByReference($this, $items);
        self::assertIsArray($value);
        $value[] = 'appended';

        $this->assertSame(['outer', 'appended'], $items);
        $this->assertSame(['other', 'nested'], $otherItems);
    }
}
