<?php

declare(strict_types=1);

namespace Go\Tests\Audit;

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
