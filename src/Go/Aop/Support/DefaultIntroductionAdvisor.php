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
use Go\Aop\ClassFilter;
use Go\Aop\IntroductionInfo;
use Go\Aop\IntroductionAdvisor;

use TokenReflection\ReflectionClass as ParsedReflectionClass;

/**
 * Convenient Pointcut-driven Advisor implementation.
 *
 * This is the most commonly used Advisor implementation. It can be used with any pointcut and advice type,
 * except for introductions. There is normally no need to subclass this class, or to implement custom Advisors.
 */
class DefaultIntroductionAdvisor implements IntroductionAdvisor, ClassFilter
{

    /**
     * @var null|Advice
     */
    private $advice = null;

    /**
     * List of introduced interfaces
     *
     * @var array|string[]
     */
    private $interfaces = array();

    /**
     * Create a DefaultIntroductionAdvisor for the given advice.
     *
     * @param Advice $advice The Advice to apply
     * @param IntroductionInfo|null $introductionInfo IntroductionInfo that describes the interface to introduce
     */
    public function __construct(Advice $advice, IntroductionInfo $introductionInfo = null)
    {
        $this->advice = $advice;
        if ($introductionInfo !== null) {
            $interfaces = $introductionInfo->getInterfaces();
            foreach ($interfaces as $interface) {
                $this->addInterface($interface);
            }
        }
    }

    /**
     * Add the specified interface to the list of interfaces to introduce.
     *
     * @param string|ReflectionClass|ParsedReflectionClass $interface The interface to introduce
     *
     * @throws \InvalidArgumentException If interface is invalid
     */
    public function addInterface($interface)
    {
        assert('!empty($interface); /* Interface must not be empty */');
        if ($interface instanceof ReflectionClass || $interface instanceof ParsedReflectionClass) {
            if ($interface->isInterface()) {
                $errorMessage = "Specified class [" . $interface->getName() . "] must be an interface";
                throw new InvalidArgumentException($errorMessage);
            }
        }
        $interfaceName = is_object($interface) ? $interface->getName() : $interface;
        $this->interfaces[] = $interfaceName;
    }

    /**
     * Return the additional interfaces introduced by this Advisor or Advice.
     *
     * @return array|string[] the introduced interfaces
     */
    public function getInterfaces()
    {
        return $this->interfaces;
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
        // TODO: Advice contains link to the trait, so need to check that all methods from interfaces are present in the trait
    }

    /**
     * Returns an advice to apply
     *
     * @return Advice|null
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
        return true;
    }

    /**
     * Return the filter determining which target classes this introduction should apply to.
     *
     * This represents the class part of a pointcut. Note that method matching doesn't make sense to introductions.
     *
     * @return ClassFilter The class filter
     */
    public function getClassFilter()
    {
        return $this;
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $point Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches($point)
    {
        return true;
    }

    /**
     * Return string representation of object
     *
     * @return string
     */
    public function __toString()
    {
        $adviceClass      = get_class($this->getAdvice());
        $interfaceClasses = join(', ', $this->interfaces);
        return get_called_class() . ": advice [{$adviceClass}]; interfaces [{$interfaceClasses}] ";
    }
}
