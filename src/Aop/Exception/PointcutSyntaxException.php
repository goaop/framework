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

namespace Go\Aop\Exception;

use Go\Aop\AspectException;

/**
 * A pointcut expression that can not be lexed or parsed, or a pattern inside it that is invalid
 */
class PointcutSyntaxException extends AspectException {}
