<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture (issue #761): a class importing a trait method with #[\Override]. Its pointcut excludes the trait
 * method with `!matchInherited()`, so the class is woven for its own method and stays loadable.
 */
class Issue761Child extends Issue761Base
{
    use Issue761OverrideTrait;

    public function own(): string
    {
        return 'own';
    }
}
