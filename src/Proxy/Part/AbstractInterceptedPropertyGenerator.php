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

    protected function createFieldAccessDocComment(): Doc
    {
        return new Doc('/** @var \Go\Aop\Intercept\FieldAccess<self, ' . $this->getPropertyTypeForPhpDoc() . '> $fieldAccess */');
    }

    private function getPropertyTypeForPhpDoc(): string
    {
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
