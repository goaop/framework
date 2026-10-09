<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture (issue #761): parent class whose method is overridden by a trait method with #[\Override]
 */
class Issue761Base
{
    public function hello(): string
    {
        return 'base';
    }
}
