<?php

namespace Go\Tests\TestProject\Application;

use Go\Tests\TestProject\Annotation as Aop;

class CircularyWeaved
{
    /**
     * @Aop\Loggable()
     */
    public function youCanNotWeaveMe()
    {

    }
}
