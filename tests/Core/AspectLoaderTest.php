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

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AspectLoaderTest extends TestCase
{
    public function testLoadReturnsEmptyArrayWithoutRegisteredLoaders(): void
    {
        $container = new Container();
        $loader    = new AspectLoader($container);

        $items = $loader->load(new ReflectionClass(AspectLoaderTestAspectOne::class));

        $this->assertSame([], $items);
    }

    public function testLoadCombinesItemsFromAllLoaderExtensions(): void
    {
        $container = new Container();

        $pointcutOne = $this->createMock(Pointcut::class);
        $pointcutTwo = $this->createMock(Pointcut::class);

        $extensionOne = $this->createMock(AspectLoaderExtension::class);
        $extensionOne->method('load')->willReturn(['item.one' => $pointcutOne]);

        $extensionTwo = $this->createMock(AspectLoaderExtension::class);
        $extensionTwo->method('load')->willReturn(['item.two' => $pointcutTwo]);

        $loader = new AspectLoader($container, $extensionOne, $extensionTwo);

        $items = $loader->load(new ReflectionClass(AspectLoaderTestAspectOne::class));

        $this->assertSame(
            ['item.one' => $pointcutOne, 'item.two' => $pointcutTwo],
            $items,
        );
    }

    public function testLoadKeepsFirstLoaderItemWhenKeysCollide(): void
    {
        $container = new Container();

        $pointcutFromFirst  = $this->createMock(Pointcut::class);
        $pointcutFromSecond = $this->createMock(Pointcut::class);

        $extensionOne = $this->createMock(AspectLoaderExtension::class);
        $extensionOne->method('load')->willReturn(['shared.key' => $pointcutFromFirst]);

        $extensionTwo = $this->createMock(AspectLoaderExtension::class);
        $extensionTwo->method('load')->willReturn(['shared.key' => $pointcutFromSecond]);

        $loader = new AspectLoader($container, $extensionOne, $extensionTwo);

        $items = $loader->load(new ReflectionClass(AspectLoaderTestAspectOne::class));

        // array union (+=) semantics: the first loader's item for a colliding key wins
        $this->assertSame($pointcutFromFirst, $items['shared.key']);
    }

    public function testLoadAndRegisterAddsLoadedItemsToContainer(): void
    {
        $container = new Container();
        $advisor   = $this->createMock(Advisor::class);

        $extension = $this->createMock(AspectLoaderExtension::class);
        $extension->method('load')->willReturn(['aspect.advisor' => $advisor]);

        $loader = new AspectLoader($container, $extension);
        $loader->loadAndRegister(new ReflectionClass(AspectLoaderTestAspectOne::class));

        $this->assertSame($advisor, $container->get('aspect.advisor'));
    }

    public function testGetUnloadedAspectsReturnsOnlyAspectsNotYetLoaded(): void
    {
        $container = new Container();
        $container->add(AspectLoaderTestAspectOne::class, new AspectLoaderTestAspectOne());
        $container->add(AspectLoaderTestAspectTwo::class, new AspectLoaderTestAspectTwo());

        $loader = new AspectLoader($container);

        // Before anything is loaded, both aspects are unloaded
        $this->assertSame(
            [AspectLoaderTestAspectOne::class, AspectLoaderTestAspectTwo::class],
            self::names($loader->getUnloadedAspects()),
        );

        $loader->loadAndRegister(new ReflectionClass(AspectLoaderTestAspectOne::class));

        $this->assertSame([AspectLoaderTestAspectTwo::class], self::names($loader->getUnloadedAspects()));
    }

    public function testDeferredAspectsAreLoadedWithoutCreatingThem(): void
    {
        $container = new Container();
        $container->addLazyService(
            AspectLoaderTestAspectOne::class,
            static fn(): never => throw new LogicException('The aspect must not be created to load it'),
        );
        $extension = $this->createMock(AspectLoaderExtension::class);
        $extension->method('load')->willReturn([]);
        $loader = new AspectLoader($container, $extension);

        $this->assertSame([AspectLoaderTestAspectOne::class], self::names($loader->getUnloadedAspects()));
        $loader->loadAndRegister(new ReflectionClass(AspectLoaderTestAspectOne::class));

        $this->assertSame([], $loader->getUnloadedAspects());
    }

    /**
     * @param list<ReflectionClass<covariant Aspect>> $aspects
     *
     * @return list<class-string<Aspect>>
     */
    private static function names(array $aspects): array
    {
        return array_map(static fn(ReflectionClass $aspect): string => $aspect->name, $aspects);
    }
}

final class AspectLoaderTestAspectOne implements Aspect {}

final class AspectLoaderTestAspectTwo implements Aspect {}
