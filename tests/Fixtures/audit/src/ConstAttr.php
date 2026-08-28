<?php

declare(strict_types=1);

namespace Go\Tests\Audit;

#[\Attribute(\Attribute::TARGET_CONSTANT | \Attribute::TARGET_CLASS_CONSTANT)]
class ConstAttr
{
    public function __construct(public string $reason = '')
    {
    }
}
