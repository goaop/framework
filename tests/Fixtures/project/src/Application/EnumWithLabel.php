<?php

declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Interface implemented by BackedEnum to verify enum + interface combination.
 */
interface EnumWithLabel
{
    public function label(): string;
}
