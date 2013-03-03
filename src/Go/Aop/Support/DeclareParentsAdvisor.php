<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use InvalidArgumentException;
use ReflectionClass;

use Go\Aop\Advice;
use Go\Aop\PointFilter;
use Go\Aop\IntroductionInfo;
use Go\Aop\IntroductionAdvisor;

/**
 * Introduction advisor delegating to the given object.
 */
class DeclareParentsAdvisor implements IntroductionAdvisor
{

    /**
     * @var null|IntroductionInfo
     */
    private $advice = null;

    /**
     * Type pattern the introduction is restricted to
     *
     * @var PointFilter
     */
    private $classFilter;

    /**
     * Create a DefaultIntroductionAdvisor for the given advice.
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

        foreach($refInterface->getMethods() as $interfaceMethod) {
            if (!$refImplementation->hasMethod($interfaceMethod->name)) {
                throw new \DomainException("Implementation requires method {$interfaceMethod->name}");
            }
        }
    }

    /**
     * Returns an advice to apply
     *
     * @return Advice|IntroductionInfo|null
     */
    public function getAdvice()
    {
        return $this->advice;
    }

    /**
     * Return whether this advice is associated with a particular instance or shared with all instances
     * of the advised class
     *
     * @return bool Whether this advice is associated with a particular target instance
     */
    public function isPerInstance()
    {
        return false;
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
