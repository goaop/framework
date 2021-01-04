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
     * Instance of aspect container
     */
    protected AspectContainer $container;

    /**
     * Default constructor for the builder
     */
    public function __construct(AspectContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Declares the "Before" hook for specific pointcut expression
     */
    public function before(string $pointcutExpression, Closure $adviceToInvoke): void
    {
        $interceptor = new BeforeInterceptor($adviceToInvoke, 0, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * Declares the "After" hook for specific pointcut expression
     */
    public function after(string $pointcutExpression, Closure $adviceToInvoke): void
    {
        $interceptor = new AfterInterceptor($adviceToInvoke, 0, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * Declares the "AfterThrowing" hook for specific pointcut expression
     */
    public function afterThrowing(string $pointcutExpression, Closure $adviceToInvoke): void
    {
        $interceptor = new AfterThrowingInterceptor($adviceToInvoke, 0, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * Declares the "Around" hook for specific pointcut expression
     */
    public function around(string $pointcutExpression, Closure $adviceToInvoke): void
    {
        $interceptor = new AroundInterceptor($adviceToInvoke, 0, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * Declares the error message for specific pointcut expression with concrete error level
     */
    public function declareError(string $pointcutExpression, string $message, int $errorLevel = E_USER_ERROR): void
    {
        $interceptor = new DeclareErrorInterceptor($message, $errorLevel, $pointcutExpression);
        $this->registerAdviceInContainer($pointcutExpression, $interceptor);
    }

    /**
     * General method to register advices
     */
    private function registerAdviceInContainer(string $pointcutExpression, Advice $adviceToInvoke): void
    {
        $this->container->registerAdvisor(
            new LazyPointcutAdvisor($this->container, $pointcutExpression, $adviceToInvoke),
            $this->getPointcutId($pointcutExpression)
        );
    }

    /**
     * Returns an unique name for given pointcut expression
     */
    private function getPointcutId(string $pointcutExpression): string
    {
        static $index = 0;

        return preg_replace('/\W+/', '_', $pointcutExpression) . '.' . $index++;
    }
}
