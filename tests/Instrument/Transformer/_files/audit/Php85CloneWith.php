<?php

declare(strict_types=1);

namespace Repro;

class Php85CloneWith
{
    public function __construct(
        public string $name = 'initial',
        public int $count = 0,
    ) {
    }

    public function withName(string $name): static
    {
        return clone($this, ['name' => $name]);
    }

    public function bump(): static
    {
        return clone($this, ['count' => $this->count + 1, 'name' => $this->name . '+']);
    }
}
