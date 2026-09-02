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

    private bool $hasDefault = false;

    /** Default value to emit; any write (including null) marks the property as having a default. */
    public mixed $defaultValue {
        set {
            $this->defaultValue = $value;
            $this->hasDefault   = true;
        }
    }

    /**
     * Pre-built AST expression node for defaults that can't be represented as PHP scalars.
     *
     * Used for PHP 8.5+ defaults (first-class callables, closures, arrow functions);
     * writing a node marks the property as having a default.
     */
    public ?Expr $defaultExpressionNode = null {
        set {
            $this->defaultExpressionNode = $value;
            if ($value !== null) {
                $this->hasDefault = true;
            }
        }
    }

    public ?TypeGenerator $type = null;
    public ?DocBlockGenerator $docBlock = null;

    /**
     * Attribute groups to emit on the property declaration.
     *
     * @var AttributeGroup[]
     */
    public array $attrGroups = [];

    /** @var list<PropertyHook> */
    private array $hooks = [];

    /**
     * @param list<PropertyModifier> $modifiers Property declaration modifiers
     */
    public function __construct(
        public readonly string $name,
        private readonly array $modifiers = [PropertyModifier::PUBLIC]
    ) {
    }

    public function addHook(PropertyHook $hook): void
    {
        $this->hooks[] = $hook;
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
