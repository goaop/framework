<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

use PhpParser\BuilderFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_ as ClassNode;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation;
use PhpParser\PrettyPrinter\Standard;

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
    public const FLAG_FINAL    = 0b01;
    public const FLAG_ABSTRACT = 0b10;

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

        if ($this->flags & self::FLAG_FINAL) {
            $builder->makeFinal();
        }
        if ($this->flags & self::FLAG_ABSTRACT) {
            $builder->makeAbstract();
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
            $traitNames = [];
            foreach ($this->traits as $trait) {
                $traitName    = ltrim($trait, '\\');
                $traitNames[] = str_contains($traitName, '\\')
                    ? new Name\FullyQualified($traitName)
                    : new Name($traitName);
            }
            $builder->addStmt(new TraitUse($traitNames));
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
