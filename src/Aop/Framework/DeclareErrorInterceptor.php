<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;
use Go\Aop\Intercept\Joinpoint;
use Stringable;

/**
 * Interceptor to dynamically trigger an user notice/warning/error on method call
 *
 * This interceptor can be used as active replacement for the "deprecated" tag or to notify about
 * probable issues with specific method.
 */
final class DeclareErrorInterceptor extends AbstractInterceptor
{
    /**
     * Default constructor for interceptor
     *
     * @param (string&non-empty-string) $message Error message to show for this interceptor
     * @param int&(E_USER_NOTICE|E_USER_WARNING|E_USER_ERROR|E_USER_DEPRECATED) $level Default level of error, only E_USER_* constants
     */
    public function __construct(
        protected string $message,
        protected int $level,
        string $pointcutExpression
    ) {
        $adviceMethod = self::declareErrorAdvice(...);
        parent::__construct($adviceMethod, -256, $pointcutExpression);
    }

    public static function unserializeAdvice(array $adviceData): Closure
    {
        return self::declareErrorAdvice(...);
    }

    public function invoke(Joinpoint $joinpoint): mixed
    {
        ($this->adviceMethod)($joinpoint, $this->message, $this->level);

        return $joinpoint->proceed();
    }

    /**
     * Returns an advice
     */
    private static function declareErrorAdvice(Stringable $joinPoint, string $message, int $level): void
    {
        $message = vsprintf(
            '[AOP Declare Error]: %s has an error: "%s"',
            [
                (string) $joinPoint,
                $message
            ]
        );
        trigger_error($message, $level);
    }
}
