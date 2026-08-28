<?php

declare(strict_types=1);

namespace Repro;

trait Php85NoDiscard__AopProxied
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
include_once AOP_CACHE_DIR . '/Transformer/_files/audit/Php85NoDiscard.php';
