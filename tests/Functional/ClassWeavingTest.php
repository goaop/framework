<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Functional;

use Go\Tests\TestProject\Application\AbstractBar;
use Go\Tests\TestProject\Application\ClassWithComplexTypes;
use Go\Tests\TestProject\Application\FinalClass;
use Go\Tests\TestProject\Application\FooInterface;
use Go\Tests\TestProject\Application\Main;

class ClassWeavingTest extends BaseFunctionalTestCase
{
    public function testPropertyWeaving(): void
    {
        // it weaves Main class public and protected properties
        $this->assertPropertyWoven(Main::class, 'publicClassProperty', 'Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');
        $this->assertPropertyWoven(Main::class, 'protectedClassProperty', 'Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');

        // it does not weaves Main class private property
        $this->assertPropertyNotWoven(Main::class, 'privateClassProperty', 'Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');
    }

    /**
     * test for https://github.com/goaop/framework/issues/335
     */
    public function testItDoesNotWeaveAbstractMethods(): void
    {
        // it weaves Main class
        $this->assertClassIsWoven(Main::class);

        // it weaves Main class methods
        $this->assertMethodWoven(Main::class, 'doSomething', 'Go\\Tests\\TestProject\\Aspect\\LoggingAspect->beforeMethod', 0);
        $this->assertMethodWoven(Main::class, 'doSomething', 'Go\\Tests\\TestProject\\Aspect\\DoSomethingAspect->afterDoSomething', 1);
        $this->assertMethodWoven(Main::class, 'doSomethingElse', 'Go\\Tests\\TestProject\\Aspect\\DoSomethingAspect->afterDoSomething');

        // it does not weaves AbstractBar class
        $this->assertClassIsNotWoven(AbstractBar::class);
    }

    public function testClassInitializationWeaving(): void
    {
        $this->assertClassInitializationWoven(Main::class, 'Go\\Tests\\TestProject\\Aspect\\InitializationAspect->beforeInstanceInitialization');
        $this->assertClassStaticInitializationWoven(Main::class, 'Go\\Tests\\TestProject\\Aspect\\InitializationAspect->afterClassStaticInitialization');
    }

    public function testItWeavesFinalClasses(): void
    {
        // it weaves FinalClass class
        $this->assertClassIsWoven(FinalClass::class);

        /* @see FinalClass::somePublicMethod */
        // it weaves somePublicMethod
        $this->assertMethodWoven(FinalClass::class, 'somePublicMethod');

        /* @see FinalClass::someFinalPublicMethod() */
        // it should match and weave someFinalPublicMethod
        $this->assertMethodWoven(FinalClass::class, 'someFinalPublicMethod');

        /* @see ParentWithFinalMethod::someFinalParentMethod() */
        // it should not match with parent final method in the class
        $this->assertMethodNotWoven(FinalClass::class, 'someFinalParentMethod');
    }

    public function testItDoesNotWeaveInterfaces(): void
    {
        $this->assertClassIsNotWoven(FooInterface::class);
    }

    public function testItDoesWeaveMethodWithComplexTypes(): void
    {
        // it weaves ClassWithComplexTypes class
        $this->assertClassIsWoven(ClassWithComplexTypes::class);

        $this->assertMethodWoven(ClassWithComplexTypes::class, 'publicMethodWithUnionTypeReturn');
        $this->assertMethodWoven(ClassWithComplexTypes::class, 'publicMethodWithIntersectionTypeReturn');
        $this->assertMethodWoven(ClassWithComplexTypes::class, 'publicMethodWithDNFTypeReturn');
    }
}
