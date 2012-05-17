<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use Reflector;
use ReflectionClass;
use TokenReflection\ReflectionClass as ParsedReflectionClass;

use Go\Aop\ClassFilter;

/**
 * Simple ClassFilter implementation that passes classes (and optionally subclasses)
 */
class RootClassFilter implements ClassFilter
{

    /**
     * Class name
     *
     * @var string
     */
    protected $className = null;

    /**
     * Class constructor
     *
     * @param string $className
     */
    public function __construct($className)
    {
        $this->className = $className;
    }

    /**
     * Performs matching of class
     *
     * @param ReflectionClass|ParsedReflectionClass $class Class instance
     *
     * @return bool
     */
    public function matches($class)
    {
        if (!$class instanceof ReflectionClass && !$class instanceof ParsedReflectionClass) {
            return false;
        }
        $isCurrentClass = $class->getName() == $this->className;
        return $isCurrentClass || $class->isSubclassOf($this->className);
    }
}
