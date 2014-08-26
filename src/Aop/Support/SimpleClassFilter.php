<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
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
            '\\?'    => '.',
            '\\|'    => '|'
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

        return ($class->name === $this->className) || (bool) preg_match("/^(?:{$this->regexp})$/", $class->name);
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
