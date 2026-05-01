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
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_ as ClassNode;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation;
use PhpParser\PrettyPrinter\Standard;
use ReflectionMethod;

/**
 * Generates a PHP class declaration as an AST node or full PHP source string.
 *
 * {@see getNode()} returns the class AST node only — suitable for direct injection
 * into another AST (e.g., append to a cloned file's statement list).
 *
 * {@see generate()} emits a full PHP source string including namespace and use statements.
 */
final class ClassGenerator implements GeneratorInterface
{
    public const FLAG_FINAL     = 0b001;
    public const FLAG_ABSTRACT  = 0b010;
    public const FLAG_READONLY  = 0b100;

    private static ?Standard $printer      = null;
    private static ?BuilderFactory $factory = null;

    private string $name;
    private ?string $namespace;
    private ?int $flags;
    private ?string $parentClass;

    /** @var string[] */
    private array $interfaces;

    /** @var PropertyNodeProvider[] */
    private array $properties;

    /** @var MethodGenerator[] */
    private array $methods;

    /** @var array<string, string|null> use => alias */
    private array $uses = [];

    /** @var string[] trait FQCNs */
    private array $traits = [];

    /** @var array{trait: string, method: string, alias: string, visibility: int}[] */
    private array $traitAliases = [];

    private ?DocBlockGenerator $docBlock = null;

    /** @var AttributeGroup[] */
    private array $attrGroups = [];

    /**
     * @param string[]               $interfaces  FQCNs of interfaces to implement
     * @param PropertyNodeProvider[] $properties
     * @param MethodGenerator[]      $methods
     */
    public function __construct(
        string $name,
        ?string $namespace,
        ?int $flags,
        ?string $parentClass,
        array $interfaces = [],
        array $properties = [],
        array $methods = [],
    ) {
        $this->name        = $name;
        $this->namespace   = $namespace;
        $this->flags       = $flags;
        $this->parentClass = $parentClass;
        $this->interfaces  = $interfaces;
        $this->properties  = $properties;
        $this->methods     = array_values($methods);
    }

    /**
     * Adds a `use` statement to the generated file.
     */
    public function addUse(string $use, ?string $alias = null): void
    {
        $this->uses[$use] = $alias;
    }

    /**
     * Adds trait FQCNs to use inside the class.
     *
     * @param string[] $traits
     */
    public function addTraits(array $traits): void
    {
        foreach ($traits as $trait) {
            if ($trait !== '') {
                $this->traits[] = $trait;
            }
        }
    }

    /**
     * Adds a trait with method aliases (e.g. `use FooTrait { greet as private __aop__greet; }`).
     *
     * @param string $traitFqcn  Fully-qualified trait name (leading backslash ok)
     * @param string $methodName Original method name in the trait
     * @param string $alias      New alias (e.g. '__aop__greet')
     * @param int    $visibility ReflectionMethod::IS_PUBLIC|IS_PROTECTED|IS_PRIVATE
     */
    public function addTraitAlias(string $traitFqcn, string $methodName, string $alias, int $visibility): void
    {
        $this->traits[]       = $traitFqcn;
        $this->traitAliases[] = [
            'trait'      => ltrim($traitFqcn, '\\'),
            'method'     => $methodName,
            'alias'      => $alias,
            'visibility' => $visibility,
        ];
    }

    public function setDocBlock(DocBlockGenerator $docBlock): void
    {
        $this->docBlock = $docBlock;
    }

    /**
     * Sets attribute groups to emit on the class declaration.
     *
     * @param AttributeGroup[] $attrGroups
     */
    public function addAttributeGroups(array $attrGroups): void
    {
        $this->attrGroups = $attrGroups;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the class AST node only — no namespace or use wrappers.
     * Suitable for direct injection into a cloned file AST.
     */
    public function getNode(): ClassNode
    {
        $builder = self::getFactory()->class($this->name);

        if (($this->flags ?? 0) & self::FLAG_FINAL) {
            $builder->makeFinal();
        }
        if (($this->flags ?? 0) & self::FLAG_ABSTRACT) {
            $builder->makeAbstract();
        }
        if (($this->flags ?? 0) & self::FLAG_READONLY) {
            $builder->makeReadonly();
        }

        foreach ($this->attrGroups as $attrGroup) {
            $builder->addAttribute($attrGroup);
        }

        if ($this->parentClass !== null && $this->parentClass !== '') {
            // Use FullyQualified when the name contains a namespace separator to avoid
            // ambiguity in the generated file's namespace context.
            $parentName = ltrim($this->parentClass, '\\');
            $parentNode = str_contains($parentName, '\\')
                ? new Name\FullyQualified($parentName)
                : $parentName;
            $builder->extend($parentNode);
        }

        foreach ($this->interfaces as $interface) {
            if ($interface !== '') {
                $ifaceName = ltrim($interface, '\\');
                $ifaceNode = str_contains($ifaceName, '\\')
                    ? new Name\FullyQualified($ifaceName)
                    : $ifaceName;
                $builder->implement($ifaceNode);
            }
        }

        // Traits (always use FQN to avoid namespace ambiguity)
        if (!empty($this->traits)) {
            // Collect unique trait names (preserving order of first occurrence)
            $seen       = [];
            $traitFqcns = [];
            foreach ($this->traits as $trait) {
                $normalized = ltrim($trait, '\\');
                if (!isset($seen[$normalized])) {
                    $seen[$normalized] = true;
                    $traitFqcns[]      = $normalized;
                }
            }

            // Build adaptations for all aliases
            $adaptations = [];
            foreach ($this->traitAliases as $info) {
                $traitNameNode   = $this->resolveTraitName($info['trait']);
                $adaptations[] = new TraitUseAdaptation\Alias(
                    $traitNameNode,
                    new Identifier($info['method']),
                    $this->mapVisibility($info['visibility']),
                    new Identifier($info['alias'])
                );
            }

            $traitNames = array_map(
                fn(string $t) => $this->resolveTraitName(ltrim($t, '\\')),
                $traitFqcns
            );
            $builder->addStmt(new TraitUse($traitNames, $adaptations));
        }

        foreach ($this->properties as $property) {
            $builder->addStmt($property->getNode());
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
     * Generates the full PHP source: namespace declaration, use statements, and class.
     */
    public function generate(): string
    {
        $stmts = [];

        if ($this->namespace !== null && $this->namespace !== '') {
            $stmts[] = self::getFactory()->namespace($this->namespace)->getNode();
        }

        foreach ($this->uses as $use => $alias) {
            $useBuilder = self::getFactory()->use($use);
            if ($alias !== null) {
                $useBuilder->as($alias);
            }
            $stmts[] = $useBuilder->getNode();
        }

        $stmts[] = $this->getNode();

        return self::getPrinter()->prettyPrint($stmts);
    }

    /**
     * Resolves a trait FQCN to a Name AST node, using a relative (unqualified) name when
     * the trait resides in the same namespace as the proxy class. This keeps the generated
     * code readable: `use FooTrait` instead of `use \Ns\FooTrait`.
     */
    private function resolveTraitName(string $traitFqcn): Name
    {
        $normalized = ltrim($traitFqcn, '\\');
        if ($this->namespace !== null && $this->namespace !== '' && str_starts_with($normalized, $this->namespace . '\\')) {
            // Trait is in the same namespace — use just the short name
            return new Name(substr($normalized, strlen($this->namespace) + 1));
        }

        return str_contains($normalized, '\\')
            ? new Name\FullyQualified($normalized)
            : new Name($normalized);
    }

    /**
     * Maps ReflectionMethod visibility flag to PhpParser Modifiers constant.
     * ReflectionMethod::IS_PUBLIC = 1, IS_PROTECTED = 2, IS_PRIVATE = 4 match Modifiers directly.
     */
    private function mapVisibility(int $visibility): int
    {
        return match (true) {
            (bool) ($visibility & ReflectionMethod::IS_PRIVATE)   => Modifiers::PRIVATE,
            (bool) ($visibility & ReflectionMethod::IS_PROTECTED) => Modifiers::PROTECTED,
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
