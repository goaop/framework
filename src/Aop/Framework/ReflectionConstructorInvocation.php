<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\ConstructorInvocation;
use Go\Core\AspectContainer;
use ReflectionClass;
use ReflectionMethod;

/**
 * Reflection constructor invocation implementation
 */
class ReflectionConstructorInvocation extends AbstractInvocation implements ConstructorInvocation
{
    /**
     * Reflection class
     *
     * @var ReflectionClass
     */
    protected $class;

    /**
     * Instance of created class, can be used for Around or After types of advices
     *
     * @var object|null
     */
    protected $instance;

    /**
     * Instance of reflection constructor for class
     *
     * @var null|ReflectionMethod
     */
    private $constructor;

    /**
     * Constructor for constructor invocation :)
     *
     * @param string $className Class name
     * @param $advices array List of advices for this invocation
     * @param string $type
     */
    public function __construct($className, $type, array $advices)
    {
        $originalClass = $className;
        if (strpos($originalClass, AspectContainer::AOP_PROXIED_SUFFIX) !== false) {
            $originalClass = substr($originalClass, 0, -strlen(AspectContainer::AOP_PROXIED_SUFFIX));
        }

        $this->class       = new ReflectionClass($originalClass);
        $this->constructor = $constructor = $this->class->getConstructor();

        // Give an access to call protected/private constructors
        if ($constructor && !$constructor->isPublic()) {
            $constructor->setAccessible(true);
        }

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
            $currentInterceptor = $this->advices[$this->current];
            $this->current++;

            return $currentInterceptor->invoke($this);
        }

        $this->instance = $this->class->newInstanceWithoutConstructor();
        $constructor    = $this->getConstructor();
        if ($constructor !== null) {
            $constructor->invoke($this->instance, ...$this->arguments);
        }

        return $this->instance;
    }

    /**
     * Gets the constructor being called.
     *
     * @return ReflectionMethod|null the constructor being called or null if it is absent.
     */
    public function getConstructor()
    {
        return $this->constructor;
    }

    /**
     * Returns the object that holds the current joinpoint's static
     * part.
     *
     * @return object|null the object (can be null if the accessible object is
     * static).
     */
    public function getThis()
    {
        return $this->instance;
    }

    /**
     * Returns the static part of this joinpoint.
     *
     * @return null|ReflectionMethod
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
    final public function __invoke(array $arguments = [])
    {
        $this->current   = 0;
        $this->arguments = $arguments;

        return $this->proceed();
    }

    /**
     * Returns a friendly description of current joinpoint
     *
     * @return string
     */
    final public function __toString()
    {
        return sprintf(
            'initialization(%s)',
            $this->class->name
        );
    }
}
