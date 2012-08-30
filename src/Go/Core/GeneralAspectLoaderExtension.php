<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use ReflectionMethod;


use Go\Aop\Aspect;
use Go\Aop\Advisor;
use Go\Lang\Annotation;
use Go\Aop\Support\DefaultPointcutAdvisor;

class GeneralAspectLoaderExtension implements AspectLoaderExtension
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
     * General aspect loader works only with methods
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
        $isSupported  = false;
        $isSupported |= $metaInformation instanceof Annotation\After;
        $isSupported |= $metaInformation instanceof Annotation\Around;
        $isSupported |= $metaInformation instanceof Annotation\Before;
        return $isSupported;
    }

    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param AspectContainer $container Instance of container
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     */
    public function load(AspectContainer $container, Aspect $aspect, $reflection, $metaInformation = null)
    {
        $adviceCallback = $this->getAdvice($aspect, $reflection);

        $pointcut = new \Go\Aop\Support\NameMatchMethodPointcut();
        $pointcut->setMappedName('*');

        if ($metaInformation instanceof Annotation\Before) {
            $advice = new \Go\Aop\Framework\MethodBeforeInterceptor($adviceCallback);
        }

        if ($metaInformation instanceof Annotation\After) {
            $advice = new \Go\Aop\Framework\MethodAfterInterceptor($adviceCallback);
        }

        if ($metaInformation instanceof Annotation\Around) {
            $advice = new \Go\Aop\Framework\MethodAroundInterceptor($adviceCallback);
        }

        $container->registerAdvisor(new DefaultPointcutAdvisor($pointcut, $advice));
    }


    /**
     * Returns an advice from aspect
     *
     * @param Aspect $aspect Aspect instance
     * @param ReflectionMethod $refMethod Reflection method of aspect
     *
     * @return callable Advice to call
     */
    private function getAdvice(Aspect $aspect, ReflectionMethod $refMethod)
    {
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            return $refMethod->getClosure($aspect);
        } else {
            return function () use ($aspect, $refMethod) {
                return $refMethod->invokeArgs($aspect, func_get_args());
            };
        }
    }

}