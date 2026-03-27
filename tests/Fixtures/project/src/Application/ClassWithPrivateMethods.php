<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture: a class with private and protected methods.
 * Functional tests verify the trait-based proxy engine's capability to intercept
 * non-public methods — impossible with the old extend-based engine.
 */
class ClassWithPrivateMethods
{
    public function publicEntry(): string
    {
        return $this->doPrivate();
    }

    private function doPrivate(): string
    {
        return 'private-result';
    }

    protected function doProtected(): string
    {
        return 'protected-result';
    }
}
