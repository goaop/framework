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

/**
 * @package go
 */
class BaseInterceptor extends BaseAdvice implements Interceptor
{
    /**
     * Name of the aspect
     *
     * @var string
     */
    public $aspectName = '';

    /**
     * Pointcut instance
     *
     * @var null|Pointcut
     */
    public $pointcut = null;

    /**
     * Advice to call
     *
     * In Spring it's ReflectionMethod, but this will be slowly
     *
     * @var null|\Closure
     */
    protected $adviceMethod = null;

    /**
     * Default constructor for interceptor
     *
     * @param callable $adviceMethod Interceptor advice to call
     * @param Pointcut $pointcut Pointcut instance where interceptor should be called
     */
    public function __construct($adviceMethod, Pointcut $pointcut = null)
    {
        assert('is_callable($adviceMethod) /* Advice method should be callable */');

        $this->adviceMethod = $adviceMethod;
        $this->pointcut     = $pointcut;
    }
}
