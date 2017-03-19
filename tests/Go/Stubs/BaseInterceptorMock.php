<?php
declare(strict_types = 1);

namespace Go\Stubs;

use Go\Aop\Framework\BaseInterceptor;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Pointcut;

class BaseInterceptorMock extends BaseInterceptor
{
    private static $advice = null;

    /**
     * {@inheritdoc}
     */
    public function __construct($adviceMethod, $order = 0, $pointcutExpression = '')
    {
        self::$advice = $adviceMethod;
        parent::__construct($adviceMethod, $order, $pointcutExpression);
    }

    /**
     * {@inheritdoc}
     */
    public static function serializeAdvice(\Closure $adviceMethod)
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
    public static function unserializeAdvice(array $adviceData)
    {
        return self::$advice;
    }

    /**
     * Implement this method to perform extra treatments before and
     * after the invocation of joinpoint.
     *
     * @param Joinpoint $joinpoint current joinpoint
     *
     * @return mixed the result of the call
     */
    public function invoke(Joinpoint $joinpoint)
    {
        return $joinpoint;
    }
}
