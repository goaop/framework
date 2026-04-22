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
use Go\Aop\IntroductionInfo;
use Go\Aop\PointcutAdvisor;
use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

use function count;

/**
 * Advice matcher returns the list of advices for the specific point of code
 */
class AdviceMatcher implements AdviceMatcherInterface
{

    /**
     * Constructor
     *
     * @param bool $isInterceptFunctions Optional flag to enable function interception
     */
    public function __construct(private readonly bool $isInterceptFunctions = false)
    {
    }

    /**
     * Returns list of function advices for namespace
     *
     * @param Aop\Advisor[] $advisors List of advisor to match
     *
     * @return array<string, array<string, array<string, Aop\Advice>>> List of advices for function
     */
    public function getAdvicesForFunctions(ReflectionFileNamespace $namespace, array $advisors): array
    {
        if (!$this->isInterceptFunctions) {
            return [];
        }

        $advices = [];

        foreach ($advisors as $advisorId => $advisor) {
            if ($advisor instanceof PointcutAdvisor) {
                $pointcut = $advisor->getPointcut();
                $isFunctionAdvisor = $pointcut->getKind() & Pointcut::KIND_FUNCTION;
                if ($isFunctionAdvisor && $pointcut->matches($namespace)) {
                    foreach ($this->getFunctionAdvicesFromAdvisor($namespace, $advisor, $advisorId, $pointcut) as $prefix => $prefixAdvices) {
                        foreach ($prefixAdvices as $name => $nameAdvices) {
                            foreach ($nameAdvices as $advisorKey => $advice) {
                                $advices[$prefix][$name][$advisorKey] = $advice;
                            }
                        }
                    }
                }
            }
        }

        return $advices;
    }

    /**
     * Return list of advices for class
     *
     * @param ReflectionClass<object> $class
     * @param Aop\Advisor[] $advisors List of advisor to match
     *
     * @return array<string, array<string, array<string, Aop\Advice>>> List of advices for class
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
            if ($advisor instanceof PointcutAdvisor) {
                $pointcut = $advisor->getPointcut();
                if (($pointcut->getKind() & Pointcut::KIND_CLASS) && $pointcut->matches($class)) {
                    foreach ($this->getClassAdvicesFromAdvisor($originalClass, $advisor, $advisorId, $pointcut) as $prefix => $prefixAdvices) {
                        foreach ($prefixAdvices as $name => $nameAdvices) {
                            foreach ($nameAdvices as $advisorKey => $advice) {
                                $classAdvices[$prefix][$name][$advisorKey] = $advice;
                            }
                        }
                    }
                }

                if ($pointcut->matches($class)) {
                    foreach ($this->getClassLevelAdvicesFromAdvisor($originalClass, $advisor, $advisorId, $pointcut) as $prefix => $prefixAdvices) {
                        foreach ($prefixAdvices as $name => $nameAdvices) {
                            foreach ($nameAdvices as $advisorKey => $advice) {
                                $classAdvices[$prefix][$name][$advisorKey] = $advice;
                            }
                        }
                    }
                }
            }
        }

        return $classAdvices;
    }

    /**
     * Returns list of class advices from advisor and point filter
     *
     * @param ReflectionClass<object> $class
     * @return array<string, array<string, array<string, Aop\Advice>>>
     */
    private function getClassAdvicesFromAdvisor(
        ReflectionClass $class,
        PointcutAdvisor $advisor,
        string $advisorId,
        Pointcut $pointcut
    ): array {
        $classAdvices = [];
        $pointcutKind = $pointcut->getKind();
        $advice       = $advisor->getAdvice();

        // Dynamic initialization (creation of instance with new)
        if (($pointcutKind & Pointcut::KIND_INIT) !== 0) {
            $classAdvices[AspectContainer::INIT_PREFIX] = ['root' => [$advisorId => $advice]];
        }
        // Static initalization (when class just loaded)
        if (($pointcutKind & Pointcut::KIND_STATIC_INIT) !== 0) {
            $classAdvices[AspectContainer::STATIC_INIT_PREFIX] = ['root' => [$advisorId => $advice]];
        }
        // Introduction which can add interfaces or traits
        if (($pointcutKind & Pointcut::KIND_INTRODUCTION) !== 0 && $advice instanceof IntroductionInfo && !$class->isTrait()) {
            $classAdvices = [...$this->getIntroductionAdvices($advice)];
        }

        return $classAdvices;
    }

    /**
     * Returns list of advices from advisor and point filter
     *
     * @param ReflectionClass<object> $class
     * @return array<string, array<string, array<string, Aop\Advice>>>
     */
    private function getClassLevelAdvicesFromAdvisor(
        ReflectionClass $class,
        PointcutAdvisor $advisor,
        string $advisorId,
        Pointcut $pointcut
    ): array {
        $classAdvices = [];
        $pointcutKind = $pointcut->getKind();

        // Check methods in class only for method filters
        if (($pointcutKind & Pointcut::KIND_METHOD) !== 0) {
            // Private methods are supported by the trait-based proxy engine: the original private method
            // body lives in the trait and is aliased as __aop__<method>; the proxy overrides it with the
            // same private visibility so the join-point chain is invoked on every in-class call.
            $mask = ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE;
            foreach ($class->getMethods($mask) as $method) {
                // abstract and parent final methods could not be woven
                $isParentFinalMethod = ($method->getDeclaringClass()->name !== $class->name) && $method->isFinal();
                if ($isParentFinalMethod || $method->isAbstract()) {
                    continue;
                }
                // Private methods inherited from a parent class cannot be overridden — skip them.
                // Only private methods declared in the class itself (or its trait body) can be intercepted.
                if ($method->isPrivate() && $method->getDeclaringClass()->name !== $class->name) {
                    continue;
                }

                if ($pointcut->matches($class, $method)) {
                    $prefix = $method->isStatic() ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;
                    $classAdvices[$prefix][$method->name][$advisorId] = $advisor->getAdvice();
                }
            }
        }

        // Check properties in class only for property filters
        if (($pointcutKind & Pointcut::KIND_PROPERTY) !== 0) {
            $mask = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE;
            foreach ($class->getProperties($mask) as $property) {
                $isPropertyDeclaredInParent = $property->getDeclaringClass()->name !== $class->name;
                if ($property->isStatic() || $property->isReadOnly()) {
                    continue;
                }
                if ($isPropertyDeclaredInParent && $property->isFinal()) {
                    continue;
                }
                // Hooked properties are out of scope for MVP native field-access weaving.
                if ($property->hasHooks()) {
                    continue;
                }
                if ($pointcut->matches($class, $property)) {
                    $classAdvices[AspectContainer::PROPERTY_PREFIX][$property->name][$advisorId] = $advisor->getAdvice();
                }
            }
        }

        return $classAdvices;
    }

    /**
     * Returns list of introduction advices from advisor
     *
     * @return array<string, array<string, array<string, IntroductionInfo>>>
     */
    private function getIntroductionAdvices(IntroductionInfo $introduction): array {
        $classAdvices = [];

        $introducedTrait = $introduction->getTrait();
        if (!empty($introducedTrait)) {
            $introducedTrait = '\\' . ltrim($introducedTrait, '\\');

            $classAdvices[AspectContainer::INTRODUCTION_TRAIT_PREFIX] = ['root' => [$introducedTrait => $introduction]];
        }
        $introducedInterface = $introduction->getInterface();
        if (!empty($introducedInterface)) {
            $introducedInterface = '\\' . ltrim($introducedInterface, '\\');

            $classAdvices[AspectContainer::INTRODUCTION_INTERFACE_PREFIX] = ['root' => [$introducedInterface => $introduction]];
        }

        return $classAdvices;
    }

    /**
     * Returns list of function advices for specific namespace
     *
     * @return array<string, array<string, array<string, Aop\Advice>>>
     */
    private function getFunctionAdvicesFromAdvisor(
        ReflectionFileNamespace $namespace,
        PointcutAdvisor $advisor,
        string $advisorId,
        Pointcut $pointcut
    ): array {
        $functions = [];
        $advices   = [];

        $listOfGlobalFunctions = get_defined_functions();
        foreach ($listOfGlobalFunctions['internal'] as $functionName) {
            $functions[$functionName] = new ReflectionFunction($functionName);
        }

        foreach ($functions as $functionName => $function) {
            if ($pointcut->matches($namespace, $function)) {
                $advices[AspectContainer::FUNCTION_PREFIX][$functionName][$advisorId] = $advisor->getAdvice();
            }
        }

        return $advices;
    }
}
