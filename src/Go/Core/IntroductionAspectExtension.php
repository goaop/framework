<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use ReflectionMethod;
use ReflectionProperty;

use Go\Aop\Aspect;
use Go\Aop\PointFilter;
use Go\Aop\Framework;
use Go\Aop\Support;
use Go\Lang\Annotation;

/**
 * Introduction aspect extension
 */
class IntroductionAspectExtension implements AspectLoaderExtension
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
        return self::TARGET_PROPERTY;
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
        return $metaInformation instanceof Annotation\DeclareParents && IS_MODERN_PHP;
    }

    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param AspectContainer $container Instance of container
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param Annotation\DeclareParents|null $metaInformation Additional meta-information
     */
    public function load(AspectContainer $container, Aspect $aspect, $reflection, $metaInformation = null)
    {
        // TODO: use general class parser here instead of hardcoded regular expressions
        $classFilter = $this->parseClassFilter($metaInformation);
        $interface   = $metaInformation->interface;
        $implement   = $metaInformation->defaultImpl;
        $advice      = new Framework\TraitIntroductionInfo($interface, $implement);
        $advisor     = new Support\DeclareParentsAdvisor($classFilter, $advice);
        $propertyId  = sprintf("%s->%s", $reflection->class, $reflection->name);
        $container->registerAdvisor($advisor, $propertyId);
    }

    /**
     * Temporary method for parsing class filters
     *
     * @todo Replace this method with pointcut parser
     *
     * @param Annotation\BaseAnnotation $metaInformation
     *
     * @throws \UnexpectedValueException If class filter can not be parsed
     * @throws \InvalidArgumentException
     * @return PointFilter
     */
    private function parseClassFilter($metaInformation)
    {
        // Go\Aspects\Blog\Package\** : This will match all classes of Go\Aspects\Blog\Package and its sub packages.
        // Go\Aspects\Blog\Package\DemoClass : This will match DemoClass.
        // DemoInterface+ : This will match all classes which implement DemoInterface.
        static $classReg = '/
            ^
                (?P<class>[\w\\\*]+)
                (?P<children>\+?)
            $/x';

        if (preg_match($classReg, $metaInformation->value, $matches)) {
            $className = $matches['class'];
            if (!$matches['children']) {
                $classFilter = new Support\SimpleClassFilter($className);
            } elseif (strpos($className, '*') === false) {
                $classFilter = new Support\InheritanceClassFilter($className);
            } else {
                throw new \InvalidArgumentException("Can not use children selector with class mask");
            }

            return $classFilter;
        }

        throw new \UnexpectedValueException("Unsupported class filter: {$metaInformation->value}");
    }
}