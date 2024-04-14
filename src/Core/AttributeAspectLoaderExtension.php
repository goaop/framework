<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Closure;
use Go\Aop\Aspect;
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\AfterThrowingInterceptor;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\Lang\Attribute;
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\AfterThrowing;
use Go\Lang\Attribute\Around;
use Go\Lang\Attribute\AbstractInterceptor;
use Go\Lang\Attribute\Before;
use ReflectionClass;
use ReflectionMethod;
use UnexpectedValueException;

/**
 * Attribute aspect loader add common support for general advices, declared as attributes
 */
class AttributeAspectLoaderExtension extends AbstractAspectLoaderExtension
{
    public function load(Aspect $aspect, ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getMethods() as $aspectMethod) {
            $methodId   = $reflectionAspect->getName() . '->'. $aspectMethod->getName();
            $attributes = $aspectMethod->getAttributes();

            foreach ($attributes as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                if ($attribute instanceof Attribute\Pointcut) {
                    $loadedItems[$methodId] = $this->parsePointcut($aspect, $reflectionAspect, $attribute->expression);
                } elseif ($attribute instanceof Attribute\AbstractInterceptor) {
                    $pointcut    = $this->parsePointcut($aspect, $reflectionAspect, $attribute->expression);
                    $interceptor = $this->getAdvice($attribute, $aspect, $aspectMethod);

                    $loadedItems[$methodId] = new GenericPointcutAdvisor($pointcut, $interceptor);
                } else {
                    throw new UnexpectedValueException('Unsupported attribute class: ' . $attribute::class);
                }
            }
        }

        return $loadedItems;
    }

    /**
     * Returns an advice (interceptor) instance by meta-type attribute and closure
     *
     * @throws UnexpectedValueException For unsupported annotations
     */
    protected function getAdvice(
        AbstractInterceptor $interceptorAttribute,
        Aspect $aspect,
        ReflectionMethod $aspectMethod
    ): Interceptor {
        $adviceCallback = $aspectMethod->getClosure($aspect);
        assert($adviceCallback instanceof Closure, "getClosure should always return Closure");

        $adviceOrder        = $interceptorAttribute->order;
        $pointcutExpression = $interceptorAttribute->expression;
        return match (true) {
            $interceptorAttribute instanceof Before => new BeforeInterceptor($adviceCallback, $adviceOrder, $pointcutExpression),
            $interceptorAttribute instanceof After => new AfterInterceptor($adviceCallback, $adviceOrder, $pointcutExpression),
            $interceptorAttribute instanceof Around => new AroundInterceptor($adviceCallback, $adviceOrder, $pointcutExpression),
            $interceptorAttribute instanceof AfterThrowing => new AfterThrowingInterceptor($adviceCallback, $adviceOrder, $pointcutExpression),
            default => throw new UnexpectedValueException('Unsupported method meta class: ' . $interceptorAttribute::class),
        };
    }
}
