<?php

declare(strict_types=1);

namespace Go\Stubs;

#[\Attribute(\Attribute::TARGET_CONSTANT | \Attribute::TARGET_CLASS_CONSTANT)]
class ConstAttr
{
    public function __construct(public string $reason = '')
    {
    }
}
