<?php

namespace Go\Functional;

use Go\Tests\TestProject\Application\AbstractBar;
use Go\Tests\TestProject\Application\FinalClass;
use Go\Tests\TestProject\Application\FooInterface;
use Go\Tests\TestProject\Application\Main;

class ClassWeavingTest extends BaseFunctionalTest
{
    public function testPropertyWeaving()
    {
        // it weaves Main class public and protected properties
        $this->assertPropertyWoven(Main::class, 'publicClassProperty', 'advisor.Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');
        $this->assertPropertyWoven(Main::class, 'protectedClassProperty', 'advisor.Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');

        // it does not weaves Main class private property
        $this->assertPropertyNotWoven(Main::class, 'privateClassProperty', 'advisor.Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');
    }

    /**
     * test for https://github.com/goaop/framework/issues/335
     */
    public function testItDoesNotWeaveAbstractMethods()
    {
        // it weaves Main class
        $this->assertClassIsWoven(Main::class);

        // it weaves Main class methods
        $this->assertMethodWoven(Main::class, 'doSomething', 'advisor.Go\\Tests\\TestProject\\Aspect\\LoggingAspect->beforeMethod', 0);
        $this->assertMethodWoven(Main::class, 'doSomething', 'advisor.Go\\Tests\\TestProject\\Aspect\\DoSomethingAspect->afterDoSomething', 1);
        $this->assertMethodWoven(Main::class, 'doSomethingElse', 'advisor.Go\\Tests\\TestProject\\Aspect\\DoSomethingAspect->afterDoSomething');

        // it does not weaves AbstractBar class
        $this->assertClassIsNotWoven(AbstractBar::class);
    }

    public function testClassInitializationWeaving()
    {
        $this->assertClassInitializationWoven(Main::class, 'advisor.Go\\Tests\\TestProject\\Aspect\\InitializationAspect->beforeInstanceInitialization');
        $this->assertClassStaticInitializationWoven(Main::class, 'advisor.Go\\Tests\\TestProject\\Aspect\\InitializationAspect->afterClassStaticInitialization');
    }

    public function testItWeavesFinalClasses()
    {
        // it weaves FinalClass class
        $this->assertClassIsWoven(FinalClass::class);

        /* @see \Go\Tests\TestProject\Application\FinalClass::somePublicMethod */
        // it weaves somePublicMethod
        $this->assertMethodWoven(FinalClass::class, 'somePublicMethod');

        /* @see \Go\Tests\TestProject\Application\FinalClass::someFinalPublicMethod() */
        // it should match and weave someFinalPublicMethod
        $this->assertMethodWoven(FinalClass::class, 'someFinalPublicMethod');

        /* @see \Go\Tests\TestProject\Application\ParentWithFinalMethod::someFinalParentMethod() */
        // it should not match with parent final method in the class
        $this->assertMethodNotWoven(FinalClass::class, 'someFinalParentMethod');
    }

    public function testItDoesNotWeaveInterfaces()
    {
        $this->assertClassIsNotWoven(FooInterface::class);
    }
}
