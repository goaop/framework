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
use Go\Aop\Framework;
use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Aop\Support;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Lang\Annotation;

/**
 * General aspect loader add common support for general advices, declared as annotations
 */
class GeneralAspectLoaderExtension implements AspectLoaderExtension
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
        return $metaInformation instanceof Annotation\Interceptor
                || $metaInformation instanceof Annotation\Pointcut;
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
        /** @var $pointcut Pointcut|PointFilter */
        $pointcut       = $this->parsePointcut($container, $reflection, $metaInformation);
        $methodId       = sprintf("%s->%s()", $reflection->class, $reflection->name);
        $adviceCallback = Framework\BaseAdvice::fromAspectReflection($aspect, $reflection);

        $isPointFilter  = $pointcut instanceof PointFilter;
        switch (true) {
            // Register a pointcut by its name
            case ($metaInformation instanceof Annotation\Pointcut):
                $container->registerPointcut($pointcut, $methodId);
                break;

            case ($isPointFilter && ($pointcut->getKind() & PointFilter::KIND_METHOD)):
                $advice = $this->getMethodInterceptor($metaInformation, $adviceCallback);
                $container->registerAdvisor(new DefaultPointcutAdvisor($pointcut, $advice), $methodId);
                break;

            case ($isPointFilter && ($pointcut->getKind() & PointFilter::KIND_PROPERTY)):
                $advice = $this->getPropertyInterceptor($metaInformation, $adviceCallback);
                $container->registerAdvisor(new DefaultPointcutAdvisor($pointcut, $advice), $methodId);
                break;

            default:
                throw new \UnexpectedValueException("Unsupported pointcut class: " . get_class($pointcut));
        }
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
     * @param AspectContainer $container Container
     * @param Annotation\BaseAnnotation|Annotation\BaseInterceptor $metaInformation
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     *
     * @return \Go\Aop\Pointcut
     */
    private function parsePointcut(AspectContainer $container, $reflection, $metaInformation)
    {
        if (isset($metaInformation->pointcut)) {
            return $this->loadPointcutFromContainer($container, $reflection, $metaInformation);
        }

        /** @var $lexer \Dissect\Lexer\Lexer */
        $lexer  = $container->get('aspect.pointcut.lexer');
        $stream = $lexer->lex($metaInformation->value);

        /** @var $parser \Dissect\Parser\Parser */
        $parser = $container->get('aspect.pointcut.parser');
        return $parser->parse($stream);
    }

    /**
     * Loads a pointcut from container by name
     *
     * @param Annotation\BaseAnnotation|Annotation\BaseInterceptor $metaInformation
     * @param $container
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     *
     * @return mixed Pointcut
     * @throws \UnexpectedValueException If both value and pointcut name is set
     */
    private function loadPointcutFromContainer(AspectContainer $container, $reflection, $metaInformation)
    {
        if ($metaInformation->value) {
            throw new \UnexpectedValueException("Can not use both `value` and `pointcut` properties");
        }

        try {
            // FQDN
            return $container->getPointcut($metaInformation->pointcut);
        } catch (\OutOfBoundsException $e) {
            // By method name for class
            $pointcutId = sprintf("%s->%s", $reflection->class, $metaInformation->pointcut);
            return $container->getPointcut($pointcutId);
        }
    }
}