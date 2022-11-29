<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Closure;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\AfterThrowingInterceptor;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Pointcut;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Lang\Attribute;
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\AfterThrowing;
use Go\Lang\Attribute\Around;
use Go\Lang\Attribute\BaseInterceptor;
use Go\Lang\Attribute\Before;
use ReflectionAttribute;
use ReflectionClass;
use UnexpectedValueException;

/**
 * General aspect loader add common support for general advices, declared as attributes
 */
class GeneralAspectLoaderExtension extends AbstractAspectLoaderExtension
{
    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param Aspect          $aspect           Instance of aspect
     * @param ReflectionClass $reflectionAspect Reflection of point
     *
     * @return array<string,Pointcut>|array<string,Advisor>
     *
     * @throws UnexpectedValueException
     */
    public function load(Aspect $aspect, ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getMethods() as $aspectMethod) {
            $methodId   = $reflectionAspect->getName() . '->'. $aspectMethod->getName();
            $attributes = $aspectMethod->getAttributes(
                Attribute\BaseAttribute::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            foreach ($attributes as $attribute) {
                $attributeInstance = $attribute->newInstance();
                if ($attributeInstance instanceof Attribute\Pointcut) {
                    $loadedItems[$methodId] = $this->parsePointcut($aspect, $reflectionAspect, $attributeInstance->value);
                } elseif ($attributeInstance instanceof Attribute\BaseInterceptor) {
                    $pointcut       = $this->parsePointcut($aspect, $reflectionAspect, $attributeInstance->value);
                    $adviceCallback = $aspectMethod->getClosure($aspect);
                    $interceptor    = $this->getInterceptor($attributeInstance, $adviceCallback);

                    $loadedItems[$methodId] = new DefaultPointcutAdvisor($pointcut, $interceptor);
                } else {
                    throw new UnexpectedValueException('Unsupported attribute class: ' . get_class($attribute));
                }
            }
        }

        return $loadedItems;
    }

    /**
     * Returns an interceptor instance by meta-type attribute and closure
     *
     * @throws UnexpectedValueException For unsupported attributes
     */
    protected function getInterceptor(BaseInterceptor $metaInformation, Closure $adviceCallback): Interceptor
    {
        $adviceOrder        = $metaInformation->order;
        $pointcutExpression = $metaInformation->value;
        switch (true) {
            case ($metaInformation instanceof Before):
                return new BeforeInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof After):
                return new AfterInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof Around):
                return new AroundInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof AfterThrowing):
                return new AfterThrowingInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            default:
                throw new UnexpectedValueException('Unsupported method meta class: ' . get_class($metaInformation));
        }
    }
}
