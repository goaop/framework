<?php

namespace Go\Tests\TestProject\Application;

use Go\Tests\TestProject\Annotation as Aop;

final class FinalClass
{
    public function somePublicMethod()
    {
        echo "I'am public method in the final class";
    }

    final public function someFinalPublicMethod()
    {
        echo "I'am final public method, do not touch me now.";
    }
}
