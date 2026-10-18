<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Advised by a property pointcut: a join point kind the ffi driver refuses
 */
final class FfiUnsupported
{
    public int $counter = 0;

    public function increment(): int
    {
        return ++$this->counter;
    }
}
