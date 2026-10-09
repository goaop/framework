<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Stubs;

/**
 * Namespaced class extending a class from the global namespace (issue #759)
 *
 * @extends \ArrayObject<array-key, mixed>
 */
class GlobalParentCollection extends \ArrayObject
{
    public function hello(): string
    {
        return 'hello';
    }
}
