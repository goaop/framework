<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use ReflectionProperty;

use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldInterceptor;

/**
 * @package go
 */
class ClassFieldAccess extends AbstractJoinpoint implements FieldAccess
{

    /**
     * Mapping of access type with method to invoke for interceptor
     *
     * @var array
     */
    private static $interceptorMethodMapping = array(
        self::READ  => 'get',
        self::WRITE => 'set'
    );

    /**
     * Instance of object for accessing
     *
     * @var object
     */
    protected $instance = null;

    /**
     * Instance of reflection property
     *
     * @var null|\ReflectionProperty
     */
    protected $reflectionProperty = null;

    /**
     * New value to set
     *
     * @var mixed
     */
    protected $newValue = null;

    /**
     * Access type for field access
     *
     * @var null|integer
     */
    private $accessType = null;

    /**
     * Copy of the original value of property
     *
     * @var mixed
     */
    private $value = null;

    /**
     * Constructor for field access
     *
     * @param string $className Class name
     * @param string $fieldName Field name
     * @param $advices array List of advices for this invocation
     */
    public function __construct($className, $fieldName, array $advices)
    {
        parent::__construct($advices);

        $this->reflectionProperty = $reflectionProperty = new ReflectionProperty($className, $fieldName);
        // Give an access to protected field
        if ($reflectionProperty->isProtected()) {
            $reflectionProperty->setAccessible(true);
        }
    }

    /**
     * Returns the access type.
     *
     * @return integer
     */
    public function getAccessType()
    {
        return $this->accessType;
    }

    /**
     * Gets the field being accessed.
     *
     * <p>This method is a friendly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return ReflectionProperty the field being accessed.
     */
    public function getField()
    {
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
        return $this->newValue;
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
        if (isset($this->advices[$this->current])) {
            /** @var $currentInterceptor FieldInterceptor */
            $currentInterceptor = $this->advices[$this->current++];
            return $currentInterceptor->{self::$interceptorMethodMapping[$this->accessType]}($this);
        }

        if ($this->accessType === self::WRITE) {
            return $this->getValueToSet();
        }

        return $this->value;
    }

    /**
     * Invokes current field access with all interceptors
     *
     * @param object $instance Instance of object
     * @param integer $accessType Type of access: READ or WRITE
     * @param mixed $originalValue Original value of property
     * @param mixed $newValue New value to set
     *
     * @return mixed
     */
    final public function __invoke($instance, $accessType, $originalValue, $newValue = NAN)
    {
        if ($this->level) {
            array_push($this->stackFrames, array($this->instance, $this->accessType, $this->value, $this->newValue));
        }

        ++$this->level;

        $this->current    = 0;
        $this->instance   = $instance;
        $this->accessType = $accessType;
        $this->value      = $originalValue;
        $this->newValue   = $newValue;

        $result = $this->proceed();

        --$this->level;

        if ($this->level) {
            list($this->instance, $this->accessType, $this->value, $this->newValue) = array_pop($this->stackFrames);
        }
        return $result;

    }

    /**
     * Returns the object that holds the current joinpoint's static
     * part.
     *
     * <p>For instance, the target object for an invocation.
     *
     * @return object|null the object (can be null if the accessible object is
     * static).
     */
    final public function getThis()
    {
        return $this->instance;
    }

    /**
     * Returns the static part of this joinpoint.
     *
     * <p>The static part is an accessible object on which a chain of
     * interceptors are installed.
     * @return object
     */
    final public function getStaticPart()
    {
        return $this->getField();
    }
}
