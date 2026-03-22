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
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use ReflectionAttribute;

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
     * Converts an array of reflection attributes to PhpParser AttributeGroup nodes.
     *
     * Each ReflectionAttribute produces one AttributeGroup (one #[...] line).
     * Attribute names are always emitted as fully-qualified to avoid namespace
     * ambiguity inside generated proxy namespaces.
     *
     * @param ReflectionAttribute<object>[] $reflectionAttributes
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

            // BuilderFactory::args() handles named args (string key → Arg::$name)
            // and normalises PHP scalar/array values to AST Expr nodes
            $args = $factory->args($attr->getArguments());

            $groups[] = new AttributeGroup([new Attribute($fqName, $args)]);
        }

        return $groups;
    }

    private static function getFactory(): BuilderFactory
    {
        if (self::$factory === null) {
            self::$factory = new BuilderFactory();
        }

        return self::$factory;
    }
}
