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
 * Because a compiled cache file is ordinary PHP code, in production it is cached,
 * optimized and even inlined by opcache: a warm advisor cache load costs a single
 * opcache-hot include (~4.2-4.6us for a typical aspect) instead of a
 * file_get_contents()+unserialize() round-trip (~8.0-8.7us) that re-parses the blob on
 * every request. Without opcache the include is re-parsed each time (~26-29us), so this
 * design deliberately assumes the production configuration. Plain var_export() would not
 * work here either: it cannot express Closures, which are first-class citizens of the
 * framework (advice callables), while a compiled expression can defer their creation
 * behind facade calls.
 *
 * The base {@see Pointcut}, {@see Advice} and {@see Advisor} contracts extend this
 * interface, so every pointcut, advice and advisor in the system is compilable by
 * construction.
 *
 * @internal
 */
interface Compilable
{
    /**
     * Compiles this instance into a PHP expression recreating an equivalent instance
     */
    public function compileToPhp(): Expr;
}
