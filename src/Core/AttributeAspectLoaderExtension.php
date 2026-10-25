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
use Go\Aop\Exception\AspectException;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Intercept\Interceptor as InterceptorInterface;
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

    public function load(ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getMethods() as $aspectMethod) {
            $methodId = $reflectionAspect->getName() . '->' . $aspectMethod->getName();

            // Only the framework's own attributes are interpreted, others (#[\Override], DI attributes...) are ignored
            $pointcutAttributes = $aspectMethod->getAttributes(Attribute\Pointcut::class);
            $adviceAttributes   = $aspectMethod->getAttributes(AbstractInterceptor::class, ReflectionAttribute::IS_INSTANCEOF);
            if (count($adviceAttributes) > 1) {
                throw AspectException::multipleAdviceAttributes($aspectMethod, count($adviceAttributes));
            }

            foreach ($pointcutAttributes as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                $loadedItems[$methodId] = $this->parsePointcut($reflectionAspect, $aspectMethod, $attribute->expression);
            }
            foreach ($adviceAttributes as $reflectionAttribute) {
                $attribute   = $reflectionAttribute->newInstance();
                $pointcut    = $this->parsePointcut($reflectionAspect, $aspectMethod, $attribute->expression);
                $interceptor = $this->getAdvice($attribute, $reflectionAspect, $aspectMethod);
                $advisorId   = $pointcutAttributes === [] ? $methodId : $methodId . self::ADVISOR_ID_SUFFIX;

                $loadedItems[$advisorId] = new GenericPointcutAdvisor($pointcut, $interceptor);
            }
        }

        return $loadedItems;
    }

    /**
     * Returns an advice (interceptor) for the advice method, without creating the aspect
     *
     * The interceptor is the lazy form of the {@see Interceptor} facade: it carries the aspect class, the method name,
     * the order and the expression as plain data, and resolves the aspect from the container on first use only.
     *
     * @param ReflectionClass<covariant Aspect> $aspect
     *
     * @throws AspectException If the advice method is not public or the attribute is unsupported
     */
    protected function getAdvice(
        AbstractInterceptor $interceptorAttribute,
        ReflectionClass $aspect,
        ReflectionMethod $aspectMethod,
    ): InterceptorInterface {
        if (!$aspectMethod->isPublic()) {
            throw AspectException::nonPublicAdviceMethod($aspectMethod);
        }

        $aspectClass = $aspect->name;
        $methodName  = $aspectMethod->getName();
        $order       = $interceptorAttribute->order;
        $expression  = $interceptorAttribute->expression;

        return match (true) {
            $interceptorAttribute instanceof Before => Interceptor::before($aspectClass, $methodName, $order, $expression),
            $interceptorAttribute instanceof After => Interceptor::after($aspectClass, $methodName, $order, $expression),
            $interceptorAttribute instanceof Around => Interceptor::around($aspectClass, $methodName, $order, $expression),
            $interceptorAttribute instanceof AfterThrowing => Interceptor::afterThrowing($aspectClass, $methodName, $order, $expression),
            default => throw AspectException::unsupportedAdviceAttribute($interceptorAttribute),
        };
    }
}
