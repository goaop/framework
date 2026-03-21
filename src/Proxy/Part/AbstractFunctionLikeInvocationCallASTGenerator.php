<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use PhpParser\BuilderFactory;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Prepares the function call argument list
 */
abstract class AbstractFunctionLikeInvocationCallASTGenerator
{
    /**
     * If function contains optional arguments
     */
    private bool $hasOptionals = false;

    /**
     * AbstractFunctionLikeCallGenerator constructor.
     *
     * @param ReflectionFunctionAbstract $functionLike Instance of function or method to call
     */
    public function __construct(protected readonly ReflectionFunctionAbstract $functionLike)
    {
        foreach ($functionLike->getParameters() as $parameter) {
            $this->hasOptionals = $this->hasOptionals || $parameter->isOptional();
        }
    }

    protected function generateInvocationArgumentList(bool $withContext = true): array
    {
        $builder      = new BuilderFactory();
        $argumentList = [];
        $parameters   = $this->functionLike->getParameters();

        // First argument for methods is either instance (for normal methods) or static scope (for static methods)
        if ($withContext && $this->functionLike instanceof ReflectionMethod) {
            if ($this->functionLike->isStatic()) {
                $scopeArgument = $builder->classConstFetch('static', 'class');
            } else {
                $scopeArgument = $builder->var('this');
            }
            $argumentList[] = $scopeArgument;
        }

        if (count($parameters) > 0) {
            $argumentArrayItems = [];

            // Variadic argument requires direct handling, thus we remove it from the list
            if ($this->functionLike->isVariadic()) {
                $variadicArgument = array_pop($parameters);
            }

            // Each parameter is wrapped into an array item, if param is by reference, we should also pass as reference
            foreach ($parameters as $parameter) {
                $argumentArrayItems[] = new ArrayItem(
                    $builder->var($parameter->getName()),
                    null,
                    $parameter->isPassedByReference()
                );
            }

            // All arguments are packed into one single short array
            $expression = new Array_($argumentArrayItems, ['kind' => Array_::KIND_SHORT]);

            // If some parameters are not required, then we should always check how much exactly parameters given
            if ($this->hasOptionals) {
                $expression = $builder->funcCall(
                    '\\array_slice',
                    [
                        $expression,
                        0,
                        $builder->funcCall('\\func_num_args')
                    ]
                );
            }
            $argumentList[] = $expression;

            // Append last variadic argument directly if we have it
            if (isset($variadicArgument)) {
                $argumentList[] = $builder->var($variadicArgument->getName());
            }
        }

        return $argumentList;
    }

    final protected function wrapCallWithReturnIfNeeded(CallLike $callLikeExpression): Return_|Expression
    {
        $shouldReturn = true;
        if ($this->functionLike->hasReturnType()) {
            $returnType = $this->functionLike->getReturnType();
            if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['void', 'never'], true)) {
                // void/never return types should not return anything
                $shouldReturn = false;
            }
        }

        return $shouldReturn ? new Return_($callLikeExpression) : new Expression($callLikeExpression);
    }
}
