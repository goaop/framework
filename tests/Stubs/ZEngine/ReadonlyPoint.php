<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

/**
 * A readonly class: its donor must be readonly too (a readonly parent requires a readonly child)
 */
final readonly class ReadonlyPoint
{
    public function __construct(public int $x = 0, public int $y = 0) {}

    public function translate(int $dx, int $dy): self
    {
        return new self($this->x + $dx, $this->y + $dy);
    }
}
