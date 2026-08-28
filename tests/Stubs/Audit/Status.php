<?php

declare(strict_types=1);

namespace Go\Stubs\Audit;

enum Status: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
