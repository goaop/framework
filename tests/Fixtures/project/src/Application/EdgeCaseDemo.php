<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

use LogicException;

/**
 * Method shapes that weaving must keep working: generators, never-returning methods, the __call() magic method
 * and optional parameters passed as named arguments
 */
class EdgeCaseDemo
{
    /**
     * @return \Generator<int, int>
     */
    public function numbers(int $count): \Generator
    {
        for ($i = 1; $i <= $count; $i++) {
            yield $i;
        }
    }

    public function fail(string $message): never
    {
        throw new LogicException($message);
    }

    public function describe(int $first = 1, int $second = 2, int $third = 3): string
    {
        return "{$first},{$second},{$third}";
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): string
    {
        return $name . '(' . implode(',', $arguments) . ')';
    }
}
