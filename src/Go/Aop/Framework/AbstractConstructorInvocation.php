<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use ReflectionClass;
use ReflectionMethod;

use Go\Aop\Intercept\ConstructorInvocation;
use Go\Aop\Intercept\ConstructorInterceptor;

/**
 * Abstract constructor invocation implementation
 *
 * @see Go\Aop\Intercept\ConstructorInvocation
 * @package go
 */
class AbstractConstructorInvocation extends AbstractInvocation implements ConstructorInvocation
{
    /**
     * Reflection class
     *
     * @var ReflectionClass
     */
    protected $class = null;

    /**
     * Instance of reflection constructor for class
     *
     * @var null|ReflectionMethod
     */
    private $constructor = null;

    /**
     * Constructor for constructor invocation :)
     *
     * @param string|object $classNameOrObject Class name or object instance
     * @param $advices array List of advices for this invocation
     */
    public function __construct($classNameOrObject, array $advices)
    {
        $this->class = new ReflectionClass($classNameOrObject);
        parent::__construct($advices);
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
            /** @var $currentInterceptor ConstructorInterceptor */
            $currentInterceptor = $this->advices[$this->current];
            $this->current++;
            return $currentInterceptor->construct($this);
        }

        return $this->constructOriginal();
    }


    /**
     * Gets the constructor being called.
     *
     * <p>This method is a frienly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return ReflectionMethod the constructor being called. */
    public function getConstructor()
    {
        if (!$this->constructor) {
            $this->constructor = $this->class->getConstructor();
            // Give an access to call protected constructor
            if ($this->constructor->isProtected()) {
                $this->constructor->setAccessible(true);
            }
        }
        return $this->constructor;
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
    public function getThis()
    {
        return null;
    }

    /**
     * Returns the static part of this joinpoint.
     *
     * <p>The static part is an accessible object on which a chain of
     * interceptors are installed.
     * @return object
     */
     public function getStaticPart()
     {
         return $this->getConstructor();
     }

    /**
     * Invokes current constructor invocation with all interceptors
     *
     * @return mixed
     */
    final public function __invoke()
    {
        // TODO: add support for recursion in constructors
        $this->current   = 0;
        $this->arguments = func_get_args();
        return $this->proceed();
    }

    /**
     * Invokes original constructor and return result from it
     *
     * @return mixed
     */
    protected function constructOriginal()
    {
        return $this->class->newInstanceArgs($this->arguments);
    }
}
