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

namespace Go\Proxy\Generator;

use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Modifiers;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\Node\Stmt\Trait_ as TraitNode;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation;
use PhpParser\PrettyPrinter\Standard;

/**
 * Generates a PHP trait declaration as an AST node or full PHP source string.
 *
 * {@see getNode()} returns the trait AST node only — suitable for direct injection
 * into another AST.
 *
 * {@see generate()} emits a full PHP source string including namespace declaration.
 */
final class TraitGenerator implements GeneratorInterface
{
    private static ?Standard $printer      = null;
    private static ?BuilderFactory $factory = null;

    private string $name;
    private ?string $namespace;

    /** @var MethodGenerator[] */
    private array $methods;

    /** @var PropertyNode[] */
    private array $properties;

    private ?DocBlockGenerator $docBlock = null;

    /** @var string[] used trait FQCNs */
    private array $usedTraits = [];

    /** @var array{trait: string, method: string, alias: string, visibility: int}[] */
    private array $traitAliases = [];

    /**
     * @param MethodGenerator[] $methods
     * @param PropertyNode[] $properties
     */
    public function __construct(
        string $name,
        ?string $namespace,
        array $methods = [],
        ?DocBlockGenerator $docBlock = null,
        array $properties = [],
    ) {
        $this->name      = $name;
        $this->namespace = $namespace;
        $this->methods   = array_values($methods);
        $this->docBlock  = $docBlock;
        $this->properties = array_values($properties);
    }

    /**
     * Adds a trait to use inside this trait.
     */
    public function addTrait(string $traitName): void
    {
        $this->usedTraits[] = $traitName;
    }

    /**
     * Adds an alias adaptation for a used trait's method.
     *
     * @param string $traitAndMethod  e.g. "ParentTrait::methodName"
     * @param string $alias           e.g. "__aop__methodName"
     * @param int    $visibility      ReflectionMethod::IS_PUBLIC|IS_PROTECTED|IS_PRIVATE (maps directly to Modifiers)
     */
    public function addTraitAlias(string $traitAndMethod, string $alias, int $visibility): void
    {
        [$traitName, $methodName] = explode('::', $traitAndMethod, 2);
        $this->traitAliases[] = [
            'trait'      => $traitName,
            'method'     => $methodName,
            'alias'      => $alias,
            'visibility' => $visibility,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the trait AST node only — no namespace wrapper.
     * Suitable for direct injection into a cloned file AST.
     */
    public function getNode(): TraitNode
    {
        $builder = self::getFactory()->trait($this->name);

        // Build TraitUse nodes grouped by trait name
        $traitUseAdaptations = [];
        foreach ($this->traitAliases as $aliasInfo) {
            $traitUseAdaptations[] = new TraitUseAdaptation\Alias(
                new Name($aliasInfo['trait']),
                new \PhpParser\Node\Identifier($aliasInfo['method']),
                $this->mapVisibility($aliasInfo['visibility']),
                new \PhpParser\Node\Identifier($aliasInfo['alias'])
            );
        }

        if (!empty($this->usedTraits)) {
            $traitNames = array_map(
                static fn(string $t) => new Name($t),
                $this->usedTraits
            );
            $builder->addStmt(new TraitUse($traitNames, $traitUseAdaptations));
        }

        foreach ($this->properties as $property) {
            $builder->addStmt($property);
        }

        foreach ($this->methods as $method) {
            $builder->addStmt($method->getNode());
        }

        $node = $builder->getNode();

        if ($this->docBlock !== null) {
            $node->setAttribute('comments', [new Doc($this->docBlock->generate())]);
        }

        return $node;
    }

    /**
     * Generates the full PHP source: namespace declaration and trait.
     */
    public function generate(): string
    {
        $stmts = [];

        if ($this->namespace !== null && $this->namespace !== '') {
            $stmts[] = self::getFactory()->namespace($this->namespace)->getNode();
        }

        $stmts[] = $this->getNode();

        return self::getPrinter()->prettyPrint($stmts);
    }

    /**
     * Maps ReflectionMethod visibility flag to PhpParser Modifiers constant.
     * ReflectionMethod::IS_PUBLIC = 1, IS_PROTECTED = 2, IS_PRIVATE = 4 match Modifiers directly.
     */
    private function mapVisibility(int $visibility): int
    {
        return match (true) {
            (bool)($visibility & \ReflectionMethod::IS_PRIVATE)   => Modifiers::PRIVATE,
            (bool)($visibility & \ReflectionMethod::IS_PROTECTED) => Modifiers::PROTECTED,
            default                                                 => Modifiers::PUBLIC,
        };
    }

    private static function getPrinter(): Standard
    {
        if (self::$printer === null) {
            self::$printer = new Standard(['shortArraySyntax' => true]);
        }
        return self::$printer;
    }

    private static function getFactory(): BuilderFactory
    {
        if (self::$factory === null) {
            self::$factory = new BuilderFactory();
        }
        return self::$factory;
    }
}
