<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

use Closure;
use Countable;
use Exception;
use Iterator;

class ClassWithComplexTypes
{
    public function publicMethodWithUnionTypeReturn(Exception|Closure $value): Exception|Closure
    {
        return $value;
    }

    public function publicMethodWithIntersectionTypeReturn(Exception&Countable $value): Exception&Countable
    {
        return $value;
    }

    public function publicMethodWithDNFTypeReturn(Iterator|(Exception&Countable) $value): Iterator|(Exception&Countable)
    {
        return $value;
    }
}
