<?php

declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Trait used by BackedEnum to verify that enums with pre-included traits are woven correctly.
 */
trait EnumExtraBehaviorTrait
{
    public function extraBehavior(): string
    {
        return 'extra';
    }
}
