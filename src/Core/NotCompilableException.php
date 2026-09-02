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
 * The advisor cache writer treats this exception as a signal to skip caching
 * for the affected aspect entirely and fall back to direct loading.
 */
final class NotCompilableException extends AspectException {}
