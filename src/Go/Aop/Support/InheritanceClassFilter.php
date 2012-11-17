<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use ReflectionClass;

use Go\Aop\ClassFilter;
use TokenReflection\ReflectionClass as ParsedReflectionClass;

/**
 * Inheritance class matcher that match single class name or any subclass
 */
class InheritanceClassFilter implements ClassFilter
{

    /**
     * Instance class name to match
     *
     * @var string
     */
    protected $parentClass = '';

    /**
     * Inheritance class matcher constructor
     *
     * @param string $parentClassName Name of the parent class or interface to match
     */
    public function __construct($parentClassName)
    {
        $this->parentClass = $parentClassName;
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

        return $class->isSubclassOf($this->parentClass) || $class->implementsInterface($this->parentClass);
    }
}
