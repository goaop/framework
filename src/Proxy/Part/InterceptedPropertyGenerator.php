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
use Go\Proxy\Generator\AttributeGroupsGenerator;
use Go\Proxy\Generator\PropertyGenerator;
use Go\Proxy\Generator\PropertyNodeProvider;
use Go\Proxy\Generator\TypeGenerator;
use InvalidArgumentException;
use PhpParser\Comment\Doc;
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
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/**
 * Generates an intercepted class property using native PHP 8.4 property hooks.
 *
 * For regular properties it generates both `get` and `set` hooks.
 *
 * Rendered output shape:
 * ```php
 * public string $name = 'value' {
 *     get {
 *         $fieldAccess = self::$__joinPoints['prop:name'];
 *         $value = &$fieldAccess->__invoke($this, FieldAccessType::READ, $this->name);
 *         return $value;
 *     }
 *     set {
 *         $fieldAccess = self::$__joinPoints['prop:name'];
 *         $this->name = $fieldAccess->__invoke($this, FieldAccessType::WRITE, $this->name, $value);
 *     }
 * }
 * ```
 *
 * For `array` typed properties only a by-reference `&get` hook is generated to keep
 * indirect modifications (`array_push($this->items, ...)`) valid.
 *
 * Rendered output shape:
 * ```php
 * public array $items = [] {
 *     &get {
 *         $fieldAccess = self::$__joinPoints['prop:items'];
 *         $value = &$fieldAccess->__invoke($this, FieldAccessType::READ, $this->items);
 *         return $value;
 *     }
 * }
 * ```
 */
final class InterceptedPropertyGenerator implements PropertyNodeProvider
{
    public function __construct(private readonly ReflectionProperty $property)
    {
        if ($this->property->isStatic() || $this->property->isReadOnly() || $this->property->hasHooks()) {
            throw new InvalidArgumentException(sprintf(
                'Property %s::$%s cannot be intercepted with native hooks',
                $this->property->getDeclaringClass()->getName(),
                $this->property->getName()
            ));
        }
    }

    public function getNode(): PropertyNode
    {
        $flags = 0;
        if ($this->property->isPrivate()) {
            $flags |= PropertyGenerator::FLAG_PRIVATE;
        } elseif ($this->property->isProtected()) {
            $flags |= PropertyGenerator::FLAG_PROTECTED;
        } else {
            $flags |= PropertyGenerator::FLAG_PUBLIC;
        }
        if ($this->property->isFinal()) {
            $flags |= PropertyGenerator::FLAG_FINAL;
        }

        if ($this->property->isPrivateSet()) {
            $flags |= PropertyGenerator::FLAG_PRIVATE_SET;
        } elseif ($this->property->isProtectedSet()) {
            $flags |= PropertyGenerator::FLAG_PROTECTED_SET;
        }

        $generator = new PropertyGenerator($this->property->getName(), $flags);
        if ($this->property->hasType()) {
            $generator->setType(TypeGenerator::fromReflectionType($this->property->getType()));
        }
        if ($this->property->hasDefaultValue()) {
            $generator->setDefaultValue($this->property->getDefaultValue());
        }

        $attributeGroups = AttributeGroupsGenerator::fromReflectionAttributes($this->property->getAttributes());
        if ($attributeGroups !== []) {
            $generator->addAttributeGroups($attributeGroups);
        }

        $generator->addHook($this->createGetHook($this->isArrayTypedProperty()));
        if (!$this->isArrayTypedProperty()) {
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
        $propertyType = (string) $this->property->getType();
        $fieldAccessExpression->setDocComment(new Doc('/** @var \Go\Aop\Intercept\FieldAccess<self,'. $propertyType . '> $fieldAccess */'));

        return new PropertyHook('get', [
            $fieldAccessExpression,
            $this->property->hasType() && !$this->property->hasDefaultValue()
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
     *     $this-><propertyName> = $fieldAccess->__invoke($this, FieldAccessType::WRITE, $this-><propertyName>, $value);
     * }
     * </pre>
     *
     * Rendered PHP template for potentially uninitialized typed properties:
     * <pre>
     * set {
     *     $fieldAccess = self::$__joinPoints['prop:<propertyName>'];
     *     if ($fieldAccess->getField()->isInitialized($this)) {
     *         $this-><propertyName> = $fieldAccess->__invoke($this, FieldAccessType::WRITE, $this-><propertyName>, $value);
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
        $propertyType = (string) $this->property->getType();
        $fieldAccessExpression->setDocComment(new Doc('/** @var \Go\Aop\Intercept\FieldAccess<self,'. $propertyType . '> $fieldAccess */'));

        $writeInvokeWithBackedValue = new MethodCall(new Variable('fieldAccess'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'WRITE')),
            new Arg(new PropertyFetch(new Variable('this'), $propertyName)),
            new Arg(new Variable('value')),
        ]);
        $writeInvokeWithoutBackedValue = new MethodCall(new Variable('fieldAccess'), '__invoke', [
            new Arg(new Variable('this')),
            new Arg(new ClassConstFetch(new Name\FullyQualified(FieldAccessType::class), 'WRITE')),
            new Arg(new Variable('value')),
        ]);

        return new PropertyHook('set', [
            $fieldAccessExpression,
            $this->property->hasType() && !$this->property->hasDefaultValue()
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

    private function isArrayTypedProperty(): bool
    {
        $type = $this->property->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === 'array';
        }
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() === 'array') {
                    return true;
                }
            }
        }

        return false;
    }
}
