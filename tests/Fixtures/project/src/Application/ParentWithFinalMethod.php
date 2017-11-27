<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

class ParentWithFinalMethod
{
    final public function someFinalParentMethod()
    {
        echo "I'am final public method, do not touch me now.";
    }
}
