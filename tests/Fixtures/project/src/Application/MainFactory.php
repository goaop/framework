<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Woven file creating Main with `new`: intercepted by InitializationAspect without any kernel feature flag
 */
class MainFactory
{
    public function create(): Main
    {
        return new Main();
    }
}
