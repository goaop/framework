<?php

declare(strict_types=1);

namespace Go\Stubs;

#[ExprAttr(static function (int $x): int { return $x * 2; })]
class Php85ClosuresInConstExpr
{
    public const \Closure UPPER = strtoupper(...);

    public const \Closure DOUBLER = static function (int $x): int {
        return $x * 2;
    };

    #[ExprAttr(strlen(...))]
    public function withClosureDefaults(
        \Closure $normalizer = trim(...),
        \Closure $mapper = static function (int $v): int {
            return $v + 1;
        },
    ): string {
        return ($normalizer)(' hello ');
    }
}
