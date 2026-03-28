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
use PhpParser\Modifiers;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Enum_ as EnumNode;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation;
use PhpParser\PrettyPrinter\Standard;
use ReflectionMethod;

/**
 * Generates a PHP enum declaration (backed or pure) as an AST node or full PHP source string.
 *
 * {@see getNode()} returns the enum AST node only — suitable for direct injection
 * into another AST.
 *
 * {@see generate()} emits a full PHP source string including namespace and use statements.
 *
 * Note: PHP enums cannot have properties (static or instance). Joinpoint state must live
 * inside each method body as a `static $__joinPoint` variable (TraitProxyGenerator pattern).
 */
final class EnumGenerator implements GeneratorInterface
{
    private static ?Standard $printer      = null;
    private static ?BuilderFactory $factory = null;

    private string $name;
    private ?string $namespace;
    private ?string $backingType;

    /** @var string[] FQCNs of interfaces to implement */
    private array $interfaces;

    /** @var MethodGenerator[] */
    private array $methods;

    /** @var array<string, string|null> use => alias */
    private array $uses = [];

    /** @var string[] trait FQCNs */
    private array $traits = [];

    /** @var array{trait: string, method: string, alias: string, visibility: int}[] */
    private array $traitAliases = [];

    /** @var array{name: string, value: string|int|null}[] */
    private array $enumCases = [];

    /**
     * @param string[]          $interfaces FQCNs of interfaces to implement
     * @param MethodGenerator[] $methods
     */
    public function __construct(
        string $name,
        ?string $namespace,
        ?string $backingType,
        array $interfaces = [],
        array $methods = [],
    ) {
        $this->name        = $name;
        $this->namespace   = $namespace;
        $this->backingType = $backingType;
        $this->interfaces  = $interfaces;
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
     * Adds an enum case to the generated enum.
     *
     * @param string|int|null $value The case value (null for pure/unit enum cases)
     */
    public function addEnumCase(string $name, string|int|null $value = null): void
    {
        $this->enumCases[] = ['name' => $name, 'value' => $value];
    }

    /**
     * Adds trait FQCNs to use inside the enum.
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

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the enum AST node only — no namespace or use wrappers.
     */
    public function getNode(): EnumNode
    {
        $stmts = [];

        // Trait use block with all aliases
        if (!empty($this->traits)) {
            $seen       = [];
            $traitFqcns = [];
            foreach ($this->traits as $trait) {
                $normalized = ltrim($trait, '\\');
                if (!isset($seen[$normalized])) {
                    $seen[$normalized] = true;
                    $traitFqcns[]      = $normalized;
                }
            }

            $adaptations = [];
            foreach ($this->traitAliases as $info) {
                $traitNameNode = str_contains($info['trait'], '\\')
                    ? new Name\FullyQualified($info['trait'])
                    : new Name($info['trait']);
                $adaptations[] = new TraitUseAdaptation\Alias(
                    $traitNameNode,
                    new Identifier($info['method']),
                    $this->mapVisibility($info['visibility']),
                    new Identifier($info['alias'])
                );
            }

            $traitNames = array_map(
                static fn(string $t) => str_contains($t, '\\')
                    ? new Name\FullyQualified($t)
                    : new Name($t),
                $traitFqcns
            );
            $stmts[] = new TraitUse($traitNames, $adaptations);
        }

        // Enum cases
        foreach ($this->enumCases as $caseInfo) {
            $caseBuilder = self::getFactory()->enumCase($caseInfo['name']);
            if ($caseInfo['value'] !== null) {
                $caseBuilder->setValue($caseInfo['value']);
            }
            $stmts[] = $caseBuilder->getNode();
        }

        // Methods
        foreach ($this->methods as $method) {
            $stmts[] = $method->getNode();
        }

        // Build interface list — always use FullyQualified so that unqualified global
        // interface names (e.g. 'Stringable') are not mis-resolved in namespaced files.
        $implements = [];
        foreach ($this->interfaces as $interface) {
            if ($interface !== '') {
                $implements[] = new Name\FullyQualified(ltrim($interface, '\\'));
            }
        }

        return new EnumNode($this->name, [
            'scalarType' => $this->backingType !== null ? new Identifier($this->backingType) : null,
            'implements' => $implements,
            'stmts'      => $stmts,
        ]);
    }

    /**
     * Generates the full PHP source: namespace declaration, use statements, and enum.
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
     * Maps ReflectionMethod visibility flag to PhpParser Modifiers constant.
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
