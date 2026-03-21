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

use Go\Core\AspectContainer;
use Go\Proxy\TraitProxyGenerator;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Static_;

class TraitMethodInvocationCallASTGenerator extends AbstractFunctionLikeInvocationCallASTGenerator
{
    public function generate(array $advices): array
    {
        assert($this->functionLike instanceof \ReflectionMethod, 'Only valid methods are allowed');

        $builder  = new BuilderFactory();
        $isStatic = $this->functionLike->isStatic();
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        // $__joinPoint
        $joinPointVariable = $builder->var('__joinPoint');

        // static \$__joinPoint;
        $staticVarStatement = new Static_([new StaticVar($joinPointVariable)]);

        // if ($__joinPoint === null) {
        //     $__joinPoint = TraitProxyGenerator::getJoinPoint(self::class, '{$prefix}', '{$method->name}', {$advicesCode});
        // }
        $conditionalInitializationExpression = new If_(
            new Identical(
                $joinPointVariable,
                new ConstFetch(
                    new Name('null')
                )
            ),
            [
                'stmts' => [
                    new Expression(
                        new Assign(
                            $joinPointVariable,
                            $builder->staticCall(
                                '\\' . TraitProxyGenerator::class,
                                'getJoinPoint',
                                [
                                    $builder->classConstFetch('self', 'class'),
                                    $prefix,
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