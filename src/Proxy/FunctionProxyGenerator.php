<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy;

use Go\Aop\Framework\ReflectionFunctionInvocation;
use Go\Aop\Intercept\Interceptor;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Proxy\Generator\FileGenerator;
use Go\Proxy\Generator\FunctionGenerator;
use Go\Proxy\Generator\ValueGenerator;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * Function proxy builder that is used to generate a proxy-function from the list of joinpoints
 */
class FunctionProxyGenerator
{
    /**
     * Cached accessor for lazy advisor resolution
     */
    private static ?LazyAdvisorAccessor $accessor = null;

    /**
     * List of advices that are used for generation of child
     *
     * @var string[][][]
     */
    protected array $adviceNames = [];

    /**
     * Instance of file generator
     */
    protected FileGenerator $fileGenerator;

    /**
     * Constructs functions stub class from namespace Reflection
     *
     * @param ReflectionFileNamespace $namespace            Reflection of namespace
     * @param string[][][]            $adviceNames          List of function advices
     * @param bool                    $useParameterWidening Enables usage of parameter widening feature
     *
     * @throws ReflectionException If there is an advice for unknown function
     */
    public function __construct(
        ReflectionFileNamespace $namespace,
        array $adviceNames = [],
        bool $useParameterWidening = false
    ) {
        $this->adviceNames   = $adviceNames;
        $this->fileGenerator = new FileGenerator();
        $this->fileGenerator->setNamespace($namespace->getName());

        $functionsContent = [];
        $functionAdvices  = $adviceNames[AspectContainer::FUNCTION_PREFIX] ?? [];
        foreach (array_keys($functionAdvices) as $functionName) {
            $functionReflection = new ReflectionFunction($functionName);
            $functionBody       = $this->getJoinpointInvocationBody($functionReflection);
            $funcGenerator      = FunctionGenerator::fromReflection($functionReflection, $useParameterWidening);
            $funcGenerator->setBody($functionBody);
            $functionsContent[] = $funcGenerator->generate();
        }

        $this->fileGenerator->setBody(implode("\n", $functionsContent));
    }

    /**
     * Returns a joinpoint for specific function in the namespace
     *
     * @param string[] $adviceNames List of advices
     */
    public static function getJoinPoint(string $functionName, array $adviceNames): ReflectionFunctionInvocation
    {
        if (self::$accessor === null) {
            self::$accessor = AspectKernel::getInstance()->getContainer()->getService(LazyAdvisorAccessor::class);
        }

        $filledAdvices = [];
        foreach ($adviceNames as $advisorName) {
            $advice = self::$accessor->$advisorName;
            if (!$advice instanceof Interceptor) {
                throw new \RuntimeException("Advice '$advisorName' must implement Interceptor, got " . get_debug_type($advice));
            }
            $filledAdvices[] = $advice;
        }

        return new ReflectionFunctionInvocation($filledAdvices, $functionName);
    }

    /**
     * Generates the source code of function proxies in given namespace
     */
    public function generate(): string
    {
        return $this->fileGenerator->generate();
    }

    /**
     * Creates string definition for function method body by function reflection
     */
    protected function getJoinpointInvocationBody(ReflectionFunction $function): string
    {
        $class = '\\' . self::class;

        $argumentList = new FunctionCallArgumentListGenerator($function);
        $argumentCode = $argumentList->generate();

        $return = 'return ';
        if ($function->hasReturnType()) {
            $returnType = $function->getReturnType();
            if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['void', 'never'], true)) {
                // void/never return types should not return anything
                $return = '';
            }
        }

        $functionAdvices = $this->adviceNames[AspectContainer::FUNCTION_PREFIX][$function->name];
        $advicesArray    = new ValueGenerator($functionAdvices);
        $advicesArray->setArrayDepth(1);
        $advicesCode = $advicesArray->generate();

        return <<<BODY
static \$__joinPoint;
if (\$__joinPoint === null) {
    \$__joinPoint = {$class}::getJoinPoint('{$function->name}', {$advicesCode});
}
{$return}\$__joinPoint->__invoke($argumentCode);
BODY;
    }
}
