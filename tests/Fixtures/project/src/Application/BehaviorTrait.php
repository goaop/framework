<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture trait used by ClassUsingTrait.
 * Provides methods that the class itself does NOT declare, so functional tests
 * can verify that trait-defined methods are correctly woven into the proxy.
 */
trait BehaviorTrait
{
    public function doSomeTraitBehavior(): string
    {
        return 'trait-behavior';
    }

    protected function getProtectedTraitState(): string
    {
        return 'protected-trait';
    }
}
