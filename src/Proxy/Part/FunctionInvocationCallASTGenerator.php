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

use Go\Proxy\FunctionProxyGenerator;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Static_;

class FunctionInvocationCallASTGenerator extends AbstractFunctionLikeInvocationCallASTGenerator
{
    public function generate(array $advices): array
    {
        $builder = new BuilderFactory();

        // $__joinPoint
        $joinPointVariable = $builder->var('__joinPoint');

        // static \$__joinPoint;
        $staticVarStatement = new Static_([new StaticVar($joinPointVariable)]);

        // if ($__joinPoint === null) {
        //     $__joinPoint = FunctionProxyGenerator::getJoinPoint('{$function->name}', {$advicesCode});
        // }
        $conditionalInitializationExpression = new If_(
            cond: new Identical(
                left: $joinPointVariable,
                right: new ConstFetch(
                    new Name('null')
                )
            ),
            subNodes: [
                'stmts' => [
                    new Expression(
                        new Assign(
                            var: $joinPointVariable,
                            expr: $builder->staticCall(
                                '\\' . FunctionProxyGenerator::class,
                                'getJoinPoint',
                                [
                                    $this->functionLike->name,
                                    $advices
                                ]
                            )
                        )
                    )
                ]
            ]
        );

        // $__joinPoint->__invoke(<$arguments>)
        $methodCallExpression = $builder->methodCall($joinPointVariable, '__invoke', $this->generateInvocationArgumentList());

        // [return] $__joinPoint->__invoke(<$arguments>);
        $callExpression = $this->wrapCallWithReturnIfNeeded($methodCallExpression);

        return [
            $staticVarStatement,
            $conditionalInitializationExpression,
            $callExpression
        ];
    }
}