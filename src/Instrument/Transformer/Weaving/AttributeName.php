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

namespace Go\Instrument\Transformer\Weaving;

use PhpParser\Node\Attribute;
use PhpParser\Node\Name;

/**
 * Resolved class names of attribute nodes
 *
 * @internal
 */
final class AttributeName
{
    /**
     * Returns the fully qualified name of an attribute class, without a leading backslash
     *
     * parser-reflection runs the NameResolver without replacing nodes, so the resolved name is kept in the
     * `resolvedName` attribute of the name node; the name as written is the fallback.
     */
    public static function of(Attribute $attribute): string
    {
        $resolvedName = $attribute->name->getAttribute('resolvedName');
        $name         = $resolvedName instanceof Name ? $resolvedName : $attribute->name;

        return ltrim($name->toString(), '\\');
    }

    /**
     * Checks whether the attribute is #[\Override], compared by the name resolved by the parser
     *
     * Aliases (`use Override as O; #[O]`), qualified names and attributes that merely end with "Override" are told
     * apart correctly.
     */
    public static function isOverride(Attribute $attribute): bool
    {
        return self::of($attribute) === 'Override';
    }
}
