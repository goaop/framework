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
use Go\Aop\Exception\AspectCircularReferenceException;
use Go\Aop\Exception\NotCompilableException;
use Go\Aop\Intercept\Interceptor as InterceptorInterface;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\OrderedAdvice;
use Go\Core\LazyServiceCreation;
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
     * Advice closure, called with the joinpoint
     */
    protected readonly Closure $adviceMethod;

    /**
     * Aspect class and advice method of an advice given by name, null for a closure advice
     *
     * @var array{class-string<Aspect>, string}|null
     *
     * @internal Read by the weaver and the advisor cache: the lazy {@see Interceptor} facade sets it without
     *           initializing the interceptor, so the aspect is not created
     */
    public private(set) ?array $aspectMethod = null;

    /**
     * Default constructor for interceptor
     *
     * @param Closure|array{class-string<Aspect>, string} $adviceMethod Advice closure, or the aspect class and the
     *        name of its advice method: the aspect is then resolved from the container ({@see The::aspect()}).
     *        The array is not a callable for PHP (the advice method is not static), so the type can not be callable.
     */
    public function __construct(
        Closure|array $adviceMethod,
        /** Order of advice invocation, lower values are invoked first */
        public readonly int $adviceOrder = 0,
        /**
         * Pointcut expression that was used to create this interceptor
         *
         * @internal Framework-internal introspection point (e.g. debug:advisor command), not a public API
         */
        public private(set) string $pointcutExpression = '',
    ) {
        if ($adviceMethod instanceof Closure) {
            $this->adviceMethod = $adviceMethod;
        } else {
            [$aspectClass, $methodName] = $adviceMethod;
            $this->adviceMethod = The::aspect($aspectClass)->$methodName(...);
            $this->aspectMethod = $adviceMethod;
        }
    }

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
     * Reports an advice whose aspect is being created: the advice would run on an aspect that does not exist yet
     *
     * Called by invoke() before the advice runs, only while a lazy service is being created
     * ({@see LazyServiceCreation::$inProgress} is not empty), which keeps the check off the hot path.
     *
     * @throws AspectCircularReferenceException
     */
    final protected function assertAspectIsCreated(Joinpoint $joinpoint): void
    {
        $reflectionAdvice = new ReflectionFunction($this->adviceMethod);
        $aspect           = $reflectionAdvice->getClosureThis();
        if ($aspect === null || !LazyServiceCreation::isInProgress($aspect)) {
            return;
        }
        $calls = LazyServiceCreation::callsSinceCreationOf($aspect);

        throw AspectCircularReferenceException::adviceRunsDuringCreation(
            $aspect::class,
            (string) $joinpoint,
            [...$calls, $aspect::class . '->' . $reflectionAdvice->name . '()'],
        );
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
        // An advice given by name is compiled without reading the closure: that would create the aspect
        [$aspectClass, $methodName] = $this->aspectMethod
            ?? self::aspectMethodOf($this->adviceMethod)
            ?? throw NotCompilableException::interceptorWithoutAspect();

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

    /**
     * Returns the aspect class and the method name of an advice closure that references an aspect method
     *
     * Only a named closure is a method reference: `$aspect->advice(...)` or `ReflectionMethod::getClosure()` of a
     * method of an {@see Aspect}. An anonymous closure scoped to an aspect is not, although its scope class is the
     * aspect: a closure created inside an aspect method, or a partial application of an aspect method
     * (`$aspect->advice(?, 300)`, PHP 8.6), which is scoped to the aspect wherever it is created. Its name, like
     * `{closure:pfa:...}`, can not be called on the aspect (issue #790).
     *
     * @return array{class-string<Aspect>, string}|null Null for any other closure
     *
     * @internal Shared by the weaver ({@see GeneratedInterceptor::fromAdvice()}) and the advisor cache
     *           ({@see compileToPhp()})
     */
    final public static function aspectMethodOf(Closure $advice): ?array
    {
        $reflectionAdvice = new ReflectionFunction($advice);
        $scopeClass       = $reflectionAdvice->getClosureScopeClass();
        if ($reflectionAdvice->isAnonymous() || $scopeClass === null || !is_subclass_of($scopeClass->name, Aspect::class)) {
            return null;
        }

        return [$scopeClass->name, $reflectionAdvice->name];
    }
}
