<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use Go\Aop;

use Doctrine\Common\Annotations\AnnotationReader;
use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionFileNamespace;

/**
 * Aspect container contains list of all pointcuts and advisors
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
     * Instance of container for aspect
     *
     * @var AspectContainer
     */
    protected $container;

    /**
     * List of resources that was loaded
     *
     * @var array
     */
    protected $loadedResources = array();

    /**
     * Temporary flag to enable|disable function advising
     *
     * @var bool
     */
    private $funcEnabled = false;

    public function __construct(AspectLoader $loader, AspectContainer $container)
    {
        $this->loader    = $loader;
        $this->container = $container;

        $this->funcEnabled |= defined('GO_INTERCEPT_FUNCTIONS');
    }

    /**
     *
     * Returns list of function advices for namespace
     *
     * @param ReflectionFileNamespace $namespace
     *
     * @return array
     */
    public function getAdvicesForFunctions($namespace)
    {
        // TODO: remove after stabilization of functionality
        if (!$this->funcEnabled || $namespace->getName() == 'no-namespace') {
            return array();
        }

        $advices = array();

        if ($this->loadedResources != $this->container->getResources()) {
            $this->loadAdvisorsAndPointcuts();
        }

        foreach ($this->container->getByTag('advisor') as $advisor) {

            if ($advisor instanceof Aop\PointcutAdvisor) {

                $pointcut = $advisor->getPointcut();
                $isFunctionAdvisor = $pointcut->getKind() & Aop\PointFilter::KIND_FUNCTION;
                if ($isFunctionAdvisor && $pointcut->getClassFilter()->matches($namespace)) {
                    $advices = array_merge_recursive(
                        $advices,
                        $this->getFunctionAdvicesFromAdvisor($namespace, $advisor, $pointcut)
                    );
                }
            }
        }

        return $advices;
    }

    /**
     * Return list of advices for class
     *
     * @param string|ReflectionClass|ParsedReflectionClass $class Class to advise
     *
     * @return array|Aop\Advice[] List of advices for class
     */
    public function getAdvicesForClass($class)
    {
        if ($this->loadedResources != $this->container->getResources()) {
            $this->loadAdvisorsAndPointcuts();
        }

        $classAdvices = array();
        if (!$class instanceof ReflectionClass && !$class instanceof ParsedReflectionClass) {
            $class = new ReflectionClass($class);
        }

        $parentClass = $class->getParentClass();

        if ($parentClass && preg_match('/' . AspectContainer::AOP_PROXIED_SUFFIX . '$/', $parentClass->name)) {
            $originalClass = $parentClass;
        } else {
            $originalClass = $class;
        }

        foreach ($this->container->getByTag('advisor') as $advisor) {

            if ($advisor instanceof Aop\PointcutAdvisor) {

                $pointcut = $advisor->getPointcut();
                if ($pointcut->getClassFilter()->matches($class)) {
                    $classAdvices = array_merge_recursive(
                        $classAdvices,
                        $this->getAdvicesFromAdvisor($originalClass, $advisor, $pointcut)
                    );
                }
            }

            if ($advisor instanceof Aop\IntroductionAdvisor) {
                if ($advisor->getClassFilter()->matches($class)) {
                    $classAdvices = array_merge_recursive(
                        $classAdvices,
                        $this->getIntroductionFromAdvisor($originalClass, $advisor)
                    );
                }
            }
        }
        return $classAdvices;
    }

    /**
     * Returns list of advices from advisor and point filter
     *
     * @param ReflectionClass|ParsedReflectionClass $class Class to inject advices
     * @param Aop\PointcutAdvisor $advisor Advisor for class
     * @param Aop\PointFilter $filter Filter for points
     *
     * @return array
     */
    private function getAdvicesFromAdvisor($class, Aop\PointcutAdvisor $advisor, Aop\PointFilter $filter)
    {
        $classAdvices = array();

        // Check methods in class only for method filters
        if ($filter->getKind() & Aop\PointFilter::KIND_METHOD) {

            $mask = ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
            foreach ($class->getMethods($mask) as $method) {
                /** @var $method ReflectionMethod| */
                if ($method->getDeclaringClass()->name == $class->name && $filter->matches($method)) {
                    $prefix = $method->isStatic() ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;
                    $classAdvices[$prefix . ':'. $method->name][] = $advisor->getAdvice();
                }
            }
        }

        // Check properties in class only for property filters
        if ($filter->getKind() & Aop\PointFilter::KIND_PROPERTY) {
            $mask = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
            foreach ($class->getProperties($mask) as $property) {
                /** @var $property ReflectionProperty */
                if ($filter->matches($property)) {
                    $classAdvices[AspectContainer::PROPERTY_PREFIX.':'.$property->name][] = $advisor->getAdvice();
                }
            }
        }

        return $classAdvices;
    }

    /**
     * Returns list of introduction advices from advisor
     *
     * @param ReflectionClass|ParsedReflectionClass $class Class to inject advices
     * @param Aop\IntroductionAdvisor $advisor Advisor for class
     *
     * @return array
     */
    private function getIntroductionFromAdvisor($class, $advisor)
    {
        // Do not make introduction for traits
        if ($class->isTrait()) {
            return array();
        }

        /** @var $advice Aop\IntroductionInfo */
        $advice = $advisor->getAdvice();

        return array(
            AspectContainer::INTRODUCTION_TRAIT_PREFIX.':'.join(':', $advice->getInterfaces()) => $advice
        );
    }

    /**
     * Load pointcuts into container
     *
     * There is no need to always load pointcuts, so we delay loading
     */
    private function loadAdvisorsAndPointcuts()
    {
        // TODO: use a difference with $this->resources to load only missed aspects
        // TODO: maybe this is a task for the AspectLoader?
        foreach ($this->container->getByTag('aspect') as $aspect) {
            $this->loader->load($aspect);
        }
        $this->loadedResources = $this->container->getResources();
    }

    /**
     * Returns list of function advices for specific namespace
     *
     * @param ReflectionFileNamespace $namespace
     * @param Aop\PointcutAdvisor $advisor Advisor for class
     * @param Aop\PointFilter $pointcut Filter for points
     *
     * @return array
     */
    private function getFunctionAdvicesFromAdvisor($namespace, $advisor, $pointcut)
    {
        $functions = array();
        $advices   = array();

        if (!$functions) {
            $listOfGlobalFunctions = get_defined_functions();
            foreach ($listOfGlobalFunctions['internal'] as $functionName) {
                $functions[$functionName] = new NamespacedReflectionFunction($functionName, $namespace->getName());
            }
        }

        foreach ($functions as $functionName=>$function) {
            if ($pointcut->matches($function)) {
                $advices["func:$functionName"][] = $advisor->getAdvice();
            }
        }

        return $advices;
    }
}
