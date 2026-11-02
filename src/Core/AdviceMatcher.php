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

use Closure;
use Go\Aop;
use Go\Aop\IntroductionInfo;
use Go\Aop\PointcutAdvisor;
use Go\Aop\Pointcut;
use Go\Aop\Proxy;
use Go\Instrument\PathResolver;
use Go\ParserReflection\MissingAncestorPolicy;
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Advice matcher returns the list of advices for the specific point of code
 *
 * @internal Framework service, not a public extension point
 */
class AdviceMatcher implements AdviceMatcherInterface
{
    /**
     * Reflections of every internal function, built on the first function match of the request
     *
     * @var array<non-empty-string, ReflectionFunction>|null
     */
    private static ?array $internalFunctions = null;

    /**
     * Answers of the woven file filter by class name
     *
     * @var array<string, bool>
     */
    private array $isWovenClassFile = [];

    public function __construct(
        /**
         * Tells whether the class loader weaves a source file (resolved path): below the include paths and outside of
         * the exclude paths. Without it no class that is not loaded yet counts as woven, so inherited methods are
         * always intercepted in the child.
         *
         * @var (Closure(string): bool)|null
         */
        private readonly ?Closure $isWovenFile = null,
    ) {}

    /**
     * Returns list of function advices for namespace
     *
     * @param Aop\Advisor[] $advisors List of advisor to match
     *
     * @return array<string, array<string, array<string, Aop\Advice>>> List of advices for function
     */
    public function getAdvicesForFunctions(ReflectionFileNamespace $namespace, array $advisors): array
    {
        // Only advisors with a function pointcut are matched: without one a namespace costs a scan of the advisors
        $advices = [];

        foreach ($advisors as $advisorId => $advisor) {
            if ($advisor instanceof PointcutAdvisor) {
                $pointcut = $advisor->pointcut;
                $isFunctionAdvisor = $pointcut->kind & Pointcut::KIND_FUNCTION;
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
     * @param ReflectionClass<covariant object> $class
     * @param Aop\Advisor[] $advisors List of advisor to match
     *
     * @return array<string, array<string, array<string, Aop\Advice>>> List of advices for class
     */
    public function getAdvicesForClass(ReflectionClass $class, array $advisors): array
    {
        // Classes are matched, then woven, with partial reflection: an ancestor declared in code generated at runtime,
        // by a class_alias() or outside the Composer autoloader is left out instead of failing the class. Switched on
        // here, before any pointcut walks the ancestors, so that requests served from the cache never pay for it
        ReflectionEngine::setMissingAncestorPolicy(MissingAncestorPolicy::Partial);
        $classAdvices = [];

        foreach ($advisors as $advisorId => $advisor) {
            if ($advisor instanceof PointcutAdvisor) {
                $pointcut     = $advisor->pointcut;
                $matchesClass = $pointcut->matches($class);
                if ($matchesClass && ($pointcut->kind & Pointcut::KIND_CLASS)) {
                    foreach ($this->getClassAdvicesFromAdvisor($class, $advisor, $advisorId, $pointcut) as $prefix => $prefixAdvices) {
                        foreach ($prefixAdvices as $name => $nameAdvices) {
                            foreach ($nameAdvices as $advisorKey => $advice) {
                                $classAdvices[$prefix][$name][$advisorKey] = $advice;
                            }
                        }
                    }
                }

                if ($matchesClass) {
                    foreach ($this->getClassLevelAdvicesFromAdvisor($class, $advisor, $advisorId, $pointcut) as $prefix => $prefixAdvices) {
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
     * @param ReflectionClass<covariant object> $class
     * @return array<string, array<string, array<string, Aop\Advice>>>
     */
    private function getClassAdvicesFromAdvisor(
        ReflectionClass $class,
        PointcutAdvisor $advisor,
        string $advisorId,
        Pointcut $pointcut,
    ): array {
        $classAdvices = [];
        $pointcutKind = $pointcut->kind;
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
            $classAdvices = [...$classAdvices, ...$this->getIntroductionAdvices($advice)];
        }

        return $classAdvices;
    }

    /**
     * Returns list of advices from advisor and point filter
     *
     * @param ReflectionClass<covariant object> $class
     * @return array<string, array<string, array<string, Aop\Advice>>>
     */
    private function getClassLevelAdvicesFromAdvisor(
        ReflectionClass $class,
        PointcutAdvisor $advisor,
        string $advisorId,
        Pointcut $pointcut,
    ): array {
        $classAdvices = [];
        $pointcutKind = $pointcut->kind;

        // Check methods in class only for method filters
        if (($pointcutKind & Pointcut::KIND_METHOD) !== 0) {
            // Private methods are supported by the trait-based proxy engine: the original private method
            // body lives in the trait and is aliased as <method>OriginalAlias; the proxy overrides it with the
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

                if ($pointcut->matches($class, $method) && !$this->isInterceptedByAncestor($class, $method, $pointcut)) {
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
                // Hooked properties cannot be intercepted — see #561 for investigation details.
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
     * Checks whether a woven ancestor already intercepts the method with the same pointcut (issue #781)
     *
     * A method inherited from a parent class (`$method->class` names the parent, trait methods name the class itself)
     * is called through `parent::method()` by the proxy of the child. When an ancestor between the parent and the
     * class holding the method is woven and the pointcut matches the method there, that ancestor intercepts every
     * execution, and the child must not run the advice a second time. When none is, as for a parent outside the
     * include paths or one the pointcut does not match, the child is the only place to intercept it.
     *
     * @param ReflectionClass<covariant object> $class
     */
    private function isInterceptedByAncestor(ReflectionClass $class, ReflectionMethod $method, Pointcut $pointcut): bool
    {
        if ($method->class === $class->name) {
            return false;
        }

        for ($ancestor = $class->getParentClass(); $ancestor !== false; $ancestor = $ancestor->getParentClass()) {
            if (
                $this->isWovenClass($ancestor)
                && $ancestor->hasMethod($method->name)
                && $pointcut->matches($ancestor)
                && $pointcut->matches($ancestor, $ancestor->getMethod($method->name))
            ) {
                return true;
            }
            if ($ancestor->name === $method->class) {
                break;
            }
        }

        return false;
    }

    /**
     * Checks whether the class loader weaves the class: it is declared in a file the loader weaves
     *
     * The answer depends on the configuration only, never on the classes loaded by the current process, as the woven
     * child is cached and served to other processes: a parent loaded before the kernel was initialized still counts as
     * woven. A loaded proxy is woven; its reflection (native one) reports the proxy file in the cache directory.
     *
     * @param ReflectionClass<covariant object> $class
     */
    private function isWovenClass(ReflectionClass $class): bool
    {
        if (isset($this->isWovenClassFile[$class->name])) {
            return $this->isWovenClassFile[$class->name];
        }
        if (class_exists($class->name, false) && is_subclass_of($class->name, Proxy::class)) {
            return $this->isWovenClassFile[$class->name] = true;
        }
        $fileName = $class->getFileName();
        if ($this->isWovenFile === null || !is_string($fileName)) {
            return $this->isWovenClassFile[$class->name] = false;
        }
        $resolvedFileName = PathResolver::realpath($fileName);

        return $this->isWovenClassFile[$class->name] = ($this->isWovenFile)(is_string($resolvedFileName) ? $resolvedFileName : $fileName);
    }

    /**
     * Returns list of introduction advices from advisor
     *
     * @return array<string, array<string, array<string, IntroductionInfo>>>
     */
    private function getIntroductionAdvices(IntroductionInfo $introduction): array
    {
        $classAdvices = [];

        $introducedTrait = $introduction->trait;
        if (!empty($introducedTrait)) {
            $introducedTrait = '\\' . ltrim($introducedTrait, '\\');

            $classAdvices[AspectContainer::INTRODUCTION_TRAIT_PREFIX] = ['root' => [$introducedTrait => $introduction]];
        }
        $introducedInterface = $introduction->interface;
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
        Pointcut $pointcut,
    ): array {
        $advices = [];

        // Internal functions never change during a request: reflect them once, not per advisor and namespace
        if (self::$internalFunctions === null) {
            self::$internalFunctions = [];
            foreach (get_defined_functions()['internal'] as $functionName) {
                self::$internalFunctions[$functionName] = new ReflectionFunction($functionName);
            }
        }

        foreach (self::$internalFunctions as $functionName => $function) {
            if ($pointcut->matches($namespace, $function)) {
                $advices[AspectContainer::FUNCTION_PREFIX][$functionName][$advisorId] = $advisor->getAdvice();
            }
        }

        return $advices;
    }
}
