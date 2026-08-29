<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.1 backed enum whose case values are constant expressions, not plain literals (issue #600).
 */
enum ConstExprStatus: int
{
    private const int SHIFT = 2;

    case Negative  = -1;
    case Shifted   = 1 << 2;
    case FromConst = self::SHIFT + 10;

    public function describe(): string
    {
        return $this->name . '=' . $this->value;
    }
}
