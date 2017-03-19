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

namespace Go\Aop\Support;

use Go\Aop\Advice;
use Go\Aop\IntroductionAdvisor;
use Go\Aop\IntroductionInfo;
use Go\Aop\PointFilter;
use ReflectionClass;

/**
 * Introduction advisor delegating to the given object.
 */
class DeclareParentsAdvisor implements IntroductionAdvisor
{

    /**
     * Introduction information
     *
     * @var IntroductionInfo
     */
    private $advice = null;

    /**
     * Type pattern the introduction is restricted to
     *
     * @var PointFilter
     */
    private $classFilter;

    /**
     * Creates an advisor for declaring mixins via traits and interfaces.
     *
     * @param PointFilter $classFilter Class filter
     * @param IntroductionInfo $info Introduction information
     */
    public function __construct(PointFilter $classFilter, IntroductionInfo $info)
    {
        $this->classFilter = $classFilter;
        $this->advice      = $info;
    }

    /**
     * Can the advised interfaces be implemented by the introduction advice?
     *
     * Invoked before adding an IntroductionAdvisor.
     *
     * @return void
     * @throws \InvalidArgumentException if the advised interfaces can't be implemented by the introduction advice
     */
    public function validateInterfaces()
    {
        $refInterface      = new ReflectionClass(reset($this->advice->getInterfaces()));
        $refImplementation = new ReflectionClass(reset($this->advice->getTraits()));
        if (!$refInterface->isInterface()) {
            throw new \InvalidArgumentException("Only interface can be introduced");
        }
        if (!$refImplementation->isTrait()) {
            throw new \InvalidArgumentException("Only trait can be used as implementation");
        }

        foreach ($refInterface->getMethods() as $interfaceMethod) {
            if (!$refImplementation->hasMethod($interfaceMethod->name)) {
                throw new \DomainException("Implementation requires method {$interfaceMethod->name}");
            }
        }
    }

    /**
     * Returns an advice to apply
     *
     * @return Advice|IntroductionInfo
     */
    public function getAdvice() : Advice
    {
        return $this->advice;
    }

    /**
     * Return the filter determining which target classes this introduction should apply to.
     *
     * This represents the class part of a pointcut. Note that method matching doesn't make sense to introductions.
     *
     * @return PointFilter The class filter
     */
    public function getClassFilter()
    {
        return $this->classFilter;
    }

    /**
     * Set the class filter for advisor
     *
     * @param PointFilter $classFilter Filter for classes
     */
    public function setClassFilter(PointFilter $classFilter)
    {
        $this->classFilter = $classFilter;
    }

    /**
     * Return string representation of object
     *
     * @return string
     */
    public function __toString()
    {
        $adviceClass      = get_class($this->advice);
        $interfaceClasses = join(',', $this->advice->getInterfaces());

        return get_called_class() . ": advice [{$adviceClass}]; interfaces [{$interfaceClasses}] ";
    }
}
