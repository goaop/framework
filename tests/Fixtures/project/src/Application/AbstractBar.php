<?php

namespace Go\Tests\TestProject\Application;

abstract class AbstractBar implements FooInterface
{
    public abstract function doSomethingElse();

    public function doSomeThirdThing()
    {
        echo 'I did some third thing';
    }
}
