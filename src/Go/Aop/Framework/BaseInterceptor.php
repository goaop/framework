<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Serializable;

use Go\Aop\Pointcut;
use Go\Aop\Framework\BaseAdvice;
use Go\Aop\Intercept\Interceptor;

/**
 * @package go
 */
class BaseInterceptor extends BaseAdvice implements Interceptor, Serializable
{
    /**
     * Name of the aspect
     *
     * @var string
     */
    public $aspectName = '';

    /**
     * Pointcut instance
     *
     * @var null|Pointcut
     */
    public $pointcut = null;

    /**
     * Advice to call
     *
     * In Spring it's ReflectionMethod, but this will be slowly
     *
     * @var null|\Closure
     */
    protected $adviceMethod = null;

    /**
     * Default constructor for interceptor
     *
     * @param callable $adviceMethod Interceptor advice to call
     * @param integer $order Order of interceptor
     * @param Pointcut $pointcut Pointcut instance where interceptor should be called
     */
    public function __construct($adviceMethod, $order = self::ORDER_NOT_SET, Pointcut $pointcut = null)
    {
        assert('is_callable($adviceMethod) /* Advice method should be callable */');

        $this->adviceMethod = $adviceMethod;
        $this->order        = $order;
        $this->pointcut     = $pointcut;
    }

    /**
     * Getter for extracting the advice closure from Interceptor
     *
     * @return callable|null
     */
    public function getRawAdvice()
    {
        return $this->adviceMethod;
    }

    /**
     * Serializes an interceptor into string representation
     *
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        $vars = get_object_vars($this);
        $vars['adviceMethod'] = static::serializeAdvice($this->adviceMethod);
        return serialize($vars);
    }

    /**
     * Unserialize an interceptor from the string
     *
     * @param string $serialized The string representation of the object.
     * @return void
     */
    public function unserialize($serialized)
    {
        $vars = unserialize($serialized);
        $vars['adviceMethod'] = static::unserializeAdvice($vars['adviceMethod']);
        foreach ($vars as $key=>$value) {
            $this->$key = $value;
        }
    }
}
