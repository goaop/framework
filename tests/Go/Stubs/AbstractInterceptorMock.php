<?php
declare(strict_types = 1);

namespace Go\Stubs;

use Closure;
use Go\Aop\Framework\AbstractInterceptor;
use Go\Aop\Intercept\Joinpoint;

class AbstractInterceptorMock extends AbstractInterceptor
{
    private static $advice;

    /**
     * {@inheritdoc}
     */
    public function __construct($adviceMethod, $adviceOrder = 0, $pointcutExpression = '')
    {
        self::$advice = $adviceMethod;
        parent::__construct($adviceMethod, $adviceOrder, $pointcutExpression);
    }

    /**
     * {@inheritdoc}
     */
    public static function serializeAdvice(Closure $adviceMethod) : array
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
    public static function unserializeAdvice(array $adviceData) : Closure
    {
        return self::$advice;
    }

    /**
     * {@inheritdoc}
     */
    public function invoke(Joinpoint $joinpoint)
    {
        return $joinpoint;
    }
}
