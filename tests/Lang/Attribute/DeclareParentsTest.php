<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Lang\Attribute;

use PHPUnit\Framework\TestCase;

class DeclareParentsTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        $attribute = new DeclareParents(
            'within(Example\Aspect\*)',
            DeclareParentsTestInterface::class,
            DeclareParentsTestTrait::class,
            5,
        );

        $this->assertSame('within(Example\Aspect\*)', $attribute->expression);
        $this->assertSame(DeclareParentsTestInterface::class, $attribute->interfaceName);
        $this->assertSame(DeclareParentsTestTrait::class, $attribute->traitName);
        $this->assertSame(5, $attribute->order);
    }

    public function testOrderDefaultsToZero(): void
    {
        $attribute = new DeclareParents(
            'within(Example\Aspect\*)',
            DeclareParentsTestInterface::class,
            DeclareParentsTestTrait::class,
        );

        $this->assertSame(0, $attribute->order);
    }

    public function testCanBeReadFromPropertyAttribute(): void
    {
        $reflectionProperty = new \ReflectionProperty(ClassWithDeclareParentsAttribute::class, 'someTrait');
        $attributes          = $reflectionProperty->getAttributes(DeclareParents::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check that newInstance() built the right attribute class)
        $this->assertInstanceOf(DeclareParents::class, $instance);
        $this->assertSame('within(Example\Aspect\*)', $instance->expression);
        $this->assertSame(DeclareParentsTestInterface::class, $instance->interfaceName);
        $this->assertSame(DeclareParentsTestTrait::class, $instance->traitName);
    }
}

trait DeclareParentsTestTrait
{
}

interface DeclareParentsTestInterface
{
}

final class DeclareParentsTestTraitConsumer
{
    use DeclareParentsTestTrait;
}

class ClassWithDeclareParentsAttribute
{
    #[DeclareParents('within(Example\Aspect\*)', DeclareParentsTestInterface::class, DeclareParentsTestTrait::class)]
    public mixed $someTrait = null;
}
