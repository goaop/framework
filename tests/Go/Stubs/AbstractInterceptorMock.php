<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Stubs;

use Closure;
use Go\Aop\Framework\AbstractInterceptor;
use Go\Aop\Intercept\Joinpoint;

class AbstractInterceptorMock extends AbstractInterceptor
{
    private static Closure $advice;

    /**
     * {@inheritdoc}
     */
    public function __construct(Closure $adviceMethod, int $adviceOrder = 0, string $pointcutExpression = '')
    {
        self::$advice = $adviceMethod;
        parent::__construct($adviceMethod, $adviceOrder, $pointcutExpression);
    }

    /**
     * {@inheritdoc}
     */
    public static function serializeAdvice(Closure $adviceMethod): array
    {
        return [
            'scope'  => 'aspect',
            'method' => 'Go\Aop\Framework\{closure}',
            'aspect' => 'Go\Aop\Framework\BaseInterceptorTest'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function unserializeAdvice(array $adviceData): Closure
    {
        return self::$advice;
    }

    /**
     * {@inheritdoc}
     *
     * @return Joinpoint Covariant return type
     */
    public function invoke(Joinpoint $joinpoint): Joinpoint
    {
        return $joinpoint;
    }
}
