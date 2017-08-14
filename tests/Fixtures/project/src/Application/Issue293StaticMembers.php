<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

class Issue293StaticMembers
{
    public static function doSomething()
    {
        echo 'I did something';
    }

    protected static function doSomethingElse()
    {
        echo 'I did something else';
    }
}
