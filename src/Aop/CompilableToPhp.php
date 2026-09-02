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

namespace Go\Aop;

use PhpParser\Node\Expr;

/**
 * Marks framework items that can compile themselves into a plain-PHP expression.
 *
 * The produced expression is a static constructor call graph (nested "new" expressions
 * and factory calls) that, when evaluated, recreates an equivalent instance. It is used
 * to render advisor cache files as includable PHP instead of serialized blobs.
 *
 * @internal
 */
interface CompilableToPhp
{
    /**
     * Compiles this instance into a PHP expression recreating an equivalent instance
     */
    public function compileToPhp(): Expr;
}
