<?php

declare(strict_types=1);

namespace Go\Stubs;

#[RichAttr(Status::Active, new \ArrayObject([1, 2]))]
class Php81NonScalarAttributeArgs
{
    #[RichAttr(Status::Disabled, 123)]
    public function tagged(#[RichAttr(Status::Active)] int $x = 8): int
    {
        return $x;
    }
}
