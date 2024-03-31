<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

use Go\Tests\TestProject\Annotation as Aop;

class Main extends AbstractBar
{
    private $privateClassProperty;

    protected $protectedClassProperty;

    public $publicClassProperty;

    #[Aop\Loggable]
    public function doSomething()
    {
        echo 'I did something';
    }

    public function doSomethingElse()
    {
        echo 'I did something else';
    }

    public function getFilename()
    {
        $reflectedClass = new \ReflectionClass($this);
        return $reflectedClass->getFileName();
    }
}
