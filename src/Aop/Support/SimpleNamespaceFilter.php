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
     */
    protected string $nsName;

    /**
     * Pattern for regular expression matching
     */
    protected string $regexp;

    /**
     * Namespace name matcher constructor that accepts name or glob pattern to match
     */
    public function __construct(string $namespaceName)
    {
        $namespaceName = trim($namespaceName, '\\');
        $this->nsName  = $namespaceName;
        $this->regexp  = strtr(preg_quote($this->nsName, '/'), [
            '\\*'    => '[^\\\\]+',
            '\\*\\*' => '.+',
            '\\?'    => '.',
            '\\|'    => '|'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function matches($ns, $context = null, $instance = null, array $arguments = null): bool
    {
        $isNamespaceIsObject = ($ns === (object) $ns);

        if ($isNamespaceIsObject && !$ns instanceof ReflectionFileNamespace) {
            return false;
        }

        $nsName = ($ns instanceof ReflectionFileNamespace) ? $ns->getName() : $ns;

        return ($nsName === $this->nsName) || (bool) preg_match("/^(?:{$this->regexp})$/", $nsName);
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return 0;
    }
}
