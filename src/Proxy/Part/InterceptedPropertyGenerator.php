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

use Go\Aop\Intercept\FieldAccessType;
use Go\Proxy\Generator\PropertyNodeProvider;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\Node\Stmt\Return_;
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
        $readInvokeWithValue = new MethodCall(new Variable('fieldAccess'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'READ')),
            new Arg(new PropertyFetch(new Variable('this'), $propertyName)),
        ]);
        $readInvokeWithoutValue = new MethodCall(new Variable('fieldAccess'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'READ')),
        ]);
        $fieldAccessExpression = new Expression(new Assign(
            new Variable('fieldAccess'),
            new ArrayDimFetch(
                new StaticPropertyFetch(new Name('self'), JoinPointPropertyGenerator::NAME),
                new String_('prop:' . $propertyName)
            )
        ));
        $fieldAccessExpression->setDocComment($this->createFieldAccessDocComment());

        return new PropertyHook('get', [
            $fieldAccessExpression,
            $this->hasPotentiallyUninitializedTypedProperty()
                ? new If_(
                    new MethodCall(
                        new MethodCall(new Variable('fieldAccess'), 'getField'),
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
        $fieldAccessExpression = new Expression(new Assign(
            new Variable('fieldAccess'),
            new ArrayDimFetch(
                new StaticPropertyFetch(new Name('self'), JoinPointPropertyGenerator::NAME),
                new String_('prop:' . $propertyName)
            )
        ));
        $fieldAccessExpression->setDocComment($this->createFieldAccessDocComment());

        $writeInvokeWithBackedValue = new MethodCall(new Variable('fieldAccess'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'WRITE')),
            new Arg(new Variable('value')),
            new Arg(new PropertyFetch(new Variable('this'), $propertyName)),
        ]);
        $writeInvokeWithoutBackedValue = new MethodCall(new Variable('fieldAccess'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'WRITE')),
            new Arg(new Variable('value')),
        ]);

        return new PropertyHook('set', [
            $fieldAccessExpression,
            $this->hasPotentiallyUninitializedTypedProperty()
                ? new If_(
                    new MethodCall(
                        new MethodCall(new Variable('fieldAccess'), 'getField'),
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

}
