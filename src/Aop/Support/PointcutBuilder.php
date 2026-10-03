<?php

declare(strict_types=1);
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
use Go\Core\AspectContainer;
use ReflectionFunction;

/**
 * Pointcut builder provides simple DSL for declaring pointcuts in plain PHP code
 */
final class PointcutBuilder
{
    /**
     * Default constructor for the builder
     */
    public function __construct(private readonly AspectContainer $container) {}

    /**
     * Declares the "Before" hook for specific pointcut expression
     */
    public function before(string $pointcutExpression, Closure $adviceToInvoke): void
    {
        $interceptor = new BeforeInterceptor($adviceToInvoke, 0, $pointcutExpression);
        $this->registerAdviceInContainer('before', $pointcutExpression, $adviceToInvoke, $interceptor);
    }

    /**
     * Declares the "After" hook for specific pointcut expression
     */
    public function after(string $pointcutExpression, Closure $adviceToInvoke): void
    {
        $interceptor = new AfterInterceptor($adviceToInvoke, 0, $pointcutExpression);
        $this->registerAdviceInContainer('after', $pointcutExpression, $adviceToInvoke, $interceptor);
    }

    /**
     * Declares the "AfterThrowing" hook for specific pointcut expression
     */
    public function afterThrowing(string $pointcutExpression, Closure $adviceToInvoke): void
    {
        $interceptor = new AfterThrowingInterceptor($adviceToInvoke, 0, $pointcutExpression);
        $this->registerAdviceInContainer('afterThrowing', $pointcutExpression, $adviceToInvoke, $interceptor);
    }

    /**
     * Declares the "Around" hook for specific pointcut expression
     */
    public function around(string $pointcutExpression, Closure $adviceToInvoke): void
    {
        $interceptor = new AroundInterceptor($adviceToInvoke, 0, $pointcutExpression);
        $this->registerAdviceInContainer('around', $pointcutExpression, $adviceToInvoke, $interceptor);
    }

    /**
     * General method to register advices
     */
    private function registerAdviceInContainer(
        string $adviceKind,
        string $pointcutExpression,
        Closure $adviceClosure,
        Advice $interceptor,
    ): void {
        $this->container->add(
            $this->getAdvisorId($adviceKind, $pointcutExpression, $adviceClosure),
            new LazyPointcutAdvisor($this->container, $pointcutExpression, $interceptor),
        );
    }

    /**
     * Returns a stable id for the advisor
     *
     * Woven proxies reference closure advisors by this id (`The::advice('<id>')`), so it must not depend on the
     * registration order: it is derived from the expression, the advice kind and the source location of the closure.
     * Only identical registrations (the same closure registered twice for the same expression) get a counter suffix.
     */
    private function getAdvisorId(string $adviceKind, string $pointcutExpression, Closure $adviceClosure): string
    {
        $closureReflection = new ReflectionFunction($adviceClosure);
        $closureLocation   = $closureReflection->getFileName() . ':' . $closureReflection->getStartLine();
        $advisorId         = sprintf(
            '%s.%s.%s',
            preg_replace('/\W+/', '_', $pointcutExpression) ?? '',
            $adviceKind,
            substr(sha1($closureLocation . "\n" . $adviceKind . "\n" . $pointcutExpression), 0, 12),
        );

        $uniqueId = $advisorId;
        for ($duplicate = 2; $this->container->has($uniqueId); $duplicate++) {
            $uniqueId = $advisorId . '.' . $duplicate;
        }

        return $uniqueId;
    }
}
