<?php

declare(strict_types=1);

namespace Go\Stubs;

class Php85PipeOperator
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
