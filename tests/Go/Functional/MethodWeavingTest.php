<?php

namespace Go\Functional;

use Go\Tests\TestProject\Application\AbstractBar;
use Go\Tests\TestProject\Application\Main;

class MethodWeavingTest extends BaseFunctionalTest
{
    /**
     * test for https://github.com/goaop/framework/issues/335
     */
    public function testItDoesNotWeaveAbstractMethods()
    {
        // it weaves Main class
        $this->assertClassIsWoven(Main::class);
        $this->assertMethodJoinPointExists(Main::class, 'doSomething', 'advisor.Go\\Tests\\TestProject\\Aspect\\LoggingAspect->beforeMethod', 0);
        $this->assertMethodJoinPointExists(Main::class, 'doSomething', 'advisor.Go\\Tests\\TestProject\\Aspect\\DoSomethingAspect->afterDoSomething', 1);
        $this->assertMethodJoinPointExists(Main::class, 'doSomethingElse', 'advisor.Go\\Tests\\TestProject\\Aspect\\DoSomethingAspect->afterDoSomething');

        // it does not weaves AbstractBar class
        $this->assertClassIsNotWoven(AbstractBar::class);
    }
}
