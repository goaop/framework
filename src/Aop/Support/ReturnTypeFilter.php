<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;
use ReflectionFunctionAbstract;

/**
 * Return type filter matcher methods and function with specific return type
 *
 * Type name can contain wildcards '*', '**' and '?'
 */
class ReturnTypeFilter implements PointFilter
{
    /**
     * Return type name to match, can contain wildcards *,?
     */
    protected string $typeName;

    /**
     * Pattern for regular expression matching
     */
    protected string $regexp;

    /**
     * Return type name matcher constructor accepts name or glob pattern of the type to match
     */
    public function __construct(string $returnTypeName)
    {
        $returnTypeName = trim($returnTypeName, '\\');
        $this->typeName = $returnTypeName;
        $this->regexp   = strtr(preg_quote($this->typeName, '/'), [
            '\\*'    => '[^\\\\]+',
            '\\*\\*' => '.+',
            '\\?'    => '.',
            '\\|'    => '|'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function matches($functionLike, $context = null, $instance = null, array $arguments = null): bool
    {
        if (!$functionLike instanceof ReflectionFunctionAbstract) {
            return false;
        }

        if (!$functionLike->hasReturnType()) {
            return false;
        }

        $returnType = (string) $functionLike->getReturnType();

        return ($returnType === $this->typeName) || (bool) preg_match("/^(?:{$this->regexp})$/", $returnType);
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return self::KIND_METHOD | self::KIND_FUNCTION;
    }
}
