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

use Go\Proxy\Generator\AttributeGroupsGenerator;
use Go\Proxy\Generator\PropertyGenerator;
use Go\Proxy\Generator\PropertyNodeProvider;
use Go\Proxy\Generator\TypeGenerator;
use InvalidArgumentException;
use PhpParser\Comment\Doc;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Property;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

abstract class AbstractInterceptedPropertyGenerator implements PropertyNodeProvider
{
    public function __construct(protected readonly ReflectionProperty $property)
    {
        if ($this->property->isStatic() || $this->property->isReadOnly() || $this->property->hasHooks()) {
            // Properties with existing hooks cannot be intercepted. The framework converts
            // the original class to a trait and redeclares intercepted properties with
            // get/set hooks in the proxy class. PHP 8.4 does not support conflict resolution
            // for hooked properties in traits (Fatal error: "Conflict resolution between
            // hooked properties is currently not supported"), so we cannot keep the original
            // hooks in the trait and override them in the proxy. Extracting hook bodies into
            // helper methods is theoretically possible but would break the woven-file line
            // number invariant required for XDebug compatibility and adds disproportionate
            // complexity for a niche use case. See https://github.com/goaop/framework/issues/561
            throw new InvalidArgumentException(sprintf(
                'Property %s::$%s cannot be intercepted with native hooks',
                $this->property->getDeclaringClass()->getName(),
                $this->property->getName()
            ));
        }
    }

    protected function createBasePropertyGenerator(): PropertyGenerator
    {
        $generator = new PropertyGenerator($this->property->getName(), $this->getPropertyFlags());
        if ($this->property->hasType()) {
            $generator->setType(TypeGenerator::fromReflectionType($this->property->getType()));
        }
        if ($this->property->hasDefaultValue()) {
            $generator->setDefaultValue($this->property->getDefaultValue());
        }

        $attributeGroups = AttributeGroupsGenerator::fromReflectionAttributes($this->property->getAttributes());
        if ($attributeGroups !== []) {
            $generator->addAttributeGroups($attributeGroups);
        }

        return $generator;
    }

    protected function isArrayTypedProperty(): bool
    {
        $type = $this->property->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === 'array';
        }
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() === 'array') {
                    return true;
                }
            }
        }

        return false;
    }

    protected function hasPotentiallyUninitializedTypedProperty(): bool
    {
        return $this->property->hasType() && !$this->property->hasDefaultValue();
    }

    protected function createFieldAccessDocComment(string $variableName = 'fieldAccess', bool $isNullable = false): Doc
    {
        $nullableSuffix = $isNullable ? '|null' : '';
        return new Doc('/** @var FieldAccess<self, ' . $this->getPropertyTypeForPhpDoc() . '>' . $nullableSuffix . ' $' . $variableName . ' */');
    }

    private function getPropertyTypeForPhpDoc(): string
    {
        // Use the raw AST type node when available (goaop/parser-reflection) to preserve keyword
        // types like 'self' and 'parent' as declared — bypassing PHP 8.5+ FQCN resolution.
        if (method_exists($this->property, 'getTypeNode')) {
            $typeNode = $this->property->getTypeNode();
            // getTypeNode() returns Property for regular properties or Param for constructor-promoted ones.
            if ($typeNode instanceof Property || $typeNode instanceof Param) {
                return TypeGenerator::renderAstTypeForPhpDoc($typeNode->type);
            }
        }

        return $this->renderTypeForPhpDoc($this->property->getType());
    }

    private function renderTypeForPhpDoc(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $this->normalizeNamedTypeForPhpDoc($type);

            if ($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null') {
                return '?' . $name;
            }

            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map($this->renderTypeForPhpDoc(...), $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map($this->renderTypeForPhpDoc(...), $type->getTypes()));
        }

        return 'mixed';
    }

    private function normalizeNamedTypeForPhpDoc(ReflectionNamedType $type): string
    {
        $typeName = $type->getName();
        if ($type->isBuiltin() || in_array($typeName, ['self', 'static', 'parent'], true)) {
            return $typeName;
        }

        return str_starts_with($typeName, '\\') ? $typeName : '\\' . $typeName;
    }

    private function getPropertyFlags(): int
    {
        $flags = 0;
        if ($this->property->isPrivate()) {
            $flags |= PropertyGenerator::FLAG_PRIVATE;
        } elseif ($this->property->isProtected()) {
            $flags |= PropertyGenerator::FLAG_PROTECTED;
        } else {
            $flags |= PropertyGenerator::FLAG_PUBLIC;
        }
        if ($this->property->isFinal()) {
            $flags |= PropertyGenerator::FLAG_FINAL;
        }

        if ($this->property->isPrivateSet()) {
            $flags |= PropertyGenerator::FLAG_PRIVATE_SET;
        } elseif ($this->property->isProtectedSet()) {
            $flags |= PropertyGenerator::FLAG_PROTECTED_SET;
        }

        return $flags;
    }
}
