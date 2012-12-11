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
use Go\Aop\Advisor;
use Go\Aop\Framework;
use Go\Aop\MethodMatcher;
use Go\Aop\PropertyMatcher;
use Go\Aop\Support;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Lang\Annotation;

/**
 * General aspect loader add common support for general advices, declared as annotations
 */
class GeneralAspectLoaderExtension implements AspectLoaderExtension
{
    /**
     * Mappings of string values to method modifiers
     *
     * @todo: Move to the pointcut parser
     * @var array
     */
    protected static $methodModifiers = array(
        'public'    => ReflectionMethod::IS_PUBLIC,
        'protected' => ReflectionMethod::IS_PROTECTED,
        '::'        => ReflectionMethod::IS_STATIC,
        '*'         => 768 /* PUBLIC | PROTECTED */,
        '->'        => 0,
    );

    /**
     * Mappings of string values to property modifiers
     *
     * @todo: Move to the pointcut parser
     * @var array
     */
    protected static $propertyModifiers = array(
        'public'    => ReflectionProperty::IS_PUBLIC,
        'protected' => ReflectionProperty::IS_PROTECTED,
        '*'         => 768 /* PUBLIC | PROTECTED */,
    );

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
        return $metaInformation instanceof Annotation\Interceptor;
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
        $adviceCallback = Framework\BaseAdvice::fromAspectReflection($aspect, $reflection);

        // TODO: use general pointcut parser here instead of hardcoded regular expressions
        $pointcut = $this->parsePointcut($metaInformation);

        switch (true) {
            case ($pointcut instanceof MethodMatcher):
                $advice = $this->getMethodInterceptor($metaInformation, $adviceCallback);
                break;

            case ($pointcut instanceof PropertyMatcher):
                $advice = $this->getPropertyInterceptor($metaInformation, $adviceCallback);
                break;

            default:
                throw new \UnexpectedValueException("Unsupported pointcut class: " . get_class($pointcut));
        }

        $container->registerAdvisor(new DefaultPointcutAdvisor($pointcut, $advice));
    }

    /**
     * @param $metaInformation
     * @param $adviceCallback
     * @return \Go\Aop\Intercept\MethodInterceptor
     * @throws \UnexpectedValueException
     */
    protected function getMethodInterceptor($metaInformation, $adviceCallback)
    {
        switch (true) {
            case ($metaInformation instanceof Annotation\Before):
                return new Framework\MethodBeforeInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\After):
                return new Framework\MethodAfterInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\Around):
                return new Framework\MethodAroundInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\AfterThrowing):
                return new Framework\MethodAfterThrowingInterceptor($adviceCallback, $metaInformation->order);

            default:
                throw new \UnexpectedValueException("Unsupported method meta class: " . get_class($metaInformation));
        }
    }

    /**
     * @param $metaInformation
     * @param $adviceCallback
     * @return \Go\Aop\Intercept\FieldAccess
     * @throws \UnexpectedValueException
     */
    protected function getPropertyInterceptor($metaInformation, $adviceCallback)
    {
        switch (true) {
            case ($metaInformation instanceof Annotation\Before):
                return new Framework\FieldBeforeInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\After):
                return new Framework\FieldAfterInterceptor($adviceCallback, $metaInformation->order);

            case ($metaInformation instanceof Annotation\Around):
                return new Framework\FieldAroundInterceptor($adviceCallback, $metaInformation->order);

            default:
                throw new \UnexpectedValueException("Unsupported method meta class: " . get_class($metaInformation));
        }
    }

    /**
     * Temporary method for parsing pointcuts
     *
     * @todo Replace this method with pointcut parser
     * @param Annotation\BaseAnnotation $metaInformation
     *
     * @throws \UnexpectedValueException If pointcut can not be parsed
     * @return \Go\Aop\Pointcut
     */
    private function parsePointcut($metaInformation)
    {
        // execution(public Example\Aspect\*->method*())
        // execution(protected Test\Class*::someStatic*Method())
        static $executionReg = '/
            ^execution\(
                (?P<modifier>public|protected|\*)\s+
                (?P<class>[\w\\\*]+)
                (?P<type>->|::)
                (?P<method>[\w\*]+)
                \(\*?\)
            \)$/x';

        if (preg_match($executionReg, $metaInformation->value, $matches)) {
            $modifier = self::$methodModifiers[$matches['modifier']];
            $modifier |= self::$methodModifiers[$matches['type']];
            $pointcut = new Support\SignatureMethodPointcut($matches['method'], $modifier);
            if ($matches['class'] !== '*') {
                $classFilter = new Support\SimpleClassFilter($matches['class']);
                $pointcut->setClassFilter($classFilter);
            }
            return $pointcut;
        }


        // within(Go\Aspects\Blog\Package\*) : This will match all the methods in all classes of Go\Aspects\Blog\Package.
        // within(Go\Aspects\Blog\Package\**) : This will match all the methods in all classes of Go\Aspects\Blog\Package and its sub packages. The only difference is the extra dot(.) after package.
        // within(Go\Aspects\Blog\Package\DemoClass) : This will match all the methods in the DemoClass.
        // within(DemoInterface+) : This will match all the methods which are in classes which implement DemoInterface.
        static $withinReg = '/
            ^within\(
                (?P<class>[\w\\\*]+)
                (?P<children>\+?)
            \)$/x';

        if (preg_match($withinReg, $metaInformation->value, $matches)) {
            $pointcut = new Support\WithinMethodPointcut($matches['class'], (bool) $matches['children']);
            return $pointcut;
        }

        // access(public Example\Aspect\*->property*)
        // access(protected Test\Class*->someProtected*Property)
        static $propertyReg = '/
            ^access\(
                (?P<modifier>public|protected|\*)\s+
                (?P<class>[\w\\\*]+)
                ->
                (?P<property>[\w\*]+)
            \)$/x';

        if (preg_match($propertyReg, $metaInformation->value, $matches)) {
            $modifier = self::$propertyModifiers[$matches['modifier']];
            $pointcut = new Support\SignaturePropertyPointcut($matches['property'], $modifier);
            if ($matches['class'] !== '*') {
                $classFilter = new Support\SimpleClassFilter($matches['class']);
                $pointcut->setClassFilter($classFilter);
            }
            return $pointcut;
        }

        throw new \UnexpectedValueException("Unsupported pointcut: {$metaInformation->value}");
    }
}