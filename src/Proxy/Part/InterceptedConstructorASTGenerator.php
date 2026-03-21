<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use LogicException;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\PrettyPrinter\Standard;
use ReflectionMethod;


/**
 * Prepares the definition of intercepted constructor
 */
final readonly class InterceptedConstructorASTGenerator
{
    private ClassMethod $constructorGenerator;

    /**
     * InterceptedConstructor
     *
     * @param non-empty-list<string>&array<string> $interceptedProperties List of intercepted properties for the class
     * @param ReflectionMethod|null $constructor Instance of original constructor or null
     * @param ClassMethod|null $constructorNode Constructor node generator (if present)
     * @param bool $useTypeWidening Should generator use parameter widening for PHP>=7.2
     */
    public function __construct(
        array                 $interceptedProperties,
        ReflectionMethod|null $constructor = null,
        ClassMethod|null      $constructorNode = null,
        bool                  $useTypeWidening = false
    ) {
        if ($constructor?->isPrivate()) {
            throw new LogicException(
                "Constructor in the class {$constructor->class} is declared as private. " .
                'Properties could not be intercepted.'
            );
        }
        $constructorBodyStatements = $this->getConstructorBodyStatements($interceptedProperties);
        if (isset($constructor)) {
            // We have parent constructor defined in the class, need to respect signature
            if (!isset($constructorNode)) {
                // We have parent constructor, but constructor method is not intercepted
                $parentConstructorCall     = new ParentConstructorCallASTGenerator($constructor);
                $parentConstructorCallStmt = $parentConstructorCall->generate();

                $constructorNodeGenerator = new InterceptedMethodASTGenerator($constructor, [$parentConstructorCallStmt], $useTypeWidening);
                $constructorNode          = $constructorNodeGenerator->generate();
            }
            $constructorBodyStatements = array_merge($constructorBodyStatements, $constructorNode->stmts ?? []);
        } else {
            // We don't have parent constructor, no need to to call parent
            $constructorNodeBuilder = (new BuilderFactory())->method('__construct');
            $constructorNodeBuilder->addStmts($constructorBodyStatements);

            $constructorNode = $constructorNodeBuilder->getNode();
        }
        assert($constructorNode !== null, "Constructor generator should be initialized");
        $this->constructorGenerator = $constructorNode;
    }

    public function generate(): ClassMethod
    {
        $builder       = new BuilderFactory();
        $methodToBuild = $builder->method('__construct');

    }

    /**
     * Returns constructor code AST
     *
     * <pre>
     *  $accessor = function (array &$propertyStorage, self $target) {
     *      $propertyStorage = [
     *          'name' => &$target->name,
     *          'more' => &$target->more,
     *          // ...
     *      ];
     *      unset(
     *          $target->name,
     *          $target->more,
     *          // ...
     *      );
     *  };
     *  ($accessor->bindTo($this, parent::class))($this->__properties, $this);
     * </pre>
     *
     * @param array<string>&non-empty-list<string> $interceptedProperties List of properties to intercept
     *
     * @return Stmt[]
     */
    private function getConstructorBodyStatements(array $interceptedProperties): array
    {
        $builder          = new BuilderFactory();
        $accessorVariable = $builder->var('accessor');

        $accessorVarExpr = new Expression(new Assign(
            var:  $accessorVariable,
            expr: new Closure([
                'params' => [
                    $builder->param('propertyStorage')->makeByRef()->setType('array')->getNode(),
                    $builder->param('target')->setType('self')->getNode(),
                ],
                'stmts' => [
                    new Expression(new Assign(
                        var:  $builder->var('propertyStorage'),
                        expr: new Array_(
                            array_map(fn(string $propertyName): ArrayItem => new ArrayItem(
                                value: $builder->propertyFetch($builder->var('target'), $propertyName),
                                key:   $builder->val($propertyName),
                                byRef: true
                            ), $interceptedProperties)
                        )
                    )),
                    new Unset_(
                        vars: array_map(function (string $propertyName) use ($builder): PropertyFetch {
                            return $builder->propertyFetch($builder->var('target'), $propertyName);
                        }, $interceptedProperties)
                    )
                ]
            ])
        ));

        $accessorVarCallExpr = new Expression(
            $builder->funcCall(
                name: $builder->methodCall(
                    var:  $accessorVariable,
                    name: 'bindTo',
                    args: $builder->args([
                        $builder->var('this'),
                        $builder->classConstFetch('parent', 'class')
                    ])
                ),
                args: $builder->args([
                    $builder->propertyFetch($builder->var('this'), '__properties'),
                    $builder->var('this'),
                ])
            )
        );

        return [$accessorVarExpr, $accessorVarCallExpr];
    }
}
