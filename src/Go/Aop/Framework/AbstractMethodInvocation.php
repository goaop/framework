<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use ReflectionMethod;

use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Intercept\MethodInterceptor;

/**
 * Abstract method invocation implementation
 *
 * @see Go\Aop\Intercept\MethodInvocation
 * @package go
 */
abstract class AbstractMethodInvocation extends AbstractInvocation implements MethodInvocation
{
    /**
     * Class name of method
     *
     * @var string
     */
    protected $classOrObject = '';

    /**
     * Name of invoked method
     *
     * @var string
     */
    protected $methodName = '';

    /**
     * Instance of object for invoking or null
     *
     * @var object
     */
    protected $instance = null;

    /**
     * Instance of reflection method for class
     *
     * @var null|ReflectionMethod
     */
    private $reflectionMethod = null;

    /**
     * Constructor for method invocation
     *
     * @param string|object $classNameOrObject Class name or object instance
     * @param string $methodName Method to invoke
     * @param $advices array List of advices for this invocation
     */
    public function __construct($classNameOrObject, $methodName, array $advices)
    {
        $this->classOrObject = $classNameOrObject;
        $this->methodName    = $methodName;
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
        /** @var $currentInterceptor MethodInterceptor */
        $currentInterceptor = current($this->advices);
        if (!$currentInterceptor) {
            return $this->invokeOriginalMethod();
        }
        next($this->advices);
        return $currentInterceptor->invoke($this);
    }

    /**
     * Gets the method being called.
     *
     * <p>This method is a frienly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return ReflectionMethod the method being called.
     */
    public function getMethod()
    {
        if (!$this->reflectionMethod) {
            $this->reflectionMethod = new ReflectionMethod($this->classOrObject, $this->methodName);
            // Give an access to call protected method
            if ($this->reflectionMethod->isProtected()) {
                $this->reflectionMethod->setAccessible(true);
            }
        }
        return $this->reflectionMethod;
    }

    /**
     * Invokes current method invocation with all interceptors
     *
     * @return mixed
     */
    final public function __invoke()
    {
        $this->arguments = func_get_args();
        $this->instance  = array_shift($this->arguments);
        reset($this->advices);
        return $this->proceed();
    }

    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    abstract protected function invokeOriginalMethod();
}
