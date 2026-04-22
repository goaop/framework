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
use Go\Core\AspectContainer;
use Go\Proxy\Generator\AttributeGroupsGenerator;
use Go\Proxy\Generator\PropertyGenerator;
use Go\Proxy\Generator\PropertyNodeProvider;
use Go\Proxy\Generator\TypeGenerator;
use Go\Proxy\TraitProxyGenerator;
use InvalidArgumentException;
use PhpParser\Comment\Doc;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
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
use PhpParser\Node\Scalar\MagicConst\Class_ as ClassMagicConst;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\Stmt\StaticVar;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/**
 * Generates intercepted property hooks for trait proxies.
 *
 * Unlike class proxies, trait proxies do not use a shared static $__joinPoints property.
 * Instead, each hook lazily creates and caches its own static $__joinPoint.
 */
final class TraitInterceptedPropertyGenerator implements PropertyNodeProvider
{
    /**
     * @param list<string> $adviceNames
     */
    public function __construct(
        private readonly ReflectionProperty $property,
        private readonly array $adviceNames
    ) {
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

        return new PropertyHook('get', [
            ...$this->getFieldAccessInitializationStatements(),
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

    private function createSetHook(): PropertyHook
    {
        $propertyName = $this->property->getName();
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
            ...$this->getFieldAccessInitializationStatements(),
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

    /**
     * @return array{0:Static_,1:If_,2:Expression}
     */
    private function getFieldAccessInitializationStatements(): array
    {
        $propertyName = $this->property->getName();

        $initializeJoinPoint = new Expression(new Assign(
            new Variable('__joinPoint'),
            new StaticCall(
                new Name\FullyQualified(TraitProxyGenerator::class),
                'getJoinPoint',
                [
                    new Arg(new ClassMagicConst()),
                    new Arg(new String_(AspectContainer::PROPERTY_PREFIX)),
                    new Arg(new String_($propertyName)),
                    new Arg(new Array_(array_map(
                        static fn (string $adviceName): ArrayItem => new ArrayItem(new String_($adviceName)),
                        $this->adviceNames
                    ))),
                ]
            )
        ));

        $fieldAccessExpression = new Expression(new Assign(
            new Variable('fieldAccess'),
            new Variable('__joinPoint')
        ));
        $propertyType = (string) $this->property->getType();
        $fieldAccessExpression->setDocComment(new Doc('/** @var \Go\Aop\Intercept\FieldAccess<self, ' . $propertyType . '> $fieldAccess */'));

        return [
            new Static_([new StaticVar(new Variable('__joinPoint'))]),
            new If_(
                new Identical(new Variable('__joinPoint'), new ConstFetch(new Name('null'))),
                ['stmts' => [$initializeJoinPoint]]
            ),
            $fieldAccessExpression
        ];
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
