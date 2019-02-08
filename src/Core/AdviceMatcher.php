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
use function count;

/**
 * Advice matcher returns the list of advices for the specific point of code
 */
class AdviceMatcher
{
    /**
     * Flag to enable/disable support of global function interception
     *
     * @var bool
     */
    private $isInterceptFunctions;

    /**
     * Constructor
     *
     * @param bool $isInterceptFunctions Optional flag to enable function interception
     */
    public function __construct(bool $isInterceptFunctions = false)
    {
        $this->isInterceptFunctions = $isInterceptFunctions;
    }

    /**
     * Returns list of function advices for namespace
     *
     * @param Aop\Advisor[] $advisors List of advisor to match
     *
     * @return Aop\Advice[] List of advices for class
     */
    public function getAdvicesForFunctions(ReflectionFileNamespace $namespace, array $advisors): array
    {
        if (!$this->isInterceptFunctions) {
            return [];
        }

        $advices = [];

        foreach ($advisors as $advisorId => $advisor) {
            if ($advisor instanceof Aop\PointcutAdvisor) {
                $pointcut = $advisor->getPointcut();
                $isFunctionAdvisor = $pointcut->getKind() & Aop\PointFilter::KIND_FUNCTION;
                if ($isFunctionAdvisor && $pointcut->getClassFilter()->matches($namespace)) {
                    $advices[] = $this->getFunctionAdvicesFromAdvisor($namespace, $advisor, $advisorId, $pointcut);
                }
            }
        }

        if (count($advices) > 0) {
            $advices = array_merge_recursive(...$advices);
        }

        return $advices;
    }

    /**
     * Return list of advices for class
     *
     * @param array|Aop\Advisor[] $advisors List of advisor to match
     *
     * @return Aop\Advice[] List of advices for class
     */
    public function getAdvicesForClass(ReflectionClass $class, array $advisors): array
    {
        $classAdvices = [];
        $parentClass  = $class->getParentClass();

        $originalClass = $class;
        if ($parentClass && strpos($parentClass->name, AspectContainer::AOP_PROXIED_SUFFIX) !== false) {
            $originalClass = $parentClass;
        }

        foreach ($advisors as $advisorId => $advisor) {
            if ($advisor instanceof Aop\PointcutAdvisor) {
                $pointcut = $advisor->getPointcut();
                if ($pointcut->getClassFilter()->matches($class)) {
                    $classAdvices[] = $this->getAdvicesFromAdvisor($originalClass, $advisor, $advisorId, $pointcut);
                }
            }

            if ($advisor instanceof Aop\IntroductionAdvisor) {
                if ($advisor->getClassFilter()->matches($class)) {
                    $classAdvices[] = $this->getIntroductionFromAdvisor($originalClass, $advisor, $advisorId);
                }
            }
        }
        if (count($classAdvices) > 0) {
            $classAdvices = array_merge_recursive(...$classAdvices);
        }

        return $classAdvices;
    }

    /**
     * Returns list of advices from advisor and point filter
     */
    private function getAdvicesFromAdvisor(
        ReflectionClass $class,
        Aop\PointcutAdvisor $advisor,
        string $advisorId,
        Aop\PointFilter $filter
    ): array {
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
                // abstract and parent final methods could not be woven
                $isParentFinalMethod = ($method->getDeclaringClass()->name !== $class->name) && $method->isFinal();
                if ($isParentFinalMethod || $method->isAbstract()) {
                    continue;
                }

                if ($filter->matches($method, $class)) {
                    $prefix = $method->isStatic() ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;
                    $classAdvices[$prefix][$method->name][$advisorId] = $advisor->getAdvice();
                }
            }
        }

        // Check properties in class only for property filters
        if ($filterKind & Aop\PointFilter::KIND_PROPERTY) {
            $mask = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE;
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
     */
    private function getIntroductionFromAdvisor(
        ReflectionClass $class,
        Aop\IntroductionAdvisor $advisor,
        string $advisorId
    ): array {
        $classAdvices = [];
        // Do not make introduction for traits
        if ($class->isTrait()) {
            return $classAdvices;
        }

        /** @var Aop\IntroductionInfo $introduction */
        $introduction    = $advisor->getAdvice();
        $introducedTrait = $introduction->getTrait();
        if (!empty($introducedTrait)) {
            $introducedTrait = '\\' . ltrim($introducedTrait, '\\');

            $classAdvices[AspectContainer::INTRODUCTION_TRAIT_PREFIX][$advisorId] = $introducedTrait;
        }
        $introducedInterface = $introduction->getInterface();
        if (!empty($introducedInterface)) {
            $introducedInterface = '\\' . ltrim($introducedInterface, '\\');

            $classAdvices[AspectContainer::INTRODUCTION_INTERFACE_PREFIX][$advisorId] = $introducedInterface;
        }

        return $classAdvices;
    }

    /**
     * Returns list of function advices for specific namespace
     */
    private function getFunctionAdvicesFromAdvisor(
        ReflectionFileNamespace $namespace,
        Aop\PointcutAdvisor $advisor,
        string $advisorId,
        Aop\PointFilter $pointcut
    ): array {
        $functions = [];
        $advices   = [];

        $listOfGlobalFunctions = get_defined_functions();
        foreach ($listOfGlobalFunctions['internal'] as $functionName) {
            $functions[$functionName] = new NamespacedReflectionFunction($functionName, $namespace->getName());
        }

        foreach ($functions as $functionName => $function) {
            if ($pointcut->matches($function, $namespace)) {
                $advices[AspectContainer::FUNCTION_PREFIX][$functionName][$advisorId] = $advisor->getAdvice();
            }
        }

        return $advices;
    }
}
