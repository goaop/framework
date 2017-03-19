<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;
use Serializable;
use Go\Aop\Intercept\Interceptor;

/**
 * Base interceptor realization
 */
abstract class BaseInterceptor extends BaseAdvice implements Interceptor, Serializable
{
    /**
     * Pointcut expression
     *
     * @var string
     */
    protected $pointcutExpression = '';

    /**
     * Advice to call
     *
     * @var Closure
     */
    protected $adviceMethod;

    /**
     * Default constructor for interceptor
     *
     * @param Closure $adviceMethod Interceptor advice to call
     * @param integer $order Order of interceptor
     * @param string $pointcutExpression Pointcut expression or advice name
     */
    public function __construct(Closure $adviceMethod, $order = 0, $pointcutExpression = '')
    {
        $this->adviceMethod       = $adviceMethod;
        $this->order              = $order;
        $this->pointcutExpression = $pointcutExpression;
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
        $vars = array_filter(get_object_vars($this));
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
