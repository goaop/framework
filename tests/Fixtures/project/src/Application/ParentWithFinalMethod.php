<?php

namespace Go\Tests\TestProject\Application;

class ParentWithFinalMethod
{
    final public function someFinalParentMethod()
    {
        echo "I'am final public method, do not touch me now.";
    }
}
