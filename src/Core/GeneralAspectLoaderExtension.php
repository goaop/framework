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
use Go\Aop\Framework;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Pointcut;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Lang\Annotation;
use Go\Lang\Annotation\BaseInterceptor;
use ReflectionMethod;
use Reflector;

use function get_class;

/**
 * General aspect loader add common support for general advices, declared as annotations
 */
class GeneralAspectLoaderExtension extends AbstractAspectLoaderExtension
{

    /**
     * General aspect loader works with annotations from aspect
     */
    public function getKind(): string
    {
        return self::KIND_ANNOTATION;
    }

    /**
     * General aspect loader works only with methods of aspect
     */
    public function getTargets(): array
    {
        return [self::TARGET_METHOD];
    }

    /**
     * Checks if loader is able to handle specific point of aspect
     *
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     *
     * @return boolean true if extension is able to create an advisor from reflection and metaInformation
     */
    public function supports(Aspect $aspect, $reflection, $metaInformation = null): bool
    {
        return $metaInformation instanceof Annotation\Interceptor
                || $metaInformation instanceof Annotation\Pointcut;
    }

    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param Aspect $aspect Instance of aspect
     * @param Reflector|ReflectionMethod $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     *
     * @return array|Pointcut[]|Advisor[]
     *
     * @throws \UnexpectedValueException
     */
    public function load(Aspect $aspect, Reflector $reflection, $metaInformation = null): array
    {
        $loadedItems    = [];
        $pointcut       = $this->parsePointcut($aspect, $reflection, $metaInformation->value);
        $methodId       = get_class($aspect) . '->' . $reflection->name;
        $adviceCallback = $reflection->getClosure($aspect);

        switch (true) {
            // Register a pointcut by its name
            case ($metaInformation instanceof Annotation\Pointcut):
                $loadedItems[$methodId] = $pointcut;
                break;

            case ($pointcut instanceof Pointcut):
                $advice = $this->getInterceptor($metaInformation, $adviceCallback);

                $loadedItems[$methodId] = new DefaultPointcutAdvisor($pointcut, $advice);
                break;

            default:
                throw new \UnexpectedValueException('Unsupported pointcut class: ' . get_class($pointcut));
        }

        return $loadedItems;
    }

    /**
     * Returns an interceptor instance by meta-type annotation and closure
     *
     * @throws \UnexpectedValueException For unsupported annotations
     */
    protected function getInterceptor(BaseInterceptor $metaInformation, Closure $adviceCallback): Interceptor
    {
        $adviceOrder        = $metaInformation->order;
        $pointcutExpression = $metaInformation->value;
        switch (true) {
            case ($metaInformation instanceof Annotation\Before):
                return new Framework\BeforeInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof Annotation\After):
                return new Framework\AfterInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof Annotation\Around):
                return new Framework\AroundInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            case ($metaInformation instanceof Annotation\AfterThrowing):
                return new Framework\AfterThrowingInterceptor($adviceCallback, $adviceOrder, $pointcutExpression);

            default:
                throw new \UnexpectedValueException('Unsupported method meta class: ' . get_class($metaInformation));
        }
    }
}
