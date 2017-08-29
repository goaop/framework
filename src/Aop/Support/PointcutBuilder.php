<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Closure;
use Go\Aop\Advice;
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\AfterThrowingInterceptor;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\DeclareErrorInterceptor;
use Go\Core\AspectContainer;

/**
 * Pointcut builder provides simple DSL for declaring pointcuts in plain PHP code
 */
class PointcutBuilder
{
    /**
     * @var AspectContainer
     */
    protected $container;

    /**
     * Default constructor for the builder
     *
     * @param AspectContainer $container Instance of container
     */
    public function __construct(AspectContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Declares the "Before" hook for specific pointcut expression
     *
     * @param string $pointcutExpression Pointcut, e.g. "within(**)"
     * @param Closure $advice Advice to call
     */
    public function before($pointcutExpression, Closure $advice)
    {
        $interceptor = new BeforeInterceptor($advice, 0, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * Declares the "After" hook for specific pointcut expression
     *
     * @param string $pointcutExpression Pointcut, e.g. "within(**)"
     * @param Closure $advice Advice to call
     */
    public function after($pointcutExpression, Closure $advice)
    {
        $interceptor = new AfterInterceptor($advice, 0, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * Declares the "AfterThrowing" hook for specific pointcut expression
     *
     * @param string $pointcutExpression Pointcut, e.g. "within(**)"
     * @param Closure $advice Advice to call
     */
    public function afterThrowing($pointcutExpression, Closure $advice)
    {
        $interceptor = new AfterThrowingInterceptor($advice, 0, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * Declares the "Around" hook for specific pointcut expression
     *
     * @param string $pointcutExpression Pointcut, e.g. "within(**)"
     * @param Closure $advice Advice to call
     */
    public function around($pointcutExpression, Closure $advice)
    {
        $interceptor = new AroundInterceptor($advice, 0, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * Declares the error message for specific pointcut expression
     *
     * @param string $pointcutExpression Pointcut, e.g. "within(**)"
     * @param string $message Error message to show
     * @param integer $level Error level to trigger
     */
    public function declareError($pointcutExpression, $message, $level = E_USER_ERROR)
    {
        $interceptor = new DeclareErrorInterceptor($message, $level, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }


    /**
     * General method to register advices
     *
     * @param string $pointcutExpression Pointcut expression string
     * @param Advice $advice Instance of advice
     */
    private function registerAdviceInContainer($pointcutExpression, Advice $advice)
    {
        $this->container->registerAdvisor(
            new LazyPointcutAdvisor($this->container, $pointcutExpression, $advice),
            $this->getPointcutId($pointcutExpression)
        );
    }

    /**
     * Returns a unique name for pointcut expression
     *
     * @param string $pointcutExpression
     *
     * @return string
     */
    private function getPointcutId($pointcutExpression)
    {
        static $index = 0;

        return preg_replace('/\W+/', '_', $pointcutExpression) . '.' . $index++;
    }
}
