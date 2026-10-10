<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Child fixture of the z-engine driver: own methods get their body swapped, the inherited public methods get an
 * override, signatures use the self keyword and a class constant default
 */
final class ZEngineChild extends ZEngineParent
{
    public const int TIMES = 3;

    private int $calls = 0;

    public function greet(string $name): string
    {
        $this->calls++;

        return 'Hi, ' . $name . ' from child';
    }

    public function withDefault(int $times = self::TIMES, ?self $other = null): string
    {
        return str_repeat('x', $times) . ($other === null ? '' : ' with ' . $other::class);
    }

    public function getCalls(): int
    {
        return $this->calls;
    }
}
