<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Aspect;
use Go\Aop\AspectException;
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
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Attribute aspect loader add common support for general advices, declared as attributes
 */
final class AttributeAspectLoaderExtension extends AbstractAspectLoaderExtension
{
    /**
     * Suffix of the advisor id when the advice method also declares a #[Pointcut], which keeps the plain method id
     * so that pointcut references (`$this->method`) resolve to it
     */
    public const string ADVISOR_ID_SUFFIX = '.advice';

    public function load(Aspect $aspect, ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getMethods() as $aspectMethod) {
            $methodId = $reflectionAspect->getName() . '->' . $aspectMethod->getName();

            // Only the framework's own attributes are interpreted, others (#[\Override], DI attributes...) are ignored
            $pointcutAttributes = $aspectMethod->getAttributes(Attribute\Pointcut::class);
            $adviceAttributes   = $aspectMethod->getAttributes(AbstractInterceptor::class, ReflectionAttribute::IS_INSTANCEOF);
            if (count($adviceAttributes) > 1) {
                throw new AspectException(sprintf(
                    'Advice method %s::%s() declares %d advice attributes, only one is supported per method (%s:%d)',
                    $aspectMethod->class,
                    $aspectMethod->name,
                    count($adviceAttributes),
                    $aspectMethod->getFileName(),
                    $aspectMethod->getStartLine(),
                ));
            }

            foreach ($pointcutAttributes as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                $loadedItems[$methodId] = $this->parsePointcut($aspect, $aspectMethod, $attribute->expression);
            }
            foreach ($adviceAttributes as $reflectionAttribute) {
                $attribute   = $reflectionAttribute->newInstance();
                $pointcut    = $this->parsePointcut($aspect, $aspectMethod, $attribute->expression);
                $interceptor = $this->getAdvice($attribute, $aspect, $aspectMethod);
                $advisorId   = $pointcutAttributes === [] ? $methodId : $methodId . self::ADVISOR_ID_SUFFIX;

                $loadedItems[$advisorId] = new GenericPointcutAdvisor($pointcut, $interceptor);
            }
        }

        return $loadedItems;
    }

    /**
     * Returns an advice (interceptor) instance by meta-type attribute and closure
     *
     * @throws AspectException If the advice method is not public or the attribute is unsupported
     */
    protected function getAdvice(
        AbstractInterceptor $interceptorAttribute,
        Aspect $aspect,
        ReflectionMethod $aspectMethod,
    ): Interceptor {
        if (!$aspectMethod->isPublic()) {
            throw new AspectException("Advice method {$aspectMethod->class}::{$aspectMethod->name}() must be public; first-class advice callables require all advice methods to be public");
        }

        $adviceCallback     = $aspectMethod->getClosure($aspect);
        $adviceOrder        = $interceptorAttribute->order;
        $pointcutExpression = $interceptorAttribute->expression;
        return match (true) {
            $interceptorAttribute instanceof Before => new BeforeInterceptor($adviceCallback, $adviceOrder, $pointcutExpression),
            $interceptorAttribute instanceof After => new AfterInterceptor($adviceCallback, $adviceOrder, $pointcutExpression),
            $interceptorAttribute instanceof Around => new AroundInterceptor($adviceCallback, $adviceOrder, $pointcutExpression),
            $interceptorAttribute instanceof AfterThrowing => new AfterThrowingInterceptor($adviceCallback, $adviceOrder, $pointcutExpression),
            default => throw new AspectException('Unsupported method meta class: ' . $interceptorAttribute::class),
        };
    }
}
