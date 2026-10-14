<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture (issue #761): uses a woven trait whose intercepted method has #[\Override] and overrides the parent
 */
class Issue761WovenOverrideTraitUser extends Issue761Base
{
    use Issue761WovenOverrideTrait;
}
