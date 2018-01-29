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

namespace Go\Proxy;

use Go\Aop\Advice;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\DynamicClosureMethodInvocation;
use Go\Aop\Framework\ReflectionConstructorInvocation;
use Go\Aop\Framework\StaticClosureMethodInvocation;
use Go\Aop\Framework\StaticInitializationJoinpoint;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Proxy;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use Go\Proxy\Part\InterceptedConstructorGenerator;
use Go\Proxy\Part\InterceptedMethodGenerator;
use Go\Proxy\Part\JoinPointPropertyGenerator;
use Go\Proxy\Part\PropertyInterceptionTrait;
use ReflectionClass;
use ReflectionMethod;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Reflection\DocBlockReflection;

/**
 * Class proxy builder that is used to generate a child class from the list of joinpoints
 */
class ClassProxyGenerator
{

    /**
     * Static mappings for class name for excluding if..else check
     *
     * @var null|array
     */
    protected static $invocationClassMap = [
        AspectContainer::METHOD_PREFIX        => DynamicClosureMethodInvocation::class,
        AspectContainer::STATIC_METHOD_PREFIX => StaticClosureMethodInvocation::class,
        AspectContainer::PROPERTY_PREFIX      => ClassFieldAccess::class,
        AspectContainer::STATIC_INIT_PREFIX   => StaticInitializationJoinpoint::class,
        AspectContainer::INIT_PREFIX          => ReflectionConstructorInvocation::class
    ];

    /**
     * List of advices that are used for generation of child
     *
     * @var array
     */
    protected $advices = [];

    /**
     * @var ClassGenerator
     */
    protected $generator;

    /**
     * Generates an child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass $originalClass        Original class reflection
     * @param string          $parentClassName      Parent class name to use
     * @param string[][]      $classAdvices         List of advices for class
     * @param bool            $useParameterWidening Enables usage of parameter widening feature
     */
    public function __construct(
        ReflectionClass $originalClass,
        string $parentClassName,
        array $classAdvices,
        bool $useParameterWidening
    ) {
        $this->advices         = $classAdvices;
        $dynamicMethodAdvices  = $classAdvices[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices   = $classAdvices[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
        $propertyAdvices       = $classAdvices[AspectContainer::PROPERTY_PREFIX] ?? [];
        $interceptedMethods    = array_keys($dynamicMethodAdvices + $staticMethodAdvices);
        $interceptedProperties = array_keys($propertyAdvices);
        $introducedInterfaces  = $classAdvices[AspectContainer::INTRODUCTION_INTERFACE_PREFIX] ?? [];
        $introducedTraits      = $classAdvices[AspectContainer::INTRODUCTION_TRAIT_PREFIX] ?? [];

        $generatedProperties = [new JoinPointPropertyGenerator($classAdvices)];
        $generatedMethods    = $this->interceptMethods($originalClass, $interceptedMethods);

        $introducedInterfaces[] = '\\' . Proxy::class;

        if (!empty($interceptedProperties)) {
            $generatedMethods['__construct'] = new InterceptedConstructorGenerator(
                $interceptedProperties,
                $originalClass->getConstructor(),
                $generatedMethods['__construct'] ?? null,
                $useParameterWidening
            );
            $introducedTraits[] = '\\' . PropertyInterceptionTrait::class;
        }

        $this->generator = new ClassGenerator(
            $originalClass->getShortName(),
            $originalClass->getNamespaceName(),
            $originalClass->isFinal() ? ClassGenerator::FLAG_FINAL : null,
            $parentClassName,
            $introducedInterfaces,
            $generatedProperties,
            $generatedMethods
        );
        if ($originalClass->getDocComment()) {
            $reflectionDocBlock = new DocBlockReflection($originalClass->getDocComment());
            $this->generator->setDocBlock(DocBlockGenerator::fromReflection($reflectionDocBlock));
        }

        $this->generator->addTraits($introducedTraits);
    }

    /**
     * Adds use alias for this class
     *
     * @param string      $use Name to use
     * @param string|null $useAlias Alias for this class name or null if not set
     */
    public function addUse(string $use, string $useAlias = null)
    {
        $this->generator->addUse($use, $useAlias);
    }

    /**
     * Inject advices into given class
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     *
     * @param string $className Aop child proxy class
     */
    public static function injectJoinPoints(string $className)
    {
        $reflectionClass    = new ReflectionClass($className);
        $joinPointsProperty = $reflectionClass->getProperty(JoinPointPropertyGenerator::NAME);

        $joinPointsProperty->setAccessible(true);
        $advices    = $joinPointsProperty->getValue();
        $joinPoints = static::wrapWithJoinPoints($advices, $reflectionClass->getParentClass()->name);
        $joinPointsProperty->setValue($joinPoints);

        $staticInit = AspectContainer::STATIC_INIT_PREFIX . ':root';
        if (isset($joinPoints[$staticInit])) {
            $joinPoints[$staticInit]->__invoke();
        }
    }

    /**
     * Generates the source code of child class
     */
    public function generate(): string
    {
        $classCode = $this->generator->generate();

        return $classCode
            // Inject advices on call
            . '\\' . __CLASS__ . '::injectJoinPoints(' . $this->generator->getName() . '::class);';
    }

    /**
     * Wrap advices with joinpoint object
     *
     * @param array|Advice[][][] $classAdvices Advices for specific class
     * @param string $className Name of the original class to use
     *
     * @throws \UnexpectedValueException If joinPoint type is unknown
     *
     * NB: Extension should be responsible for wrapping advice with join point.
     *
     * @return array|Joinpoint[] returns list of joinpoint ready to use
     */
    protected static function wrapWithJoinPoints(array $classAdvices, string $className): array
    {
        /** @var LazyAdvisorAccessor $accessor */
        static $accessor;

        if (!isset($accessor)) {
            $aspectKernel = AspectKernel::getInstance();
            $accessor     = $aspectKernel->getContainer()->get('aspect.advisor.accessor');
        }

        $joinPoints = [];

        foreach ($classAdvices as $joinPointType => $typedAdvices) {
            // if not isset then we don't want to create such invocation for class
            if (!isset(self::$invocationClassMap[$joinPointType])) {
                continue;
            }
            foreach ($typedAdvices as $joinPointName => $advices) {
                $filledAdvices = [];
                foreach ($advices as $advisorName) {
                    $filledAdvices[] = $accessor->$advisorName;
                }

                $joinpoint = new self::$invocationClassMap[$joinPointType]($className, $joinPointName, $filledAdvices);
                $joinPoints["$joinPointType:$joinPointName"] = $joinpoint;
            }
        }

        return $joinPoints;
    }

    /**
     * Returns list of intercepted methods
     *
     * @param ReflectionClass $originalClass Instance of original reflection
     * @param array           $methodNames List of methods to intercept
     *
     * @return InterceptedMethodGenerator[]
     */
    protected function interceptMethods(ReflectionClass $originalClass, array $methodNames): array
    {
        $interceptedMethods = [];
        foreach ($methodNames as $methodName) {
            $reflectionMethod = $originalClass->getMethod($methodName);
            $methodBody       = $this->getJoinpointInvocationBody($reflectionMethod);

            $interceptedMethods[$methodName] = new InterceptedMethodGenerator($reflectionMethod, $methodBody);
        }

        return $interceptedMethods;
    }

    /**
     * Creates definition for method body
     *
     * @param ReflectionMethod $method Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method): string
    {
        $isStatic = $method->isStatic();
        $scope    = $isStatic ? 'static::class' : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $argumentList = new FunctionCallArgumentListGenerator($method);
        $argumentCode = $argumentList->generate();
        $return = 'return ';
        if (PHP_VERSION_ID >= 70100 && $method->hasReturnType()) {
            $returnType = (string) $method->getReturnType();
            if ($returnType === 'void') {
                // void return types should not return anything
                $return = '';
            }
        }

        if (!empty($argumentCode)) {
            $scope = "$scope, $argumentCode";
        }

        $body = "{$return}self::\$__joinPoints['{$prefix}:{$method->name}']->__invoke($scope);";

        return $body;
    }
}
