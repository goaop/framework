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

namespace Go\Core\Cache;

use Go\Aop\AspectException;

/**
 * Thrown when an advisor cache item cannot be compiled into plain PHP.
 *
 * Since the base Pointcut/Advice/Advisor contracts extend {@see \Go\Aop\Compilable},
 * this is limited to items whose compileToPhp() implementation cannot express the
 * instance statically: an advice closure not scoped to an {@see \Go\Aop\Aspect} class
 * (it cannot be restored from a first-class callable on an aspect instance), or a
 * userland implementation that deliberately refuses compilation.
 *
 * The exception propagates out of the cache writer: such an aspect cannot be used with
 * the advisor cache enabled - fix the item or run without a cache directory.
 *
 * @internal
 */
final class NotCompilableException extends AspectException {}
