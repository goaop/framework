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
    public const int FLAG_FINAL     = 0b001;
    public const int FLAG_ABSTRACT  = 0b010;
    public const int FLAG_READONLY  = 0b100;

    private static ?Standard $printer      = null;
    private static ?BuilderFactory $factory = null;

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
    /**
     * @param string[]               $interfaces
     * @param PropertyNodeProvider[] $properties
     * @param MethodGenerator[]      $methods
     */
    public function __construct(
        private readonly string $name,
        private readonly ?string $namespace,
        private readonly ?int $flags,
        private readonly ?string $parentClass,
        private readonly array $interfaces = [],
        private readonly array $properties = [],
        array $methods = [],
    ) {
        $this->methods = array_values($methods);
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

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Builds the AST name node for a class-like reference (parent, interface,
     * trait, alias) with a single universal rule: explicitly rooted
     * ("\Stringable") and multi-segment names are fully qualified; a bare
     * short name resolves in the generated class's own namespace (e.g. the
     * Foo__AopProxied body trait). Global-namespace names coming from
     * ::class constants are rooted upstream (AdviceMatcher, proxy generators)
     * before they reach this generator.
     */
    private static function classNameNode(string $name): Name
    {
        $normalized = ltrim($name, '\\');

        return str_starts_with($name, '\\') || str_contains($normalized, '\\')
            ? new Name\FullyQualified($normalized)
            : new Name($normalized);
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
            $builder->extend(self::classNameNode($this->parentClass));
        }

        foreach ($this->interfaces as $interface) {
            if ($interface !== '') {
                $builder->implement(self::classNameNode($interface));
            }
        }

        if (!empty($this->traits)) {
            // Collect unique trait names (preserving order of first occurrence)
            $seen       = [];
            $traitNames = [];
            foreach ($this->traits as $trait) {
                $normalized = ltrim($trait, '\\');
                if (!isset($seen[$normalized])) {
                    $seen[$normalized] = true;
                    $traitNames[]      = self::classNameNode($trait);
                }
            }

            // Build adaptations for all aliases
            $adaptations = [];
            foreach ($this->traitAliases as $info) {
                $adaptations[] = new TraitUseAdaptation\Alias(
                    self::classNameNode($info['trait']),
                    new Identifier($info['method']),
                    $this->mapVisibility($info['visibility']),
                    new Identifier($info['alias'])
                );
            }

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
    #[\Override]
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
