<?php

namespace Go\Tests\TestProject\Application;

abstract class AbstractBar implements FooInterface
{
    abstract public function doSomethingElse();

    public function doSomeThirdThing()
    {
        echo 'I did some third thing';
    }
}
