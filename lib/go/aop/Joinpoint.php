<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

/**
 * Joinpoint realization for PHP
 *
 * Join points are points in the execution of the system, such as method calls, where behavior supplied by aspects is
 * combined. A join point is a point in the execution of the program, which is used to define the dynamic structure of a
 * crosscutting concern.
 * The dynamic interpretation of join points makes it possible to expose runtime information
 *
 * @link http://en.wikipedia.org/wiki/Aspect-oriented_software_development#Join_point_model
 * @package go
 * @subpackage aop
 *
 * @property-write Advice $around Write-only property to assign new 'around' advices
 * @property-write Advice $before Write-only property to assign new 'before' advices
 * @property-write Advice $after Write-only property to assign new 'after' advices
 */
class Joinpoint {

    /**
     * Advices for joinpoint
     *
     * This field contains definition of advices. Advices are taken from Aspect public properties.
     * @see \go\aop\call() for details of where this value is taken from
     * @var array array[$adviceName][] => Advice
     */
    protected $advices = array();

    /**
     * Invoker closure for fastest invoking current joinpoint
     * @var Closure
     */
    protected $invoker = null;

    /**
     * Join point initialization
     *
     * @param callback $closure Lambda function, closure or name of global function to invoke
     */
    public function __construct($closure)
    {
        $this->advices = array(
            Advice::BEFORE => array(),
            Advice::AROUND => array($closure),
            Advice::AFTER  => array(),
        );
        $this->invoker = $this->getAroundInvoker();
    }

    /**
     * Advices setter
     *
     * @param string $adviceName Name of advice
     * @param Advice|callback $advice
     */
    final public function __set($adviceName, $advice)
    {
        switch($adviceName) {
            case Advice::BEFORE:
            case Advice::AROUND:
            case Advice::AFTER:
                if (!is_array($advice) && !is_callable($advice)) {
                    throw new \UnexpectedValueException("Only valid closures can be used as advice");
                }
                $advices = is_array($advice) ? $advice : array($advice);
                $this->advices[$adviceName] = array_merge($advices, $this->advices[$adviceName]);
                $this->invoker = $this->getFastestInvoker();
                break;

            default:
                throw new \DomainException("Trying to set unknown advice '$adviceName'");
        }
    }

    /**
     * Executes current join point
     *
     * WARNING! Due to performance restrictions only single parameter can be passed to joinpoint
     * 
     * @param array|mixed $params Parameter for calling, typically it is an array
     */
    final public function __invoke($params = null)
    {
        $invoker = $this->invoker;
        return $invoker($params, $this);
    }

    /**
     * Iterates to the next 'around' closure and run it
     *
     * Typically this method is called inside previous closure, as instance of Joinpoint is passed to callback
     * Do not call this method directly, only inside callback closures.
     *
     * @param array|mixed $params Parameter for calling, typically it's an array
     * @param Joinpoint $joinPoint Current instance of join point
     * @return mixed
     */
    public function proceed($params, Joinpoint $joinPoint)
    {
        $next = next($this->advices[Advice::AROUND]);
        return $next($params, $joinPoint);
    }

    /**
     * Returns around advice invoker
     *
     * @return closure
     */
    final protected function getAroundInvoker()
    {
        $arounds = &$this->advices[Advice::AROUND];
        $joinPoint = $this;
        return function($params = null) use (&$arounds, $joinPoint) {
            $first = reset($arounds);
            return $first($params, $joinPoint);
        };
    }

    /**
     * Returns fastest invoker closure regarding to configured advices
     *
     * @return closure
     */
    protected function getFastestInvoker()
    {
        $before = &$this->advices[Advice::BEFORE];
        $after = &$this->advices[Advice::AFTER];
        $invoker = null;
        $aroundInvoker = $this->getAroundInvoker();
        switch(array($before, $after)) {

            case array(false, false):
                $invoker = $aroundInvoker;
                break;

            case array(true, false):
                $invoker = function($params = null) use (&$before, $aroundInvoker) {
                    for($i = count($before); $i--;) {
                        $before[$i]($params);
                    }
                    return $aroundInvoker($params);
                };
                break;

            case array(false, true):
                $invoker = function($params = null) use (&$after, $aroundInvoker) {
                    $result = $aroundInvoker($params);
                    for($i = count($after); $i--;) {
                        $after[$i]($params);
                    }
                    return $result;
                };
                break;

            case array(true, true):
                $invoker = function($params = null) use (&$before, &$after, $aroundInvoker) {
                    for($i = count($before); $i--;) {
                        $before[$i]($params);
                    }
                    $result = $aroundInvoker($params);
                    for($i = count($after); $i--;) {
                        $after[$i]($params);
                    }
                    return $result;
                };
                break;
        }
        return $invoker;
    }

}
