<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

use Go\Tests\TestProject\Annotation as Aop;

class Main
{
    /**
     * @Aop\Loggable()
     */
    public function doSomething()
    {

    }
}
