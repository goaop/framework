<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

class WovenParent
{
    public function describe(): string
    {
        return 'parent of ' . static::class;
    }

    public static function create(): static
    {
        return new static();
    }
}
