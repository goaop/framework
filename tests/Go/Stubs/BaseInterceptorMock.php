<?php

namespace Go\Stubs;

use Go\Aop\Framework\BaseInterceptor;
use Go\Aop\Pointcut;

class BaseInterceptorMock extends BaseInterceptor
{
    private static $advice = null;

    /**
     * {@inheritdoc}
     */
    public function __construct($adviceMethod, $order = 0, Pointcut $pointcut = null)
    {
        self::$advice = $adviceMethod;
        parent::__construct($adviceMethod, $order, $pointcut);
    }

    /**
     * {@inheritdoc}
     */
    public static function serializeAdvice($adviceMethod)
    {
        return array(
            'scope'  => 'aspect',
            'method' => 'Go\Aop\Framework\{closure}',
            'aspect' => 'Go\Aop\Framework\BaseInterceptorTest'
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function unserializeAdvice($adviceData)
    {
        return self::$advice;
    }

}
