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
use PhpParser\Node\Expr\ClassConstFetch;
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
 *         static $__joinPoint;
 *         if ($__joinPoint === null) {
 *             $__joinPoint = InterceptorInjector::forProperty(self::class, 'name', [...]);
 *         }
 *         return $__joinPoint->__invoke($this, FieldAccessType::READ, $this->name);
 *     }
 *     set {
 *         static $__joinPoint;
 *         if ($__joinPoint === null) {
 *             $__joinPoint = InterceptorInjector::forProperty(self::class, 'name', [...]);
 *         }
 *         $this->name = $__joinPoint->__invoke($this, FieldAccessType::WRITE, $value, $this->name);
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
 *         static $__joinPoint;
 *         if ($__joinPoint === null) {
 *             $__joinPoint = InterceptorInjector::forProperty(self::class, 'items', [...]);
 *         }
 *         $value = &$__joinPoint->__invoke($this, FieldAccessType::READ, $this->items);
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
     *     static $__joinPoint;
     *     if ($__joinPoint === null) {
     *         $__joinPoint = InterceptorInjector::forProperty(self::class, '<propertyName>', [...]);
     *     }
     *     return $__joinPoint->__invoke($this, FieldAccessType::READ, $this-><propertyName>);
     * }
     * </pre>
     *
     * Rendered PHP template for potentially uninitialized typed properties:
     * <pre>
     * get {
     *     static $__joinPoint;
     *     if ($__joinPoint === null) {
     *         $__joinPoint = InterceptorInjector::forProperty(self::class, '<propertyName>', [...]);
     *     }
     *     if ($__joinPoint->getField()->isInitialized($this)) {
     *         return $__joinPoint->__invoke($this, FieldAccessType::READ, $this-><propertyName>);
     *     }
     *     return $__joinPoint->__invoke($this, FieldAccessType::READ);
     * }
     * </pre>
     *
     * For array typed properties this becomes `&get`:
     * <pre>
     * &get {
     *     static $__joinPoint;
     *     if ($__joinPoint === null) {
     *         $__joinPoint = InterceptorInjector::forProperty(self::class, '<propertyName>', [...]);
     *     }
     *     if ($__joinPoint->getField()->isInitialized($this)) {
     *         return $__joinPoint->__invoke($this, FieldAccessType::READ, $this-><propertyName>);
     *     }
     *     return $__joinPoint->__invoke($this, FieldAccessType::READ);
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
     *     static $__joinPoint;
     *     if ($__joinPoint === null) {
     *         $__joinPoint = InterceptorInjector::forProperty(self::class, '<propertyName>', [...]);
     *     }
     *     $this-><propertyName> = $__joinPoint->__invoke($this, FieldAccessType::WRITE, $value, $this-><propertyName>);
     * }
     * </pre>
     *
     * Rendered PHP template for potentially uninitialized typed properties:
     * <pre>
     * set {
     *     static $__joinPoint;
     *     if ($__joinPoint === null) {
     *         $__joinPoint = InterceptorInjector::forProperty(self::class, '<propertyName>', [...]);
     *     }
     *     if ($__joinPoint->getField()->isInitialized($this)) {
     *         $this-><propertyName> = $__joinPoint->__invoke($this, FieldAccessType::WRITE, $value, $this-><propertyName>);
     *     } else {
     *         $this-><propertyName> = $__joinPoint->__invoke($this, FieldAccessType::WRITE, $value);
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
    private function getFieldAccessInitializationStatements(StaticCall $initExpression): array
    {
        $joinPointStaticVar = new Static_([new StaticVar(new Variable('__joinPoint'), $initExpression)]);
        $joinPointStaticVar->setDocComment($this->createFieldAccessDocComment('__joinPoint', false));

        return [$joinPointStaticVar];
    }

    private function createFieldAccessInitializationExpression(string $propertyName): StaticCall
    {
        return new StaticCall(
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
        );
    }
}
