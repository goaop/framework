<?php
declare(strict_types=1);

namespace Go\Stubs\Constructor;

class ClassWithProtectedConstructor
{
    protected function __construct(string $className, int &$byReference)
    {
        echo $className;
        $byReference = 42;
    }

    public static function getInstance()
    {
        $value = 0;
        return new self(static::class, $value);
    }
}
