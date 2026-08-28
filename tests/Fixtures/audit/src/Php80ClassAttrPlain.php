<?php

declare(strict_types=1);

namespace Go\Tests\Audit;

#[ExprAttr]
class Php80ClassAttrPlain
{
    public function run(): int
    {
        return 42;
    }
}
