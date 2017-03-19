<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;
use Go\ParserReflection\ReflectionFileNamespace;

/**
 * Simple namespace matcher that match only specific namespace name
 *
 * Namespace name can contain wildcards '*', '**' and '?'
 */
class SimpleNamespaceFilter implements PointFilter
{

    /**
     * Namespace name to match, can contain wildcards *,?
     *
     * @var string
     */
    protected $nsName = '';

    /**
     * Pattern for regular expression matching
     *
     * @var string
     */
    protected $regexp;

    /**
     * Namespace name matcher constructor
     *
     * @param string $namespaceName Name of the namespace to match or glob pattern
     */
    public function __construct($namespaceName)
    {
        $namespaceName = trim($namespaceName, '\\');
        $this->nsName  = $namespaceName;
        $this->regexp  = strtr(preg_quote($this->nsName, '/'), array(
            '\\*'    => '[^\\\\]+',
            '\\*\\*' => '.+',
            '\\?'    => '.',
            '\\|'    => '|'
        ));
    }

    /**
     * {@inheritdoc}
     * @param ReflectionFileNamespace|string $ns
     */
    public function matches($ns, $context = null, $instance = null, array $arguments = null)
    {
        $isNamespaceIsObject = ($ns === (object) $ns);

        if ($isNamespaceIsObject && !$ns instanceof ReflectionFileNamespace) {
            return false;
        }

        $nsName = $isNamespaceIsObject ? $ns->getName() : $ns;

        return ($nsName === $this->nsName) || (bool) preg_match("/^(?:{$this->regexp})$/", $nsName);
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return 0;
    }
}
