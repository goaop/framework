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
use Go\ParserReflection\ReflectionAttribute;
use Go\Proxy\Part\InterceptedConstructorASTGenerator;
use Go\Proxy\Part\InterceptedConstructorGenerator;
use Go\Proxy\Part\InterceptedMethodASTGenerator;
use Go\Proxy\Part\JoinPointPropertyASTGenerator;
use Go\Proxy\Part\JoinPointPropertyGenerator;
use Go\Proxy\Part\MethodInvocationCallASTGenerator;
use Go\Proxy\Part\PropertyInterceptionTrait;
use PhpParser\Builder\Class_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use ReflectionMethod;
use UnexpectedValueException;

/**
 * Class proxy builder that is used to generate a child class from the list of joinpoints
 */
class ClassProxyASTGenerator
{
    /**
     * @var array&array<string,class-string> Static mappings for class name for excluding if..else check
     */
    protected static array $invocationClassMap = [
        AspectContainer::METHOD_PREFIX        => DynamicClosureMethodInvocation::class,
        AspectContainer::STATIC_METHOD_PREFIX => StaticClosureMethodInvocation::class,
        AspectContainer::PROPERTY_PREFIX      => ClassFieldAccess::class,
        AspectContainer::STATIC_INIT_PREFIX   => StaticInitializationJoinpoint::class,
        AspectContainer::INIT_PREFIX          => ReflectionConstructorInvocation::class
    ];

    private Class_ $classToBuild;

    private array  $useAliases;

    /**
     * Generates an child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass $reflectionClass        Original class reflection
     * @param string          $parentClassName      Parent class name to use
     * @param string[][]      $adviceNames     List of advices for class
     * @param bool            $useParameterWidening Enables usage of parameter widening feature
     */
    public function __construct(
        private readonly ReflectionClass $reflectionClass,
        private readonly string          $parentClassName,
        array                            $adviceNames = [],
        private readonly bool            $useParameterWidening = true
    ) {
        $dynamicMethodAdvices  = $adviceNames[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices   = $adviceNames[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
        $propertyAdvices       = $adviceNames[AspectContainer::PROPERTY_PREFIX] ?? [];
        $interceptedMethods    = array_keys($dynamicMethodAdvices + $staticMethodAdvices);
        $interceptedProperties = array_keys($propertyAdvices);
        $introducedInterfaces  = $adviceNames[AspectContainer::INTRODUCTION_INTERFACE_PREFIX]['root'] ?? [];
        $introducedTraits      = $adviceNames[AspectContainer::INTRODUCTION_TRAIT_PREFIX]['root'] ?? [];

        $generatedASTProperties = [(new JoinPointPropertyASTGenerator())->generate($adviceNames)];
        $generatedASTMethods    = $this->interceptMethods($reflectionClass, $interceptedMethods);

        $introducedInterfaces[] = '\\' . Proxy::class;

        if (!empty($interceptedProperties)) {
            $generatedASTMethods['__construct'] = new InterceptedConstructorASTGenerator(
                $interceptedProperties,
                $reflectionClass->getConstructor(),
                $generatedASTMethods['__construct'] ?? null,
                $useParameterWidening
            );
            $introducedTraits[] = '\\' . PropertyInterceptionTrait::class;
        }

        $builder      = new BuilderFactory();
        $classToBuild = $builder->class($reflectionClass->getShortName());
        $classToBuild->extend('\\' . $this->parentClassName);
        $classToBuild->implement(...$introducedInterfaces);

        if (count($introducedTraits) > 0) {
            $classToBuild->addStmt($builder->useTrait(...$introducedTraits));
        }

        if ($reflectionClass->isFinal()) {
            $classToBuild->makeFinal();
        }

        if ($reflectionClass->isReadOnly()) {
            $classToBuild->makeReadonly();
        }

        if (count($generatedASTMethods) > 0) {
            $classToBuild->addStmts($generatedASTMethods);
        }

        if (count($generatedASTProperties) > 0) {
            $classToBuild->addStmts($generatedASTProperties);
        }

        if ($reflectionClass->getDocComment()) {
            $classToBuild->setDocComment($reflectionClass->getDocComment());
        }

        foreach ($reflectionClass->getAttributes() as $attribute) {
            if ($attribute instanceof ReflectionAttribute) {
                // This will generate attribute in the exact way it was defined in the original class
                $classToBuild->addAttribute($attribute->getNode());
            } else {
                // Otherwise we try to do our best with attribute name and arguments pair
                $classToBuild->addAttribute(
                    $builder->attribute(
                        '\\' . $attribute->getName(),
                        $attribute->getArguments()
                    )
                );
            }
        }

        $this->classToBuild = $classToBuild;
    }

    /**
     * Adds use alias for this class
     *
     * @param string&class-string $use
     * @param string|null $useAlias Short alias name to use or null if there is no alias defined.
     */
    public function addUse(string $use, string|null $useAlias = null): void
    {
        $this->useAliases[$use] = $useAlias;
    }

    /**
     * Inject advices into given class
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     */
    public static function injectJoinPoints(string $targetClassName): void
    {
        $reflectionClass    = new ReflectionClass($targetClassName);
        $joinPointsProperty = $reflectionClass->getProperty(JoinPointPropertyGenerator::NAME);

        $advices    = $joinPointsProperty->getValue();
        $joinPoints = static::wrapWithJoinPoints($advices, $reflectionClass->name);
        $joinPointsProperty->setValue(null, $joinPoints);

        $staticInit = AspectContainer::STATIC_INIT_PREFIX . ':root';
        if (isset($joinPoints[$staticInit])) {
            ($joinPoints[$staticInit])();
        }
    }

    /**
     * Generates the source code of child class
     */
    public function generate(): string
    {
        $printer    = new Standard();
        $builder    = new BuilderFactory();

        $statements = [];
        if ($this->reflectionClass->inNamespace()) {
            $statements[] = $builder->namespace($this->reflectionClass->getNamespaceName())->getNode();
        }
        if (count($this->useAliases) > 0) {
            foreach ($this->useAliases as $use => $useAlias) {
                $namespaceParts = explode('\\', $use);
                $lastPart       = array_pop($namespaceParts);
                $useNodeBuilder = $builder->use($use);
                if ($lastPart !== $useAlias) {
                    $useNodeBuilder->as($useAlias);
                }
                $statements[] = $useNodeBuilder->getNode();
            }
        }
        $statements[] = $this->classToBuild->getNode();

        $classCode = $printer->prettyPrint($statements);

        return $classCode
            // Inject advices on call
            . '\\' . self::class . '::injectJoinPoints(' . $this->reflectionClass->getShortName() . '::class);';
    }

    /**
     * Wrap advices with joinpoint object
     *
     * @param array|Advice[][][] $classAdvices Advices for specific class
     *
     * @throws UnexpectedValueException If joinPoint type is unknown
     *
     * NB: Extension should be responsible for wrapping advice with join point.
     *
     * @return Joinpoint[] returns list of joinpoint ready to use
     */
    protected static function wrapWithJoinPoints(array $classAdvices, string $className): array
    {
        static $accessor = null;

        if (!isset($accessor)) {
            $aspectKernel = AspectKernel::getInstance();
            $accessor     = $aspectKernel->getContainer()->getService(LazyAdvisorAccessor::class);
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

                $joinpoint = new self::$invocationClassMap[$joinPointType]($filledAdvices, $className, $joinPointName);
                $joinPoints["$joinPointType:$joinPointName"] = $joinpoint;
            }
        }

        return $joinPoints;
    }

    /**
     * Returns list of intercepted method generators for class by method names
     *
     * @param string[] $methodNames List of methods to intercept
     *
     * @return array<string, ClassMethod>
     */
    protected function interceptMethods(ReflectionClass $originalClass, array $methodNames): array
    {
        $interceptedMethods = [];
        foreach ($methodNames as $methodName) {
            $reflectionMethod = $originalClass->getMethod($methodName);
            $methodGenerator  = new InterceptedMethodASTGenerator(
                $reflectionMethod,
                [(new MethodInvocationCallASTGenerator($reflectionMethod))->generate()],
                $this->useParameterWidening
            );

            $interceptedMethods[$methodName] = $methodGenerator->generate();
        }

        return $interceptedMethods;
    }

    /**
     * Creates string definition for method body by method reflection
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method): string
    {
        $methodCall = new MethodInvocationCallASTGenerator($method);
        $invocation = $methodCall->generate();
        $printer    = new Standard();

        return $printer->prettyPrint([$invocation]);
    }
}
