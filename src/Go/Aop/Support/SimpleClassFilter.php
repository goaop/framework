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
use TokenReflection\ReflectionClass as ParsedReflectionClass;

/**
 * Simple class matcher that match only single class name
 *
 * Class name can contain wildcards '*', '**' and '?'
 */
class SimpleClassFilter implements PointFilter
{

    /**
     * Class name to match, can contain wildcards *,?
     *
     * @var string
     */
    protected $className = '';

    /**
     * Class name matcher constructor
     *
     * @param string $className Name of the class to match or glob pattern
     */
    public function __construct($className)
    {
        $this->className = $className;
        $this->regexp    = strtr(preg_quote($this->className, '/'), array(
            '\\*'    => '[^\\\\]+',
            '\\*\\*' => '.+',
            '\\?'    => '.'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function matches($class)
    {
        /** @var $point ReflectionClass|ParsedReflectionClass */
        if (!$class instanceof ReflectionClass && !$class instanceof ParsedReflectionClass) {
            return false;
        }

        return ($class->name === $this->className) || (bool) preg_match("/^{$this->regexp}$/i", $class->name);
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return self::KIND_CLASS;
    }
}
