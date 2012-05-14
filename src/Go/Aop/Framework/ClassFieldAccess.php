<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use ReflectionProperty;
use Go\AopAlliance\Intercept\FieldAccess;

/**
 * @package go
 */
class ClassFieldAccess extends AbstractJoinpoint implements FieldAccess
{
    /** @var object|string Name of the class*/
    protected $className = '';

    /** @var string Name of field */
    protected $fieldName = '';

    /** @var object Instance of object for accessing or null */
    protected $instance = null;

    /** @var null|\ReflectionProperty */
    protected $reflectionProperty = null;

    /**
     * Constructor for field access
     *
     * @param string|object $classNameOrObject Class name or object instance
     * @param string $methodName Field name
     * @param $advices array List of advices for this invocation
     */
    public function __construct($classNameOrObject, $methodName, array $advices)
    {
        $isObject = is_object($classNameOrObject);
        $this->className = $isObject ? get_parent_class($classNameOrObject) : $classNameOrObject;
        $this->instance  = $isObject ? $classNameOrObject : null;
        $this->fieldName = $methodName;
        parent::__construct($advices);
    }

    /**
     * Returns the access type.
     *
     * @return integer
     */
    public function getAccessType()
    {
        // TODO: Implement getAccessType() method.
    }

    /**
     * Gets the field being accessed.
     *
     * <p>This method is a frienly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return \ReflectionProperty the field being accessed.  */
    public function getField()
    {
        $this->reflectionProperty = $this->reflectionProperty ?: new ReflectionProperty(
            $this->className, $this->fieldName
        );
        return $this->reflectionProperty;
    }

    /**
     * Gets the value that must be set to the field.
     *
     * <p>This value can be intercepted and changed by a field
     * interceptor.
     * @return mixed
     */
    public function getValueToSet()
    {
        // TODO: Implement getValueToSet() method.
    }

    /**
     * Proceed to the next interceptor in the Chain
     *
     * Typically this method is called inside previous closure, as instance of Joinpoint is passed to callback
     * Do not call this method directly, only inside callback closures.
     *
     * @return mixed
     */
    final public function proceed()
    {
        /** @var $currentInterceptor \Go\AopAlliance\Intercept\FieldAccess */
        $currentInterceptor = current($this->advices);
        if (!$currentInterceptor) {
            return $this->invokeOriginalMethod();
        }
        next($this->advices);
        return $currentInterceptor->invoke($this);
    }
}
