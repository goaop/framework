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
use Go\Proxy\Generator\PropertyModifier;
use Go\Proxy\Generator\PropertyNodeProvider;
use Go\Proxy\Generator\TypeGenerator;
use InvalidArgumentException;
use LogicException;
use PhpParser\Comment\Doc;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
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
        $generator = new PropertyGenerator($this->property->getName(), $this->getPropertyModifiers());
        if ($this->property->hasType()) {
            $generator->setType(TypeGenerator::fromReflectionType($this->property->getType()));
        }
        // When parser-reflection is loaded, prefer the raw AST default node over
        // getDefaultValue(). This avoids parser-reflection bugs where getDefaultValue()
        // crashes (uninitialized typed property for FCC) or returns null (Closure defaults),
        // and correctly handles scalars, arrays, and FCC expressions uniformly.
        // The AST node is also the only source for promoted constructor property defaults:
        // reflection reports hasDefaultValue() === false for them because the default
        // formally belongs to the constructor parameter (see issue #599), but the proxy
        // property must carry it so that the demoted woven trait plus proxy behave like
        // the original promoted declaration.
        $astDefault = $this->getAstDefaultNode();
        if ($astDefault !== null) {
            $generator->setDefaultExpressionNode($astDefault);
        } elseif ($this->property->hasDefaultValue() && !method_exists($this->property, 'getNode')) {
            $rawDefault = $this->property->getDefaultValue();
            if ($rawDefault instanceof \Closure) {
                throw new LogicException(sprintf(
                    'Cannot generate proxy for property %s::$%s: PHP 8.5 Closure default values '
                    . 'require goaop/parser-reflection for AST access.',
                    $this->property->getDeclaringClass()->getName(),
                    $this->property->getName()
                ));
            }
            $generator->setDefaultValue($rawDefault);
        }

        $attributeGroups = AttributeGroupsGenerator::fromReflector($this->property);
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
            return array_any(
                $type->getTypes(),
                fn($unionType): bool => $unionType instanceof ReflectionNamedType && $unionType->getName() === 'array'
            );
        }

        return false;
    }

    protected function hasPotentiallyUninitializedTypedProperty(): bool
    {
        return $this->property->hasType()
            && !$this->property->hasDefaultValue()
            && $this->getAstDefaultNode() === null;
    }

    /**
     * Returns the raw AST default value expression when parser-reflection is loaded.
     *
     * For promoted constructor properties the default lives on the Param node while
     * reflection's hasDefaultValue() reports false, so the AST node is authoritative.
     *
     * A default containing a `new` expression is never returned (issue #616): `new` is legal
     * in a constructor parameter default but illegal in a property initializer, so copying it
     * onto the proxy hook property would be a compile error ("New expressions are not
     * supported in this context"). The hook property then stays uninitialized — the
     * constructor assignment injected by the promoted-parameter demotion supplies the value,
     * and the isInitialized() guard in the get hook covers the pre-construction window.
     */
    private function getAstDefaultNode(): ?\PhpParser\Node\Expr
    {
        if (!method_exists($this->property, 'getNode')) {
            return null;
        }
        $astNode = $this->property->getNode();

        $default = ($astNode instanceof PropertyItem || $astNode instanceof Param)
            ? $astNode->default
            : null;

        if ($default !== null
            && (new NodeFinder())->findFirstInstanceOf([$default], New_::class) !== null) {
            return null;
        }

        return $default;
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

    /**
     * @return list<PropertyModifier>
     */
    private function getPropertyModifiers(): array
    {
        $modifiers = [];
        if ($this->property->isPrivate()) {
            $modifiers[] = PropertyModifier::PRIVATE;
        } elseif ($this->property->isProtected()) {
            $modifiers[] = PropertyModifier::PROTECTED;
        } else {
            $modifiers[] = PropertyModifier::PUBLIC;
        }
        if ($this->property->isFinal()) {
            $modifiers[] = PropertyModifier::FINAL;
        }

        if ($this->property->isPrivateSet()) {
            $modifiers[] = PropertyModifier::PRIVATE_SET;
        } elseif ($this->property->isProtectedSet()) {
            $modifiers[] = PropertyModifier::PROTECTED_SET;
        }

        return $modifiers;
    }
}
