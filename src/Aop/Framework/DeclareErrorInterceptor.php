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
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\Joinpoint;
use Stringable;

/**
 * Interceptor to dynamically trigger a user notice/warning/error on method call
 *
 * This interceptor can be used as active replacement for the "deprecated" tag or to notify about
 * probable issues with specific method.
 *
 * @phpstan-type InterceptorState array{message: non-empty-string, level: positive-int, pointcutExpression: string}
 */
final readonly class DeclareErrorInterceptor implements Interceptor
{
    private Closure $adviceMethod;

    /**
     * Default constructor for interceptor
     *
     * @param non-empty-string $message Error message to show for this interceptor
     * @param positive-int $level Default level of error, only E_USER_* constants
     * @param string $pointcutExpression Pointcut expression used
     */
    public function __construct(
        private string $message,
        private int $level,
        private string $pointcutExpression
    ) {
        $this->adviceMethod = self::declareErrorAdvice(...);
    }

    public function invoke(Joinpoint $joinpoint): mixed
    {
        ($this->adviceMethod)($joinpoint, $this->message, $this->level);

        return $joinpoint->proceed();
    }

    /**
     * Serializes an interceptor into its array shape representation
     *
     * @phpstan-return InterceptorState
     */
    final public function __serialize(): array
    {
        return [
            'message'            => $this->message,
            'level'              => $this->level,
            'pointcutExpression' => $this->pointcutExpression,
        ];
    }


    /**
     * Un-serializes an interceptor from its stored state
     *
     * @phpstan-param InterceptorState $state The stored representation of the interceptor.
     */
    final public function __unserialize(array $state): void
    {
        [
            'message'            => $this->message,
            'level'              => $this->level,
            'pointcutExpression' => $this->pointcutExpression
        ] = $state;
        $this->adviceMethod = self::declareErrorAdvice(...);
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
