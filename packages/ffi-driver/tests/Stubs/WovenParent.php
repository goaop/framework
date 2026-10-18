<?php

declare(strict_types=1);

namespace Go\Driver\Ffi\Stubs;

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
