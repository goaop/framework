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
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\AfterThrowingInterceptor;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Pointcut;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Lang\Annotation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\AfterThrowing;
use Go\Lang\Annotation\Around;
use Go\Lang\Annotation\BaseInterceptor;
use Go\Lang\Annotation\Before;
use ReflectionClass;
use UnexpectedValueException;

use function get_class;

/**
 * General aspect loader add common support for general advices, declared as annotations
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
            $methodId    = $reflectionAspect->getName() . '->'. $aspectMethod->getName();
            $annotations = $this->reader->getMethodAnnotations($aspectMethod);

            foreach ($annotations as $annotation) {
                if ($annotation instanceof Annotation\Pointcut) {
                    $loadedItems[$methodId] = $this->parsePointcut($aspect, $reflectionAspect, $annotation->value);
                } elseif ($annotation instanceof Annotation\BaseInterceptor) {
                    $pointcut       = $this->parsePointcut($aspect, $reflectionAspect, $annotation->value);
                    $adviceCallback = $aspectMethod->getClosure($aspect);
                    $interceptor    = $this->getInterceptor($annotation, $adviceCallback);

                    $loadedItems[$methodId] = new DefaultPointcutAdvisor($pointcut, $interceptor);
                } else {
                    throw new UnexpectedValueException('Unsupported annotation class: ' . get_class($annotation));
                }
            }
        }

        return $loadedItems;
    }

    /**
     * Returns an interceptor instance by meta-type annotation and closure
     *
     * @throws UnexpectedValueException For unsupported annotations
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
