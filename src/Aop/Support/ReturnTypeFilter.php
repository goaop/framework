<?php
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
 * Namespace name can contain wildcards '*', '**' and '?'
 */
class ReturnTypeFilter implements PointFilter
{

    /**
     * Namespace name to match, can contain wildcards *,?
     *
     * @var string
     */
    protected $typeName;

    /**
     * Pattern for regular expression matching
     *
     * @var string
     */
    protected $regexp;

    /**
     * Namespace name matcher constructor
     *
     * @param string $returnTypeName Name of the return type to match or glob pattern
     */
    public function __construct($returnTypeName)
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
     * @param ReflectionFunctionAbstract
     */
    public function matches($functionLike, $context = null, $instance = null, array $arguments = null)
    {
        if (!$functionLike instanceof ReflectionFunctionAbstract) {
            return false;
        }

        if (PHP_VERSION_ID < 70000 || !$functionLike->hasReturnType()) {
            return false;
        }

        $returnType = (string) $functionLike->getReturnType();

        return ($returnType === $this->typeName) || (bool) preg_match("/^(?:{$this->regexp})$/", $returnType);
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind()
    {
        return self::KIND_METHOD | self::KIND_FUNCTION;
    }
}
