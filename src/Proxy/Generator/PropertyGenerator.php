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
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\PrettyPrinter\Standard;

/**
 * Generates a PHP class property declaration as an AST node or PHP string.
 */
final class PropertyGenerator implements PropertyNodeProvider
{
    private static ?Standard $printer      = null;
    private static ?BuilderFactory $factory = null;

    private mixed $defaultValue;
    private bool $hasDefault    = false;

    /** Pre-built AST expression node for defaults that can't be represented as PHP scalars. */
    private ?Expr $defaultExpressionNode = null;

    private ?TypeGenerator $type      = null;
    private ?DocBlockGenerator $docBlock = null;

    /** @var AttributeGroup[] */
    private array $attrGroups = [];
    /** @var list<PropertyHook> */
    private array $hooks = [];

    /**
     * @param list<PropertyModifier> $modifiers Property declaration modifiers
     */
    public function __construct(
        private readonly string $name,
        private readonly array $modifiers = [PropertyModifier::PUBLIC]
    ) {
    }

    public function setDefaultValue(mixed $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
        $this->hasDefault   = true;
    }

    /**
     * Sets the default value from an existing AST expression node.
     *
     * Used for PHP 8.5+ defaults (first-class callables, closures, arrow functions)
     * that cannot be represented as PHP scalars.
     */
    public function setDefaultExpressionNode(Expr $expression): void
    {
        $this->defaultExpressionNode = $expression;
        $this->hasDefault            = true;
    }

    public function setType(TypeGenerator $type): void
    {
        $this->type = $type;
    }

    public function setDocBlock(DocBlockGenerator $docBlock): void
    {
        $this->docBlock = $docBlock;
    }

    /**
     * Sets attribute groups to emit on the property declaration.
     *
     * @param AttributeGroup[] $attrGroups
     */
    public function addAttributeGroups(array $attrGroups): void
    {
        $this->attrGroups = $attrGroups;
    }

    public function addHook(PropertyHook $hook): void
    {
        $this->hooks[] = $hook;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the underlying AST property node.
     */
    public function getNode(): PropertyNode
    {
        $builder = self::getFactory()->property($this->name);

        // Visibility
        if ($this->hasModifier(PropertyModifier::PRIVATE)) {
            $builder->makePrivate();
        } elseif ($this->hasModifier(PropertyModifier::PROTECTED)) {
            $builder->makeProtected();
        } else {
            $builder->makePublic();
        }

        if ($this->hasModifier(PropertyModifier::STATIC)) {
            $builder->makeStatic();
        }
        if ($this->hasModifier(PropertyModifier::FINAL)) {
            $builder->makeFinal();
        }
        if ($this->hasModifier(PropertyModifier::READONLY)) {
            $builder->makeReadonly();
        }
        if ($this->hasModifier(PropertyModifier::PRIVATE_SET)) {
            $builder->makePrivateSet();
        } elseif ($this->hasModifier(PropertyModifier::PROTECTED_SET)) {
            $builder->makeProtectedSet();
        }

        if ($this->type !== null) {
            $builder->setType($this->type->getNode());
        }

        if ($this->hasDefault) {
            if ($this->defaultExpressionNode !== null) {
                // Pass the Expr node directly; BuilderHelpers::normalizeValue()
                // returns Expr nodes unchanged.
                $builder->setDefault($this->defaultExpressionNode);
            } else {
                $builder->setDefault($this->defaultValue);
            }
        }

        if ($this->docBlock !== null) {
            $builder->setDocComment($this->docBlock->generate());
        }

        foreach ($this->attrGroups as $attrGroup) {
            $builder->addAttribute($attrGroup);
        }
        foreach ($this->hooks as $hook) {
            $builder->addHook($hook);
        }

        return $builder->getNode();
    }

    /**
     * Generates the PHP property declaration as a string.
     */
    public function generate(): string
    {
        return self::getPrinter()->prettyPrint([$this->getNode()]);
    }

    private function hasModifier(PropertyModifier $modifier): bool
    {
        return in_array($modifier, $this->modifiers, true);
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
