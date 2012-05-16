<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Pointcut;
use Go\Aop\Framework\BaseAdvice;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\Joinpoint;


/**
 * @package go
 */
class BaseInterceptor extends BaseAdvice implements Interceptor
{
    /** @var string Name of the aspect */
    public $aspectName = '';

    /** @var null|Pointcut */
    public $pointcut = null;

    /** @var null|\Closure In Spring it's ReflectionMethod, but this will be slowly */
    protected $adviceMethod = null;

    public function __construct($adviceMethod, Pointcut $pointcut = null)
    {
        assert('!empty($adviceMethod) /* Advice must not be empty */');
        $this->adviceMethod = $adviceMethod;
        $this->pointcut = $pointcut;
    }

    /**
     * Invokes advice method for joinpoint
     *
     * @param Joinpoint $joinPoint
     * @return mixed Result of invoking of advice
     */
    protected function invokeAdviceForJoinpoint(Joinpoint $joinPoint)
    {
        $adviceMethod = $this->adviceMethod;
        return $adviceMethod($joinPoint);
    }
}
