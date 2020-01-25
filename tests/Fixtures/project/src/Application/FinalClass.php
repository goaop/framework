<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

final class FinalClass extends ParentWithFinalMethod
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
