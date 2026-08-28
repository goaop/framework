<?php

declare(strict_types=1);

namespace Go\Tests\Audit;

class Php80GlobalConstAttrArg
{
    #[ExprAttr(PHP_INT_MAX)]
    public function limited(): int
    {
        return PHP_INT_MAX;
    }
}
