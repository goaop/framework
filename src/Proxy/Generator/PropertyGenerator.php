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
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\PrettyPrinter\Standard;

/**
 * Generates a PHP class property declaration as an AST node or PHP string.
 */
final class PropertyGenerator implements PropertyNodeProvider
{
    public const FLAG_PUBLIC    = 0b0001;
    public const FLAG_PROTECTED = 0b0010;
    public const FLAG_PRIVATE   = 0b0100;
    public const FLAG_STATIC    = 0b1000;

    private static ?Standard $printer      = null;
    private static ?BuilderFactory $factory = null;

    private string $name;
    private int $flags;
    private mixed $defaultValue;
    private bool $hasDefault;
    private ?TypeGenerator $type      = null;
    private ?DocBlockGenerator $docBlock = null;

    public function __construct(string $name, mixed $defaultValue = null, int $flags = self::FLAG_PUBLIC)
    {
        $this->name         = $name;
        $this->flags        = $flags;
        $this->defaultValue = $defaultValue;
        $this->hasDefault   = func_num_args() > 1;
    }

    public function setType(TypeGenerator $type): void
    {
        $this->type = $type;
    }

    public function setDocBlock(DocBlockGenerator $docBlock): void
    {
        $this->docBlock = $docBlock;
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
        if ($this->flags & self::FLAG_PRIVATE) {
            $builder->makePrivate();
        } elseif ($this->flags & self::FLAG_PROTECTED) {
            $builder->makeProtected();
        } else {
            $builder->makePublic();
        }

        if ($this->flags & self::FLAG_STATIC) {
            $builder->makeStatic();
        }

        if ($this->type !== null) {
            $builder->setType($this->type->getNode());
        }

        if ($this->hasDefault) {
            $builder->setDefault($this->defaultValue);
        }

        if ($this->docBlock !== null) {
            $builder->setDocComment($this->docBlock->generate());
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
