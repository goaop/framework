<?php
declare(strict_types=1);

namespace Go\Stubs\Constructor;

class ClassWithOptionalArgsConstructor
{
    public function __construct(int $foo = 42, bool $bar = false, \stdClass $instance = null)
    {
    }
}
