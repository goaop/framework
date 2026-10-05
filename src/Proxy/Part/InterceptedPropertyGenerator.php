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
 * Generates an intercepted class property using native PHP 8.4 property hooks.
 *
 * For regular properties it generates both `get` and `set` hooks, which pass the values to the joinpoint by value.
 *
 * Rendered output shape:
 * <pre>
 * public string $name = 'value' {
 *     get {
 *         static $__joinPoint = InterceptorInjector::forProperty(self::class, 'name', [...]);
 *         return $__joinPoint->read($this, $this->name);
 *     }
 *     set {
 *         static $__joinPoint = InterceptorInjector::forProperty(self::class, 'name', [...]);
 *         $this->name = $__joinPoint->write($this, $value, $this->name);
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
 *         static $__joinPoint = InterceptorInjector::forProperty(self::class, 'items', [...]);
 *         return $__joinPoint->readByReference($this, $this->items);
 *     }
 * }
 * </pre>
 *
 * Hooks of typed properties without a default value check the initialization first, see
 * {@see AbstractInterceptedPropertyGenerator::createReadStatement()}.
 */
final class InterceptedPropertyGenerator extends AbstractInterceptedPropertyGenerator implements PropertyNodeProvider
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

    /**
     * Builds AST for a native property `get` hook, `&get` for array typed properties
     */
    private function createGetHook(bool $returnsByReference): PropertyHook
    {
        $fieldAccessExpression = $this->createFieldAccessInitializationExpression($this->property->getName());

        return new PropertyHook('get', [
            ...$this->getFieldAccessInitializationStatements($fieldAccessExpression),
            $this->createReadStatement($returnsByReference),
        ], ['byRef' => $returnsByReference]);
    }

    /**
     * Builds AST for a native property `set` hook
     */
    private function createSetHook(): PropertyHook
    {
        $fieldAccessExpression = $this->createFieldAccessInitializationExpression($this->property->getName());

        return new PropertyHook('set', [
            ...$this->getFieldAccessInitializationStatements($fieldAccessExpression),
            $this->createWriteStatement(),
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
            new Name($this->importedName(InterceptorInjector::class)),
            'forProperty',
            [
                new Arg(new ClassConstFetch(new Name('self'), 'class')),
                new Arg(new String_($propertyName)),
                new Arg((new InterceptorListGenerator($this->adviceNames, $this->imports))->getNode()),
            ],
        );
    }
}
