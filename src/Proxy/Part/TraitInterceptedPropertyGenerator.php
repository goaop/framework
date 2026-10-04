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

use Go\Aop\Framework\GeneratedInterceptor;
use Go\Aop\Framework\InterceptorInjector;
use Go\Proxy\Generator\InterceptorListGenerator;
use Go\Proxy\Generator\PropertyNodeProvider;
use Go\Proxy\Generator\ProxyImports;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\StaticVar;
use ReflectionProperty;

/**
 * Generates intercepted property hooks for trait proxies.
 *
 * Each hook lazily creates and caches its own static $__joinPoint.
 */
final class TraitInterceptedPropertyGenerator extends AbstractInterceptedPropertyGenerator implements PropertyNodeProvider
{
    /**
     * @param list<GeneratedInterceptor|string> $adviceNames
     * @param ProxyImports|null                 $imports     Imports of the generated file
     */
    public function __construct(
        ReflectionProperty $property,
        private readonly array $adviceNames,
        ?ProxyImports $imports = null,
    ) {
        parent::__construct($property, $imports);
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
        return new PropertyHook('get', [
            ...$this->getFieldAccessInitializationStatements(),
            $this->createReadStatement($returnsByReference),
        ], ['byRef' => $returnsByReference]);
    }

    private function createSetHook(): PropertyHook
    {
        return new PropertyHook('set', [
            ...$this->getFieldAccessInitializationStatements(),
            $this->createWriteStatement(),
        ]);
    }

    /**
     * @return array<int, Node\Stmt>
     */
    private function getFieldAccessInitializationStatements(): array
    {
        $propertyName = $this->property->getName();

        $initExpression = new StaticCall(
            new Name($this->importedName(InterceptorInjector::class)),
            'forProperty',
            [
                new Arg(new ClassConstFetch(new Name('self'), 'class')),
                new Arg(new String_($propertyName)),
                new Arg((new InterceptorListGenerator($this->adviceNames, $this->imports))->getNode()),
            ],
        );

        $joinPointStaticVar = new Static_([new StaticVar(new Variable('__joinPoint'), $initExpression)]);
        $joinPointStaticVar->setDocComment($this->createFieldAccessDocComment('__joinPoint', false));

        return [$joinPointStaticVar];
    }

}
