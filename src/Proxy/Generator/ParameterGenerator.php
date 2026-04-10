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

use Go\ParserReflection\Resolver\TypeExpressionResolver;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use ReflectionAttribute;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Generates a PHP function/method parameter declaration as an AST node or PHP string.
 */
final class ParameterGenerator
{
    private static ?Standard $printer     = null;
    private static ?BuilderFactory $factory = null;

    private string $name;
    private ?TypeGenerator $type;
    private bool $byRef;
    private bool $variadic;
    private ?ValueGenerator $defaultValue;

    /** @var ReflectionAttribute<object>[] */
    private array $reflectionAttributes = [];

    public function __construct(
        string $name,
        ?TypeGenerator $type = null,
        bool $byRef = false,
        bool $variadic = false,
        ?ValueGenerator $defaultValue = null,
    ) {
        $this->name         = $name;
        $this->type         = $type;
        $this->byRef        = $byRef;
        $this->variadic     = $variadic;
        $this->defaultValue = $defaultValue;
    }

    /**
     * Creates a ParameterGenerator from a reflection parameter.
     *
     * @param bool $useWidening When true, type declarations are omitted (for parameter widening)
     */
    public static function fromReflection(ReflectionParameter $param, bool $useWidening = false): self
    {
        $type         = null;
        $defaultValue = null;

        if (!$useWidening && $param->hasType()) {
            // If the parameter exposes its AST node (Go\ParserReflection\ReflectionParameter),
            // re-process the raw type node with TypeExpressionResolver(null, null) so that
            // 'self' and 'parent' keywords are preserved without PHP 8.5+ name resolution,
            // while regular class names are still fully qualified via resolvedName attributes.
            if (method_exists($param, 'getNode')) {
                /** @var Node\Param $astParam */
                $astParam = $param->getNode();
                $typeNode  = $astParam->type;
                if ($typeNode !== null) {
                    $typeResolver = new TypeExpressionResolver();
                    $typeResolver->process($typeNode, false);
                    $resolvedType = $typeResolver->getType();
                    if ($resolvedType !== null) {
                        $type = TypeGenerator::fromReflectionType($resolvedType);
                    }
                }
            } else {
                $reflectionType = $param->getType();
                if ($reflectionType instanceof ReflectionNamedType) {
                    $typeName = TypeGenerator::resolveReflectionNamedTypeName($reflectionType);
                    $nullable = $reflectionType->allowsNull() && $typeName !== 'mixed' && $typeName !== 'null';
                    $type     = TypeGenerator::fromTypeString(($nullable ? '?' : '') . $typeName);
                } else {
                    $type = TypeGenerator::fromReflectionType($reflectionType);
                }
            }
        }

        if (!$param->isVariadic()) {
            if ($param->isDefaultValueAvailable()) {
                $defaultValue = new ValueGenerator($param->getDefaultValue());
            } elseif ($param->isOptional()) {
                $defaultValue = new ValueGenerator(null);
            }
        }

        $generator = new self(
            $param->getName(),
            $type,
            $param->isPassedByReference(),
            $param->isVariadic(),
            $defaultValue,
        );

        $generator->reflectionAttributes = $param->getAttributes();

        return $generator;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?TypeGenerator
    {
        return $this->type;
    }

    public function getPassedByReference(): bool
    {
        return $this->byRef;
    }

    public function getVariadic(): bool
    {
        return $this->variadic;
    }

    public function getDefaultValue(): ?ValueGenerator
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(ValueGenerator $value): void
    {
        $this->defaultValue = $value;
    }

    /**
     * Returns the underlying AST parameter node.
     */
    public function getNode(): Node\Param
    {
        $builder = self::getFactory()->param($this->name);

        if ($this->type !== null) {
            $builder->setType($this->type->getNode());
        }
        if ($this->byRef) {
            $builder->makeByRef();
        }
        if ($this->variadic) {
            $builder->makeVariadic();
        }
        if ($this->defaultValue !== null) {
            $builder->setDefault($this->defaultValue->getNode());
        }

        foreach (AttributeGroupsGenerator::fromReflectionAttributes($this->reflectionAttributes) as $attrGroup) {
            $builder->addAttribute($attrGroup);
        }

        return $builder->getNode();
    }

    /**
     * Generates the PHP parameter declaration string.
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
