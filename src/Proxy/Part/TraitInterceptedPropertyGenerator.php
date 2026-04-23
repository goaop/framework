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

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\FieldAccessType;
use Go\Proxy\Generator\PropertyNodeProvider;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\StaticVar;
use ReflectionProperty;

/**
 * Generates intercepted property hooks for trait proxies.
 *
 * Unlike class proxies, trait proxies do not use a shared static $__joinPoints property.
 * Instead, each hook lazily creates and caches its own static $__joinPoint.
 */
final class TraitInterceptedPropertyGenerator extends AbstractInterceptedPropertyGenerator implements PropertyNodeProvider
{
    /**
     * @param list<string> $adviceNames
     */
    public function __construct(
        ReflectionProperty $property,
        private readonly array $adviceNames
    ) {
        parent::__construct($property);
    }

    public function getNode(): PropertyNode
    {
        $generator = $this->createBasePropertyGenerator();

        $isArrayProperty = $this->isArrayTypedProperty();
        $generator->addHook($this->createGetHook($isArrayProperty));
        if (!$isArrayProperty) {
            $generator->addHook($this->createSetHook());
        }

        return $generator->getNode();
    }

    private function createGetHook(bool $returnsByReference): PropertyHook
    {
        $propertyName = $this->property->getName();
        $readInvokeWithValue = new MethodCall(new Variable('__joinPoint'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'READ')),
            new Arg(new PropertyFetch(new Variable('this'), $propertyName)),
        ]);
        $readInvokeWithoutValue = new MethodCall(new Variable('__joinPoint'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'READ')),
        ]);

        return new PropertyHook('get', [
            ...$this->getFieldAccessInitializationStatements(),
            $this->hasPotentiallyUninitializedTypedProperty()
                ? new If_(
                    new MethodCall(
                        new MethodCall(new Variable('__joinPoint'), 'getField'),
                        'isInitialized',
                        [new Arg(new Variable('this'))]
                    ),
                    [
                        'stmts' => [
                            new Return_($readInvokeWithValue)
                        ],
                        'else' => new Else_([new Return_($readInvokeWithoutValue)]),
                    ]
                )
                : new Return_($readInvokeWithValue),
        ], ['byRef' => $returnsByReference]);
    }

    private function createSetHook(): PropertyHook
    {
        $propertyName = $this->property->getName();
        $writeInvokeWithBackedValue = new MethodCall(new Variable('__joinPoint'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'WRITE')),
            new Arg(new Variable('value')),
            new Arg(new PropertyFetch(new Variable('this'), $propertyName)),
        ]);
        $writeInvokeWithoutBackedValue = new MethodCall(new Variable('__joinPoint'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'WRITE')),
            new Arg(new Variable('value')),
        ]);

        return new PropertyHook('set', [
            ...$this->getFieldAccessInitializationStatements(),
            $this->hasPotentiallyUninitializedTypedProperty()
                ? new If_(
                    new MethodCall(
                        new MethodCall(new Variable('__joinPoint'), 'getField'),
                        'isInitialized',
                        [new Arg(new Variable('this'))]
                    ),
                    [
                        'stmts' => [
                            new Expression(new Assign(
                                new PropertyFetch(new Variable('this'), $propertyName),
                                $writeInvokeWithBackedValue
                            )),
                        ],
                        'else' => new Else_([
                            new Expression(new Assign(
                                new PropertyFetch(new Variable('this'), $propertyName),
                                $writeInvokeWithoutBackedValue
                            )),
                        ]),
                    ]
                )
                : new Expression(new Assign(
                    new PropertyFetch(new Variable('this'), $propertyName),
                    $writeInvokeWithBackedValue
                )),
        ]);
    }

    /**
     * @return array<int, Node\Stmt>
     */
    private function getFieldAccessInitializationStatements(): array
    {
        $propertyName = $this->property->getName();

        $initializeJoinPoint = new Expression(new Assign(
            new Variable('__joinPoint'),
            new StaticCall(
                new Name\FullyQualified(InterceptorInjector::class),
                'forProperty',
                [
                    new Arg(new ClassConstFetch(new Name('self'), 'class')),
                    new Arg(new String_($propertyName)),
                    new Arg(new Array_(array_map(
                        static fn (string $adviceName): ArrayItem => new ArrayItem(new String_($adviceName)),
                        $this->adviceNames
                    ))),
                ]
            )
        ));

        $joinPointStaticVar = new Static_([new StaticVar(new Variable('__joinPoint'))]);
        $joinPointStaticVar->setDocComment($this->createFieldAccessDocComment('__joinPoint', true));

        return [
            $joinPointStaticVar,
            new If_(
                new Identical(new Variable('__joinPoint'), new ConstFetch(new Name('null'))),
                ['stmts' => [$initializeJoinPoint]]
            )
        ];
    }

}
