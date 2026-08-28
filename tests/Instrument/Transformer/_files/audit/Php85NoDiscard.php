<?php

declare(strict_types=1);

namespace Repro;

class Php85NoDiscard
{
    #[\NoDiscard('result must be used')]
    public function computeTotal(int $a, int $b): int
    {
        return $a + $b;
    }

    public function caller(): int
    {
        return $this->computeTotal(1, 2);
    }
}
