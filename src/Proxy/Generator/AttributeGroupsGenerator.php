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

use LogicException;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Converts PHP reflection attributes to PhpParser AttributeGroup AST nodes.
 *
 * Used by generator classes to propagate PHP 8+ attributes (#[...]) from
 * original class/method/parameter/property/function declarations into
 * generated proxy code, so that runtime attribute inspection on proxy objects
 * returns the same attributes as on the original class.
 */
final class AttributeGroupsGenerator
{
    private static ?BuilderFactory $factory = null;

    /**
     * Converts the attributes of a reflection element to PhpParser AttributeGroup nodes.
     *
     * When the reflection element exposes its AST node (Go\ParserReflection classes
     * used during weaving), attribute name and argument expressions are cloned from
     * the original AST verbatim. This never evaluates argument expressions, so
     * non-scalar arguments (enum cases, new-in-initializer objects, closures in
     * constant expressions) and environment-dependent constants (PHP_INT_MAX)
     * are preserved exactly as written in the source (see issues #601 and #602).
     *
     * For native reflection the value-based {@see fromReflectionAttributes} fallback
     * is used instead.
     *
     * @param ReflectionClass<covariant object>|ReflectionFunctionAbstract|ReflectionParameter|ReflectionProperty $reflection
     *
     * @return list<AttributeGroup>
     */
    public static function fromReflector(
        ReflectionClass|ReflectionFunctionAbstract|ReflectionParameter|ReflectionProperty $reflection
    ): array {
        $node = null;

        // Duck-typed check for Go\ParserReflection classes exposing their AST node.
        // For properties the attribute groups live on the Property statement (or the
        // promoted constructor Param), not on the PropertyItem returned by getNode().
        if ($reflection instanceof ReflectionProperty && method_exists($reflection, 'getTypeNode')) {
            $node = $reflection->getTypeNode();
        } elseif (method_exists($reflection, 'getNode')) {
            $node = $reflection->getNode();
        }

        if ($node instanceof ClassLike
            || $node instanceof ClassMethod
            || $node instanceof Function_
            || $node instanceof Param
            || $node instanceof Property
        ) {
            return self::fromAttributeGroupNodes($node->attrGroups);
        }

        return self::fromReflectionAttributes($reflection->getAttributes());
    }

    /**
     * Clones original AttributeGroup AST nodes for use in generated proxy code.
     *
     * Each original attribute produces one AttributeGroup (one #[...] line) with:
     *  - the attribute name resolved to its fully-qualified form (via the resolvedName
     *    attribute set by parser-reflection's NameResolver, falling back to the name
     *    as written);
     *  - argument expressions deep-cloned as-is, with any resolved class/function/const
     *    names inside them fully qualified, so the expressions keep their meaning in
     *    the generated proxy context and are never constant-folded.
     *
     * @param AttributeGroup[] $attrGroups
     *
     * @return list<AttributeGroup>
     */
    public static function fromAttributeGroupNodes(array $attrGroups): array
    {
        $groups = [];

        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $groups[] = new AttributeGroup([self::cloneAttribute($attr)]);
            }
        }

        return $groups;
    }

    /**
     * Converts an array of reflection attributes to PhpParser AttributeGroup nodes
     * using their evaluated argument values (native reflection fallback).
     *
     * Attribute names are always emitted as fully-qualified to avoid namespace
     * ambiguity inside generated proxy namespaces. Attributes whose argument values
     * cannot be represented as PHP code (e.g. arbitrary objects from new-in-initializer
     * expressions) are skipped best-effort instead of aborting the whole generation.
     *
     * @param ReflectionAttribute<object>[] $reflectionAttributes
     *
     * @return list<AttributeGroup>
     */
    public static function fromReflectionAttributes(array $reflectionAttributes): array
    {
        if (empty($reflectionAttributes)) {
            return [];
        }

        $factory = self::getFactory();
        $groups  = [];

        foreach ($reflectionAttributes as $attr) {
            // Always use FullyQualified names to avoid resolution issues inside
            // the generated proxy's namespace context
            $fqName = new Name\FullyQualified(ltrim($attr->getName(), '\\'));

            try {
                // BuilderFactory::args() handles named args (string key → Arg::$name)
                // and normalises PHP scalar/array/enum values to AST Expr nodes
                $args = $factory->args($attr->getArguments());
            } catch (LogicException) {
                // Argument value has no PHP-code representation (e.g. an object created
                // by a new-in-initializer expression evaluated by native reflection).
                // Skip this attribute instead of aborting the whole proxy generation.
                continue;
            }

            $groups[] = new AttributeGroup([new Attribute($fqName, $args)]);
        }

        return $groups;
    }

    /**
     * Clones a single Attribute node with a fully-qualified name and deep-cloned
     * argument expressions, leaving the original AST untouched.
     */
    private static function cloneAttribute(Attribute $attr): Attribute
    {
        // Prefer the fully-resolved class name attached by parser-reflection's
        // NameResolver (preserveOriginalNames + replaceNodes=false mode)
        $nameNode = $attr->name;
        $resolved = $nameNode->getAttribute('resolvedName');
        $fqName   = $resolved instanceof Name
            ? new Name\FullyQualified($resolved->toString())
            : new Name\FullyQualified(ltrim($nameNode->toString(), '\\'));

        // Deep-clone argument expressions so the proxy AST shares no nodes with the
        // original file AST, and fully qualify resolved names inside them so class
        // constant fetches, enum cases etc. stay unambiguous in the proxy context.
        // Unresolved names (e.g. unqualified global constants like PHP_INT_MAX with
        // namespace fallback semantics) are kept as written.
        $traverser = new NodeTraverser(
            new CloningVisitor(),
            new class extends NodeVisitorAbstract {
                public function leaveNode(Node $node): ?Node
                {
                    if ($node instanceof Name && !($node instanceof Name\FullyQualified)) {
                        $resolved = $node->getAttribute('resolvedName');
                        if ($resolved instanceof Name) {
                            return new Name\FullyQualified($resolved->toString(), $node->getAttributes());
                        }
                    }

                    return null;
                }
            }
        );

        /** @var list<Node\Arg> $clonedArgs */
        $clonedArgs = array_values($traverser->traverse($attr->args));

        return new Attribute($fqName, $clonedArgs);
    }

    private static function getFactory(): BuilderFactory
    {
        if (self::$factory === null) {
            self::$factory = new BuilderFactory();
        }

        return self::$factory;
    }
}
