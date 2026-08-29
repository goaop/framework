<?php

declare(strict_types=1);

namespace Go\Stubs;

#[\Attribute(\Attribute::TARGET_ALL)]
class ExprAttr
{
    public function __construct(public mixed $value = null)
    {
    }
}
