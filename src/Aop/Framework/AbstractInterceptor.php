<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;
use Go\Aop\Aspect;
use Go\Aop\Exception\NotCompilableException;
use Go\Aop\Intercept\Interceptor as InterceptorInterface;
use Go\Aop\OrderedAdvice;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use ReflectionFunction;

/**
 * Base class for all framework interceptor implementations
 *
 * This class describe an action taken by the interceptor at a particular joinpoint.
 * Different types of interceptors include "around", "before" and "after" advices.
 *
 * Around interceptor is an advice that surrounds a joinpoint such as a method invocation. This is the most powerful
 * kind of advice. Around advices will perform custom behavior before and after the method invocation. They are
 * responsible for choosing whether to proceed to the joinpoint or to shortcut executing by returning their own return
 * value or throwing an exception.
 *
 * After and before interceptors are simple closures that will be invoked after and before main invocation.
 *
 * Framework models an interceptor as an PHP {@see Closure}, maintaining a chain of interceptors "around" the joinpoint:
 * <pre>
 *   public function (Joinpoint $joinPoint)
 *   {
 *      echo 'Before action';
 *      // call chain here with Joinpoint->proceed() method
 *      $result = $joinPoint->proceed();
 *      echo 'After action';
 *
 *      return $result;
 *   }
 * </pre>
 */
abstract class AbstractInterceptor implements InterceptorInterface, OrderedAdvice
{
    /**
     * Default constructor for interceptor
     */
    public function __construct(
        protected readonly Closure $adviceMethod,
        /** Order of advice invocation, lower values are invoked first */
        public readonly int $adviceOrder = 0,
        /**
         * Pointcut expression that was used to create this interceptor
         *
         * @internal Framework-internal introspection point (e.g. debug:advisor command), not a public API
         */
        public private(set) string $pointcutExpression = '',
        /**
         * @var class-string<Aspect>|null Aspect class of an advice method, null for a plain closure advice
         *
         * @internal Set by the lazy {@see Interceptor} facade: the weaver reads it without creating the aspect
         */
        public private(set) ?string $aspectClass = null,
        /**
         * Name of the advice method of {@see $aspectClass}
         *
         * @internal Set together with {@see $aspectClass}
         */
        public private(set) ?string $adviceMethodName = null,
    ) {}

    /**
     * Getter for extracting the advice closure from Interceptor
     *
     * @internal
     */
    public function getRawAdvice(): Closure
    {
        return $this->adviceMethod;
    }

    /**
     * Compiles the interceptor into an Interceptor factory facade call
     *
     * The emitted expression mirrors the interceptor declarations rendered into generated
     * proxy code and carries pure static data, e.g.
     * `Interceptor::after(SomeAspect::class, 'afterMethod')` - the facade defers aspect
     * resolution and advice closure creation behind a native lazy proxy.
     */
    final public function compileToPhp(): Expr
    {
        // A lazy interceptor knows its aspect method: reading the advice closure would create the aspect
        if ($this->aspectClass !== null && $this->adviceMethodName !== null) {
            $aspectClass = $this->aspectClass;
            $methodName  = $this->adviceMethodName;
        } else {
            $reflectionAdvice     = new ReflectionFunction($this->adviceMethod);
            $scopeReflectionClass = $reflectionAdvice->getClosureScopeClass();
            if (!isset($scopeReflectionClass) || !is_subclass_of($scopeReflectionClass->name, Aspect::class)) {
                throw NotCompilableException::interceptorWithoutAspect();
            }
            $aspectClass = $scopeReflectionClass->name;
            $methodName  = $reflectionAdvice->name;
        }

        $factoryMethod = match ($this::class) {
            BeforeInterceptor::class        => 'before',
            AfterInterceptor::class         => 'after',
            AroundInterceptor::class        => 'around',
            AfterThrowingInterceptor::class => 'afterThrowing',
            default                         => throw NotCompilableException::unsupportedInterceptor($this::class),
        };

        $args = [
            new Arg(new ClassConstFetch(new FullyQualified($aspectClass), 'class')),
            new Arg(new String_($methodName)),
        ];
        if ($this->adviceOrder !== 0) {
            $args[] = new Arg(new Int_($this->adviceOrder), name: new Identifier('order'));
        }
        if ($this->pointcutExpression !== '') {
            $args[] = new Arg(new String_($this->pointcutExpression), name: new Identifier('expression'));
        }

        return new StaticCall(new FullyQualified(Interceptor::class), $factoryMethod, $args);
    }
}
