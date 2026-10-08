<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use PhpParser\Comment\Doc;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\StaticVar;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Builds the statements of an intercepted method or function body directly as AST.
 *
 * Rendered output shape:
 * <pre>
 * /** @var DynamicMethodInvocation<self, int> $__joinPoint *\/
 * static $__joinPoint = InterceptorInjector::forMethod(
 *     self::class,
 *     'method',
 *     [...],
 *     $this->methodOriginalAlias(...),
 * );
 * return $__joinPoint->__invoke($this, [$argument]);
 * </pre>
 *
 * Generated bodies are never printed to a string and parsed back: the proxy generators hand these
 * statements to the method/function generators, the whole proxy is printed once.
 *
 * @internal
 */
final class JoinPointStatementsGenerator
{
    /**
     * Name of the static variable that keeps the joinpoint of a generated body
     */
    public const string JOINPOINT_VARIABLE = '__joinPoint';

    /**
     * Builds `static $__joinPoint = <Injector>::<injectorMethod>(...);` with its `@var` docblock
     *
     * @param string    $joinPointType  PhpDoc type of the joinpoint, e.g. `DynamicMethodInvocation<self, int>`
     * @param string    $injector       Name of InterceptorInjector in the generated file (its import alias)
     * @param string    $injectorMethod Factory method of InterceptorInjector, e.g. `forMethod`
     * @param list<Arg> $injectorArgs   Arguments of the factory call
     */
    public static function createInitialization(
        string $joinPointType,
        string $injector,
        string $injectorMethod,
        array $injectorArgs,
    ): Static_ {
        $initialization = new Static_([
            new StaticVar(
                new Variable(self::JOINPOINT_VARIABLE),
                new StaticCall(new Name($injector), new Identifier($injectorMethod), $injectorArgs),
            ),
        ]);
        $initialization->setDocComment(new Doc('/** @var ' . $joinPointType . ' $' . self::JOINPOINT_VARIABLE . ' */'));

        return $initialization;
    }

    /**
     * Builds `return $__joinPoint->__invoke(...);`, or the bare call when the result must not be returned
     *
     * @param list<Arg> $args
     */
    public static function createInvocation(array $args, bool $returnsResult): Return_|Expression
    {
        $invocation = new MethodCall(new Variable(self::JOINPOINT_VARIABLE), new Identifier('__invoke'), $args);

        return $returnsResult ? new Return_($invocation) : new Expression($invocation);
    }

    /**
     * Tells whether the generated body returns the result of the joinpoint
     *
     * Constructors and __clone() return nothing, whatever the joinpoint returns, as well as void and never functions.
     */
    public static function returnsResult(ReflectionFunctionAbstract $function): bool
    {
        if ($function instanceof ReflectionMethod && ($function->isConstructor() || $function->name === '__clone')) {
            return false;
        }
        if (!$function->hasReturnType()) {
            return true;
        }
        $returnType = $function->getReturnType();

        return !($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['void', 'never'], true));
    }
}
