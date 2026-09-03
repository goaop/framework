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
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AspectLoaderTest extends TestCase
{
    public function testLoadReturnsEmptyArrayWithoutRegisteredLoaders(): void
    {
        $container = new Container();
        $loader    = new AspectLoader($container);

        $items = $loader->load(new AspectLoaderTestAspectOne());

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

        $aspect = new AspectLoaderTestAspectOne();
        $items  = $loader->load($aspect);

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

        $items = $loader->load(new AspectLoaderTestAspectOne());

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
        $loader->loadAndRegister(new AspectLoaderTestAspectOne());

        $this->assertSame($advisor, $container->getValue('aspect.advisor'));
    }

    public function testGetUnloadedAspectsReturnsOnlyAspectsNotYetLoaded(): void
    {
        $container = new Container();
        $aspectOne = new AspectLoaderTestAspectOne();
        $aspectTwo = new AspectLoaderTestAspectTwo();

        $container->add(AspectLoaderTestAspectOne::class, $aspectOne);
        $container->add(AspectLoaderTestAspectTwo::class, $aspectTwo);

        $loader = new AspectLoader($container);

        // Before anything is loaded, both aspects are unloaded
        $this->assertSame(
            [$aspectOne, $aspectTwo],
            $loader->getUnloadedAspects(),
        );

        $loader->loadAndRegister($aspectOne);

        $this->assertSame([$aspectTwo], $loader->getUnloadedAspects());
    }
}

final class AspectLoaderTestAspectOne implements Aspect
{
}

final class AspectLoaderTestAspectTwo implements Aspect
{
}
