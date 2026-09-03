<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NativeLazyProxyTest extends TestCase
{
    public function testCreatesUninitializedProxyForCompatibleClass(): void
    {
        $constructed = false;
        $proxy = NativeLazyProxy::tryCreate(LazyProxyFixture::class, function () use (&$constructed): LazyProxyFixture {
            $constructed = true;

            return new LazyProxyFixture(42);
        });

        $this->assertInstanceOf(LazyProxyFixture::class, $proxy);
        $this->assertTrue((new ReflectionClass(LazyProxyFixture::class))->isUninitializedLazyObject($proxy));
        $this->assertFalse($constructed, 'Initializer must not run at proxy creation');

        // First real interaction runs the initializer
        $this->assertSame(42, $proxy->getValue());
        // @phpstan-ignore method.impossibleType (the flag is flipped by reference inside the initializer)
        $this->assertTrue($constructed);
    }

    public function testRefusesPropertylessClass(): void
    {
        // PHP creates lazy objects of property-less classes as already initialized,
        // so the initializer would never run - such classes must be reported unsupported
        $proxy = NativeLazyProxy::tryCreate(PropertylessFixture::class, fn(): PropertylessFixture => new PropertylessFixture());

        $this->assertNull($proxy);
        // Repeated attempts take the memoized path and stay refused
        $this->assertNull(NativeLazyProxy::tryCreate(PropertylessFixture::class, fn(): PropertylessFixture => new PropertylessFixture()));
    }

    public function testRefusesAbstractClass(): void
    {
        $this->assertNull(NativeLazyProxy::tryCreate(AbstractLazyFixture::class, fn(): object => new LazyProxyFixture(1)));
    }

    public function testRefusesEnum(): void
    {
        $this->assertNull(NativeLazyProxy::tryCreate(LazyEnumFixture::class, fn(): object => LazyEnumFixture::One));
    }

    public function testRefusesInternalClass(): void
    {
        $this->assertNull(NativeLazyProxy::tryCreate(ArrayObject::class, fn(): ArrayObject => new ArrayObject()));
    }

    public function testRefusesSubclassOfInternalClass(): void
    {
        $this->assertNull(NativeLazyProxy::tryCreate(InternalSubclassFixture::class, fn(): InternalSubclassFixture => new InternalSubclassFixture()));
    }

    public function testReadonlyClassSupportFollowsEngineCapabilities(): void
    {
        // Current engines (PHP 8.4.19+, 8.5) can make readonly classes lazy; the probe
        // asks the engine instead of hardcoding a version cutoff, so an engine without
        // that support would simply get the eager fallback (null) here
        $proxy = NativeLazyProxy::tryCreate(ReadonlyLazyFixture::class, fn(): ReadonlyLazyFixture => new ReadonlyLazyFixture(1));

        $this->assertInstanceOf(ReadonlyLazyFixture::class, $proxy);
        $this->assertTrue((new ReflectionClass(ReadonlyLazyFixture::class))->isUninitializedLazyObject($proxy));
        $this->assertSame(1, $proxy->getValue());
    }

    public function testTrustedCreateSkipsProbe(): void
    {
        $proxy = NativeLazyProxy::create(LazyProxyFixture::class, static fn(): LazyProxyFixture => new LazyProxyFixture(7));

        $this->assertTrue((new ReflectionClass(LazyProxyFixture::class))->isUninitializedLazyObject($proxy));
        $this->assertSame(7, $proxy->getValue());
    }
}

class LazyProxyFixture
{
    public function __construct(private readonly int $value) {}

    public function getValue(): int
    {
        return $this->value;
    }
}

class PropertylessFixture
{
    public function doNothing(): void {}
}

abstract class AbstractLazyFixture
{
    public int $property = 0;
}

enum LazyEnumFixture
{
    case One;
}

/**
 * @extends ArrayObject<int, mixed>
 */
class InternalSubclassFixture extends ArrayObject
{
    public int $property = 0;
}

readonly class ReadonlyLazyFixture
{
    public function __construct(private int $value) {}

    public function getValue(): int
    {
        return $this->value;
    }
}
