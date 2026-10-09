<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture (issue #761): trait method with #[\Override], it can not be intercepted in a class using the trait
 */
trait Issue761OverrideTrait
{
    #[\Override]
    public function hello(): string
    {
        return 'trait';
    }
}
