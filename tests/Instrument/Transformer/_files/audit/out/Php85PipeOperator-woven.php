<?php

declare(strict_types=1);

namespace Repro;

trait Php85PipeOperator__AopProxied
{
    public function transform(string $input): string
    {
        return $input
            |> trim(...)
            |> (fn(string $x) => strtoupper($x))
            |> strrev(...);
    }

    public function withNewOnRhs(string $value): \ArrayObject
    {
        return [$value] |> (fn(array $items) => new \ArrayObject($items));
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/audit/Php85PipeOperator.php';
