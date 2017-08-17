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

        // it does not weaves AbstractBar class
        $this->assertClassIsNotWoven(AbstractBar::class);
    }
}
