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

use Go\Aop;
use Go\Aop\Support\NamespacedReflectionFunction;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Advice matcher returns the list of advices for the specific point of code
 */
class AdviceMatcher
{
    /**
     * Loader of aspects
     *
     * @var AspectLoader
     */
    protected $loader;

    /**
     * Flag to enable/disable support of global function interception
     *
     * @var bool
     */
    private $isInterceptFunctions = false;

    /**
     * Constructor
     *
     * @param AspectLoader $loader Instance of aspect loader
     * @param bool $isInterceptFunctions Optional flag to enable function interception
     */
    public function __construct(AspectLoader $loader, $isInterceptFunctions = false)
    {
        $this->loader = $loader;

        $this->isInterceptFunctions = $isInterceptFunctions;
    }

    /**
     * Returns list of function advices for namespace
     *
     * @param ReflectionFileNamespace $namespace
     * @param array|Aop\Advisor[] $advisors List of advisor to match
     *
     * @return array
     */
    public function getAdvicesForFunctions(ReflectionFileNamespace $namespace, array $advisors)
    {
        if (!$this->isInterceptFunctions || $namespace->getName() == 'no-namespace') {
            return [];
        }

        $advices = [];

        foreach ($advisors as $advisorId => $advisor) {

            if ($advisor instanceof Aop\PointcutAdvisor) {

                $pointcut = $advisor->getPointcut();
                $isFunctionAdvisor = $pointcut->getKind() & Aop\PointFilter::KIND_FUNCTION;
                if ($isFunctionAdvisor && $pointcut->getClassFilter()->matches($namespace)) {
                    $advices = array_merge_recursive(
                        $advices,
                        $this->getFunctionAdvicesFromAdvisor($namespace, $advisor, $advisorId, $pointcut)
                    );
                }
            }
        }

        return $advices;
    }

    /**
     * Return list of advices for class
     *
     * @param ReflectionClass $class Class to advise
     * @param array|Aop\Advisor[] $advisors List of advisor to match
     *
     * @return array|Aop\Advice[] List of advices for class
     */
    public function getAdvicesForClass(ReflectionClass $class, array $advisors)
    {
        $classAdvices = [];
        $parentClass  = $class->getParentClass();

        if ($parentClass && preg_match('/' . AspectContainer::AOP_PROXIED_SUFFIX . '$/', $parentClass->name)) {
            $originalClass = $parentClass;
        } else {
            $originalClass = $class;
        }

        foreach ($advisors as $advisorId => $advisor) {

            if ($advisor instanceof Aop\PointcutAdvisor) {

                $pointcut = $advisor->getPointcut();
                if ($pointcut->getClassFilter()->matches($class)) {
                    $classAdvices = array_merge_recursive(
                        $classAdvices,
                        $this->getAdvicesFromAdvisor($originalClass, $advisor, $advisorId, $pointcut)
                    );
                }
            }

            if ($advisor instanceof Aop\IntroductionAdvisor) {
                if ($advisor->getClassFilter()->matches($class)) {
                    $classAdvices = array_merge_recursive(
                        $classAdvices,
                        $this->getIntroductionFromAdvisor($originalClass, $advisor, $advisorId)
                    );
                }
            }
        }

        return $classAdvices;
    }

    /**
     * Returns list of advices from advisor and point filter
     *
     * @param ReflectionClass $class Class to inject advices
     * @param Aop\PointcutAdvisor $advisor Advisor for class
     * @param string $advisorId Identifier of advisor
     * @param Aop\PointFilter $filter Filter for points
     *
     * @return array
     */
    private function getAdvicesFromAdvisor(
        ReflectionClass $class,
        Aop\PointcutAdvisor $advisor,
        $advisorId,
        Aop\PointFilter $filter)
    {
        $classAdvices = [];
        $filterKind   = $filter->getKind();

        // Check class only for class filters
        if ($filterKind & Aop\PointFilter::KIND_CLASS) {
            if ($filter->matches($class)) {
                // Dynamic initialization
                if ($filterKind & Aop\PointFilter::KIND_INIT) {
                    $classAdvices[AspectContainer::INIT_PREFIX]['root'][$advisorId] = $advisor->getAdvice();
                }
                // Static initalization
                if ($filterKind & Aop\PointFilter::KIND_STATIC_INIT) {
                    $classAdvices[AspectContainer::STATIC_INIT_PREFIX]['root'][$advisorId] = $advisor->getAdvice();
                }
            }
        }

        // Check methods in class only for method filters
        if ($filterKind & Aop\PointFilter::KIND_METHOD) {

            $mask = ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
            foreach ($class->getMethods($mask) as $method) {
                if ($filter->matches($method, $class)) {
                    $prefix = $method->isStatic() ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;
                    $classAdvices[$prefix][$method->name][$advisorId] = $advisor->getAdvice();
                }
            }
        }

        // Check properties in class only for property filters
        if ($filterKind & Aop\PointFilter::KIND_PROPERTY) {
            $mask = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
            foreach ($class->getProperties($mask) as $property) {
                if ($filter->matches($property, $class) && !$property->isStatic()) {
                    $classAdvices[AspectContainer::PROPERTY_PREFIX][$property->name][$advisorId] = $advisor->getAdvice();
                }
            }
        }

        return $classAdvices;
    }

    /**
     * Returns list of introduction advices from advisor
     *
     * @param ReflectionClass $class Class to inject advices
     * @param Aop\IntroductionAdvisor $advisor Advisor for class
     * @param string $advisorId Identifier of advisor
     *
     * @return array
     */
    private function getIntroductionFromAdvisor(
        ReflectionClass $class,
        Aop\IntroductionAdvisor $advisor,
        $advisorId)
    {
        $classAdvices = [];
        // Do not make introduction for traits
        if ($class->isTrait()) {
            return $classAdvices;
        }

        $advice = $advisor->getAdvice();

        $classAdvices[AspectContainer::INTRODUCTION_TRAIT_PREFIX][$advisorId] = $advice;

        return $classAdvices;
    }

    /**
     * Returns list of function advices for specific namespace
     *
     * @param ReflectionFileNamespace $namespace
     * @param Aop\PointcutAdvisor $advisor Advisor for class
     * @param string $advisorId Identifier of advisor
     * @param Aop\PointFilter $pointcut Filter for points
     *
     * @return array
     */
    private function getFunctionAdvicesFromAdvisor(
        ReflectionFileNamespace $namespace,
        Aop\PointcutAdvisor $advisor,
        $advisorId,
        Aop\PointFilter $pointcut)
    {
        $functions = [];
        $advices   = [];

        $listOfGlobalFunctions = get_defined_functions();
        foreach ($listOfGlobalFunctions['internal'] as $functionName) {
            $functions[$functionName] = new NamespacedReflectionFunction($functionName, $namespace->getName());
        }

        foreach ($functions as $functionName=>$function) {
            if ($pointcut->matches($function, $namespace)) {
                $advices[AspectContainer::FUNCTION_PREFIX][$functionName][$advisorId] = $advisor->getAdvice();
            }
        }

        return $advices;
    }
}
