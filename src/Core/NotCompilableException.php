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

namespace Go\Core;

use Go\Aop\AspectException;

/**
 * Thrown when an advisor cache item cannot be compiled into plain PHP.
 *
 * This happens when a custom aspect loader extension produces Advisor/Pointcut/Advice
 * items that do not implement {@see \Go\Aop\CompilableToPhp} (for example a hand-written
 * Advisor class or an interceptor subclass unknown to the Interceptor facade), or when
 * an advice closure is not scoped to an {@see \Go\Aop\Aspect} class and therefore cannot
 * be restored from a first-class callable on an aspect instance.
 *
 * The advisor cache writer treats this exception as a signal to skip caching for the
 * affected aspect entirely (with an E_USER_WARNING explaining the reason): the aspect is
 * loaded directly on every request and never cached, but keeps working.
 *
 * @internal
 */
final class NotCompilableException extends AspectException {}
