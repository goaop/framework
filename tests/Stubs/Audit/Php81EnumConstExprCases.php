<?php

declare(strict_types=1);

namespace Go\Stubs\Audit;

enum Php81EnumConstExprCases: int
{
    private const int SHIFT = 2;

    case Negative = -1;
    case Shifted = 1 << 2;
    case FromConst = self::SHIFT + 10;

    public function describe(): string
    {
        return $this->name . '=' . $this->value;
    }
}
