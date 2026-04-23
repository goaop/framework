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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\ArrayItem;
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
 * Generates an intercepted class property using native PHP 8.4 property hooks.
 *
 * For regular properties it generates both `get` and `set` hooks.
 *
 * Rendered output shape:
 * <pre>
 * public string $name = 'value' {
 *     get {
 *         $fieldAccess = self::$__joinPoints['prop:name'];
 *         return $fieldAccess->__invoke($this, FieldAccessType::READ, $this->name);
 *     }
 *     set {
 *         $fieldAccess = self::$__joinPoints['prop:name'];
 *         $this->name = $fieldAccess->__invoke($this, FieldAccessType::WRITE, $value, $this->name);
 *     }
 * }
 * </pre>
 *
 * For `array` typed properties only a by-reference `&get` hook is generated to keep
 * indirect modifications (`array_push($this->items, ...)`) valid.
 *
 * Rendered output shape:
 * <pre>
 * public array $items = [] {
 *     &get {
 *         $fieldAccess = self::$__joinPoints['prop:items'];
 *         $value = &$fieldAccess->__invoke($this, FieldAccessType::READ, $this->items);
 *         return $value;
 *     }
 * }
 * </pre>
 */
final class InterceptedPropertyGenerator extends AbstractInterceptedPropertyGenerator implements PropertyNodeProvider
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

    /**
     * Builds AST for a native property `get` hook.
     *
     * Rendered PHP template for initialized/backed properties:
     * <pre>
     * get {
     *     $fieldAccess = self::$__joinPoints['prop:<propertyName>'];
     *     return $fieldAccess->__invoke($this, FieldAccessType::READ, $this-><propertyName>);
     * }
     * </pre>
     *
     * Rendered PHP template for potentially uninitialized typed properties:
     * <pre>
     * get {
     *     $fieldAccess = self::$__joinPoints['prop:<propertyName>'];
     *     if ($fieldAccess->getField()->isInitialized($this)) {
     *         return $fieldAccess->__invoke($this, FieldAccessType::READ, $this-><propertyName>);
     *     }
     *     return $fieldAccess->__invoke($this, FieldAccessType::READ);
     * }
     * </pre>
     *
     * For array typed properties this becomes `&get`:
     * <pre>
     * &get {
     *     $fieldAccess = self::$__joinPoints['prop:<propertyName>'];
     *     if ($fieldAccess->getField()->isInitialized($this)) {
     *         return $fieldAccess->__invoke($this, FieldAccessType::READ, $this-><propertyName>);
     *     }
     *     return $fieldAccess->__invoke($this, FieldAccessType::READ);
     * }
     * </pre>
     */
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
        $fieldAccessExpression = $this->createFieldAccessInitializationExpression($propertyName);

        return new PropertyHook('get', [
            ...$this->getFieldAccessInitializationStatements($fieldAccessExpression),
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

    /**
     * Builds AST for a native property `set` hook.
     *
     * Rendered PHP template for initialized/backed properties:
     * <pre>
     * set {
     *     $fieldAccess = self::$__joinPoints['prop:<propertyName>'];
     *     $this-><propertyName> = $fieldAccess->__invoke($this, FieldAccessType::WRITE, $value, $this-><propertyName>);
     * }
     * </pre>
     *
     * Rendered PHP template for potentially uninitialized typed properties:
     * <pre>
     * set {
     *     $fieldAccess = self::$__joinPoints['prop:<propertyName>'];
     *     if ($fieldAccess->getField()->isInitialized($this)) {
     *         $this-><propertyName> = $fieldAccess->__invoke($this, FieldAccessType::WRITE, $value, $this-><propertyName>);
     *     } else {
     *         $this-><propertyName> = $fieldAccess->__invoke($this, FieldAccessType::WRITE, $value);
     *     }
     * }
     * </pre>
     */
    private function createSetHook(): PropertyHook
    {
        $propertyName = $this->property->getName();
        $fieldAccessExpression = $this->createFieldAccessInitializationExpression($propertyName);

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
            ...$this->getFieldAccessInitializationStatements($fieldAccessExpression),
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
     * Generates lazy static initialization for property-level field access joinpoint.
     *
     * Property hooks are executed for every read/write, so we cache the resolved
     * ClassFieldAccess in a static local variable per hook to avoid repeated container
     * lookups, mirroring method-level lazy joinpoint initialization.
     *
     * @return array<int, \PhpParser\Node\Stmt>
     */
    private function getFieldAccessInitializationStatements(Expression $fieldAccessExpression): array
    {
        $joinPointStaticVar = new Static_([new StaticVar(new Variable('__joinPoint'))]);
        $joinPointStaticVar->setDocComment($this->createFieldAccessDocComment('__joinPoint', true));

        return [
            $joinPointStaticVar,
            new If_(
                new Identical(new Variable('__joinPoint'), new ConstFetch(new Name('null'))),
                ['stmts' => [$fieldAccessExpression]]
            ),
        ];
    }

    private function createFieldAccessInitializationExpression(string $propertyName): Expression
    {
        return new Expression(new Assign(
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
    }
}
