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
use Go\Core\AspectKernel;
use ReflectionFunction;
use ReflectionMethod;
use Serializable;
use Go\Aop\Intercept\Interceptor;

/**
 * Base interceptor realization
 */
abstract class BaseInterceptor implements Interceptor, OrderedAdvice, Serializable
{
    /**
     * Local cache of advices for faster unserialization on big projects
     *
     * @var Closure[]
     */
    protected static $localAdvicesCache = [];
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
     * Advice order
     *
     * @var int
     */
    protected $order = 0;

    /**
     * Default constructor for interceptor
     */
    public function __construct(Closure $adviceMethod, int $order = 0, string $pointcutExpression = '')
    {
        $this->adviceMethod       = $adviceMethod;
        $this->order              = $order;
        $this->pointcutExpression = $pointcutExpression;
    }

    /**
     * Serialize advice method into array
     */
    public static function serializeAdvice(Closure $adviceMethod): array
    {
        $refAdvice = new ReflectionFunction($adviceMethod);

        return [
            'method' => $refAdvice->name,
            'aspect' => get_class($refAdvice->getClosureThis())
        ];
    }

    /**
     * Unserialize an advice
     *
     * @param array $adviceData Information about advice
     */
    public static function unserializeAdvice(array $adviceData): Closure
    {
        $aspectName = $adviceData['aspect'];
        $methodName = $adviceData['method'];

        if (!isset(static::$localAdvicesCache["$aspectName->$methodName"])) {
            $refMethod                                             = new ReflectionMethod($aspectName, $methodName);
            $aspect                                                = AspectKernel::getInstance()->getContainer()
                ->getAspect($aspectName);
            $advice                                                = $refMethod->getClosure($aspect);
            static::$localAdvicesCache["$aspectName->$methodName"] = $advice;
        }

        return static::$localAdvicesCache["$aspectName->$methodName"];
    }

    /**
     * Getter for extracting the advice closure from Interceptor
     */
    public function getRawAdvice(): Closure
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
        foreach ($vars as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Returns the advice order
     */
    public function getAdviceOrder(): int
    {
        return $this->order;
    }
}
