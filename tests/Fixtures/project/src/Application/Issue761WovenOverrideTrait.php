<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture (issue #761): a woven trait whose own intercepted method has #[\Override]. The attribute is stripped
 * from the renamed original trait and kept on the proxy trait method.
 */
trait Issue761WovenOverrideTrait
{
    #[\Override]
    public function hello(): string
    {
        return 'woven-trait';
    }
}
