<?php

namespace Go\Tests\TestProject\Application;

use Go\Tests\TestProject\Annotation as Aop;

class Main extends AbstractBar
{
    /**
     * @Aop\Loggable()
     */
    public function doSomething()
    {
        echo 'I did something';
    }

    public function doSomethingElse()
    {
        echo 'I did something else';
    }
}
