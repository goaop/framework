<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Aspect;
use Go\Aop\Framework;
use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Aop\Support;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Lang\Annotation;
use ReflectionMethod;
use ReflectionProperty;

/**
 * General aspect loader add common support for general advices, declared as annotations
 */
class GeneralAspectLoaderExtension extends AbstractAspectLoaderExtension
{

    /**
     * General aspect loader works with annotations from aspect
     *
     * For extension that works with annotations additional metaInformation will be passed
     *
     * @return string
     */
    public function getKind()
    {
        return self::KIND_ANNOTATION;
    }

    /**
     * General aspect loader works only with methods of aspect
     *
     * @return string|array
     */
    public function getTarget()
    {
        return self::TARGET_METHOD;
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
    public function supports(Aspect $aspect, $reflection, $metaInformation = null)
    {
        return $metaInformation instanceof Annotation\Interceptor
                || $metaInformation instanceof Annotation\Pointcut;
    }

    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param AspectContainer $container Instance of container
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     *
     * @throws \UnexpectedValueException
     */
    public function load(AspectContainer $container, Aspect $aspect, $reflection, $metaInformation = null)
    {
        $pointcut       = $this->parsePointcut($aspect, $reflection, $metaInformation);
        $methodId       = get_class($aspect).'->'.$reflection->name;
        $adviceCallback = Framework\BaseAdvice::fromAspectReflection($aspect, $reflection);

        if (isset($metaInformation->scope) && $metaInformation->scope !== 'aspect') {
            $scope = $metaInformation->scope;
            $adviceCallback = Framework\BaseAdvice::createScopeCallback($aspect, $adviceCallback, $scope);
        }

        $isPointFilter  = $pointcut instanceof PointFilter;
        switch (true) {
            // Register a pointcut by its name
            case ($metaInformation instanceof Annotation\Pointcut):
                $container->registerPointcut($pointcut, $methodId);
                break;

            case ($isPointFilter && ($pointcut->getKind() & PointFilter::KIND_METHOD)):
                $advice = $this->getMethodInterceptor($metaInformation, $adviceCallback);
                if ($pointcut->getKind() & PointFilter::KIND_DYNAMIC) {
                    $advice = new Framework\DynamicMethodMatcherInterceptor(
                        $pointcut,
                        $advice
                    );
                }
                $container->registerAdvisor(new DefaultPointcutAdvisor($pointcut, $advice), $methodId);
                break;

            case ($isPointFilter && ($pointcut->getKind() & PointFilter::KIND_PROPERTY)):
                $advice = $this->getPropertyInterceptor($metaInformation, $adviceCallback);
                $container->registerAdvisor(new DefaultPointcutAdvisor($pointcut, $advice), $methodId);
                break;

            case ($isPointFilter && ($pointcut->getKind() & PointFilter::KIND_FUNCTION)):
                $advice = $this->getFunctionInterceptor($metaInformation, $adviceCallback);
                $container->registerAdvisor(new DefaultPointcutAdvisor($pointcut, $advice), $methodId);
                break;

            default:
                throw new \UnexpectedValueException("Unsupported pointcut class: " . get_class($pointcut));
        }
    }

    /**
     * @param $metaInformation
     * @param $adviceCallback
     * @return \Go\Aop\Intercept\MethodInterceptor
     * @throws \UnexpectedValueException
     */
    protected function getMethodInterceptor($metaInformation, $adviceCallback)
    {
        switch (true) {
            case ($metaInformation instanceof Annotation\Before):
                return new Framework\MethodBeforeInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\After):
                return new Framework\MethodAfterInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\Around):
                return new Framework\MethodAroundInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\AfterThrowing):
                return new Framework\MethodAfterThrowingInterceptor($adviceCallback, $metaInformation->order);

            default:
                throw new \UnexpectedValueException("Unsupported method meta class: " . get_class($metaInformation));
        }
    }

    /**
     * @param $metaInformation
     * @param $adviceCallback
     * @return \Go\Aop\Intercept\MethodInterceptor
     * @throws \UnexpectedValueException
     */
    protected function getFunctionInterceptor($metaInformation, $adviceCallback)
    {
        switch (true) {
            case ($metaInformation instanceof Annotation\Around):
                return new Framework\FunctionAroundInterceptor($adviceCallback, $metaInformation->order);

            default:
                throw new \UnexpectedValueException("Unsupported method meta class: " . get_class($metaInformation));
        }
    }

    /**
     * @param $metaInformation
     * @param $adviceCallback
     * @return \Go\Aop\Intercept\FieldAccess
     * @throws \UnexpectedValueException
     */
    protected function getPropertyInterceptor($metaInformation, $adviceCallback)
    {
        switch (true) {
            case ($metaInformation instanceof Annotation\Before):
                return new Framework\FieldBeforeInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\After):
                return new Framework\FieldAfterInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\Around):
                return new Framework\FieldAroundInterceptor($adviceCallback, $metaInformation->order);

            default:
                throw new \UnexpectedValueException("Unsupported method meta class: " . get_class($metaInformation));
        }
    }
}
