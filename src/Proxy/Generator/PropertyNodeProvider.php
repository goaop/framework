<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

use PhpParser\Node\Stmt\Property as PropertyNode;

/**
 * Implemented by anything that can produce a PhpParser property node.
 *
 * Allows ClassGenerator to accept both PropertyGenerator instances and
 * specialised wrappers like JoinPointPropertyGenerator without coupling.
 */
interface PropertyNodeProvider
{
    public function getNode(): PropertyNode;
}
