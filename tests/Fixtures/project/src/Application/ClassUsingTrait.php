<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture: a class whose methods come from both a used trait (BehaviorTrait)
 * and its own body. Functional tests verify that both sources are correctly woven.
 */
class ClassUsingTrait
{
    use BehaviorTrait;

    public function ownMethod(): string
    {
        return 'own';
    }
}
