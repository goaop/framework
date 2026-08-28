<?php

declare(strict_types=1);

namespace Repro;

enum Status: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}

#[\Attribute(\Attribute::TARGET_ALL)]
class RichAttr
{
    public function __construct(
        public mixed $value = null,
        public mixed $extra = null,
    ) {
    }
}

#[RichAttr(Status::Active, new \ArrayObject([1, 2]))]
class Php81NonScalarAttributeArgs
{
    #[RichAttr(Status::Disabled, PHP_INT_MAX)]
    public function tagged(#[RichAttr(Status::Active)] int $x = \PHP_INT_SIZE): int
    {
        return $x;
    }
}
