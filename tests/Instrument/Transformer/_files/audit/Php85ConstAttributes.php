<?php

declare(strict_types=1);

namespace Repro;

#[\Attribute(\Attribute::TARGET_CONSTANT | \Attribute::TARGET_CLASS_CONSTANT)]
class ConstAttr
{
    public function __construct(public string $reason = '')
    {
    }
}

class Php85ConstAttributes
{
    #[ConstAttr('class constant attribute')]
    public const string LABEL = 'label';

    #[\Deprecated(message: 'use LABEL')]
    public const string OLD_LABEL = 'old';

    public function getLabel(): string
    {
        return self::LABEL;
    }
}
