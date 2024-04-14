<?php

declare(strict_types=1);
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
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\OrderedAdvice;
use Go\Core\AspectKernel;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Base class for all framework interceptor implementations
 *
 * This class describe an action taken by the interceptor at a particular joinpoint.
 * Different types of interceptors include "around", "before" and "after" advices.
 *
 * Around interceptor is an advice that surrounds a joinpoint such as a method invocation. This is the most powerful
 * kind of advice. Around advices will perform custom behavior before and after the method invocation. They are
 * responsible for choosing whether to proceed to the joinpoint or to shortcut executing by returning their own return
 * value or throwing an exception.
 *
 * After and before interceptors are simple closures that will be invoked after and before main invocation.
 *
 * Framework models an interceptor as an PHP {@see Closure}, maintaining a chain of interceptors "around" the joinpoint:
 * <pre>
 *   public function (Joinpoint $joinPoint)
 *   {
 *      echo 'Before action';
 *      // call chain here with Joinpoint->proceed() method
 *      $result = $joinPoint->proceed();
 *      echo 'After action';
 *
 *      return $result;
 *   }
 * </pre>
 */
abstract class AbstractInterceptor implements Interceptor, OrderedAdvice
{
    /**
     * @var (array&array<string, Closure>) Local hashmap of advices for faster unserialization
     */
    private static array $localAdvicesCache = [];

    /**
     * Default constructor for interceptor
     */
    public function __construct(
        protected readonly Closure $adviceMethod,
        private readonly int $adviceOrder = 0,
        protected readonly string $pointcutExpression = ''
    ) {}

    /**
     * Serializes advice closure into array
     *
     * @return array{name: string, class?: string}
     */
    public static function serializeAdvice(Closure $adviceMethod): array
    {
        $reflectionAdvice     = new ReflectionFunction($adviceMethod);
        $scopeReflectionClass = $reflectionAdvice->getClosureScopeClass();

        $packedAdvice = ['name' => $reflectionAdvice->name];
        if (!isset($scopeReflectionClass)) {
            throw new AspectException('Could not pack an interceptor without aspect name');
        }
        $packedAdvice['class'] = $scopeReflectionClass->name;

        return $packedAdvice;
    }

    /**
     * Unserialize an advice
     *
     * @param array{name: string, class?: string} $adviceData Information about advice
     */
    public static function unserializeAdvice(array $adviceData): Closure
    {
        // General unpacking supports only aspect's advices
        if (!isset($adviceData['class']) || !is_subclass_of($adviceData['class'], Aspect::class)) {
            throw new AspectException('Could not unpack an interceptor without aspect name');
        }
        $aspectName = $adviceData['class'];
        $methodName = $adviceData['name'];

        // With aspect name and method name, we can restore back a closure for it
        if (!isset(self::$localAdvicesCache["$aspectName->$methodName"])) {
            $aspect = AspectKernel::getInstance()->getContainer()->getService($aspectName);
            $advice = (new ReflectionMethod($aspectName, $methodName))->getClosure($aspect);

            assert(isset($advice), 'getClosure() can not be null on modern PHP versions');
            self::$localAdvicesCache["$aspectName->$methodName"] = $advice;
        }

        return self::$localAdvicesCache["$aspectName->$methodName"];
    }

    public function getAdviceOrder(): int
    {
        return $this->adviceOrder;
    }

    /**
     * Getter for extracting the advice closure from Interceptor
     *
     * @internal
     */
    public function getRawAdvice(): Closure
    {
        return $this->adviceMethod;
    }

    /**
     * Serializes an interceptor into it's array shape representation
     *
     * @return non-empty-array<string, mixed>
     */
    final public function __serialize(): array
    {
        // Compressing state representation to avoid default values, eg pointcutExpression = '' or adviceOrder = 0
        $state = array_filter(get_object_vars($this));

        // Override closure with array representation to enable serialization
        $state['adviceMethod'] = static::serializeAdvice($this->adviceMethod);

        return $state;
    }

    /**
     * Un-serializes an interceptor from it's stored state
     *
     * @param array{adviceMethod: array{name: string, class?: string}} $state The stored representation of the interceptor.
     */
    final public function __unserialize(array $state): void
    {
        $state['adviceMethod'] = static::unserializeAdvice($state['adviceMethod']);
        foreach ($state as $key => $value) {
            $this->$key = $value;
        }
    }
}
