<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use ReflectionClass;

use Go\Aop\PointFilter;
use TokenReflection\ReflectionFileNamespace as ParsedReflectionFileNamespace;

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
            '\\?'    => '.'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function matches($ns)
    {
        /** @var $ns ParsedReflectionFileNamespace */
        if (!$ns instanceof ParsedReflectionFileNamespace) {
            return false;
        }

        return ($ns->getName() === $this->nsName) || (bool) preg_match("/^{$this->regexp}$/", $ns->getName());
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return 0; //self::KIND_CLASS;
    }
}
