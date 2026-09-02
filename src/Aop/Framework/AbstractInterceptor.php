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
     * @var array<string, Closure> Local hashmap of advices for faster unserialization
     */
    private static array $localAdvicesCache = [];

    /**
     * Default state of properties that is not stored during serialization to compress state representation.
     *
     * Values must match the declared property defaults, as the engine initializes properties
     * from their declarations before __unserialize() is invoked.
     */
    private const array DEFAULT_STATE = ['adviceOrder' => 0, 'pointcutExpression' => ''];

    /**
     * Order of advice invocation, lower values are invoked first
     */
    private int $adviceOrder = 0;

    /**
     * Pointcut expression that was used to create this interceptor
     *
     * @internal Framework-internal introspection point (e.g. debug:advisor command), not a public API
     */
    public private(set) string $pointcutExpression = '';

    /**
     * Default constructor for interceptor
     */
    public function __construct(
        protected readonly Closure $adviceMethod,
        int $adviceOrder = 0,
        string $pointcutExpression = ''
    ) {
        $this->adviceOrder        = $adviceOrder;
        $this->pointcutExpression = $pointcutExpression;
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
     * @return array<mixed>
     */
    final public function __serialize(): array
    {
        // Compressing state representation by dropping only the values that match the defaults
        // restored by __unserialize(). Strict comparison keeps legitimate falsy values (eg '0') intact.
        $state = get_object_vars($this);
        foreach (self::DEFAULT_STATE as $key => $defaultValue) {
            if ($state[$key] === $defaultValue) {
                unset($state[$key]);
            }
        }

        // Override closure with array representation to enable serialization
        $state['adviceMethod'] = static::serializeAdvice($this->adviceMethod);

        return $state;
    }

    /**
     * Un-serializes an interceptor from it's stored state
     *
     * @param array{adviceMethod: array{class: class-string<Aspect>, name: string}} $state The stored representation of the interceptor.
     */
    final public function __unserialize(array $state): void
    {
        $state['adviceMethod'] = static::unserializeAdvice($state['adviceMethod']);
        // Only stored state is assigned here: properties compressed away by __serialize()
        // are already initialized by the engine with their declared default values.
        foreach ($state as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Serializes advice closure into array
     *
     * @return array<string, mixed>
     */
    protected static function serializeAdvice(Closure $adviceMethod): array
    {
        $reflectionAdvice     = new ReflectionFunction($adviceMethod);
        $scopeReflectionClass = $reflectionAdvice->getClosureScopeClass();
        if (!isset($scopeReflectionClass) || !is_subclass_of($scopeReflectionClass->name, Aspect::class)) {
            throw new AspectException('Could not pack an interceptor without valid aspect');
        }

        return [
            'name'  => $reflectionAdvice->name,
            'class' => $scopeReflectionClass->name,
        ];
    }

    /**
     * Unserialize an advice
     *
     * @param array<string, mixed> $adviceData Information about advice
     */
    protected static function unserializeAdvice(array $adviceData): Closure
    {
        $aspectName = $adviceData['class'] ?? null;
        $methodName = $adviceData['name'] ?? null;
        // General unpacking supports only aspect's advices
        if (!is_string($aspectName) || !is_string($methodName) || !is_subclass_of($aspectName, Aspect::class)) {
            throw new AspectException('Could not unpack an interceptor without aspect name');
        }

        // With aspect name and method name, we can restore back a closure for it
        if (!isset(self::$localAdvicesCache["$aspectName->$methodName"])) {
            $aspect = AspectKernel::getInstance()->getContainer()->getService($aspectName);
            $advice = new ReflectionMethod($aspectName, $methodName)->getClosure($aspect);

            self::$localAdvicesCache["$aspectName->$methodName"] = $advice;
        }

        return self::$localAdvicesCache["$aspectName->$methodName"];
    }
}
