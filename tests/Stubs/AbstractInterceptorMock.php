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

    public function __construct(Closure $adviceMethod, int $adviceOrder = 0, string $pointcutExpression = '')
    {
        self::$advice = $adviceMethod;
        parent::__construct($adviceMethod, $adviceOrder, $pointcutExpression);
    }

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
