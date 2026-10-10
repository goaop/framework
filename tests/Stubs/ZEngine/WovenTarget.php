<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

final class WovenTarget
{
    private int $calls = 0;

    public function greet(string $name): string
    {
        $this->calls++;

        return 'hello ' . $name;
    }

    public function getCalls(): int
    {
        return $this->calls;
    }
}
