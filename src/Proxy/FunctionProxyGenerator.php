<?php

declare(strict_types = 1);
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
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Proxy\Part\FunctionInvocationCallASTGenerator;
use Go\Proxy\Part\InterceptedFunctionGenerator;
use Laminas\Code\Generator\FileGenerator;
use PhpParser\PrettyPrinter\Standard;
use ReflectionException;
use ReflectionFunction;

/**
 * Function proxy builder that is used to generate a proxy-function from the list of joinpoints
 */
class FunctionProxyGenerator
{
    /**
     * List of advices that are used for generation of child
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
     * @param string[][]              $adviceNames          List of function advices
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
            $functionReflection  = new ReflectionFunction($functionName);
            $functionBody        = $this->getJoinpointInvocationBody($functionReflection);
            $interceptedFunction = new InterceptedFunctionGenerator($functionReflection, $functionBody, $useParameterWidening);
            $functionsContent[]  = $interceptedFunction->generate();
        }

        $this->fileGenerator->setBody(implode("\n", $functionsContent));
    }

    /**
     * Returns a joinpoint for specific function in the namespace
     *
     * @param array $adviceNames List of advices
     */
    public static function getJoinPoint(string $functionName, array $adviceNames): ReflectionFunctionInvocation
    {
        static $accessor;

        if ($accessor === null) {
            $accessor = AspectKernel::getInstance()->getContainer()->getService(LazyAdvisorAccessor::class);
        }

        $filledAdvices = [];
        foreach ($adviceNames as $advisorName) {
            $filledAdvices[] = $accessor->$advisorName;
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
        $functionCall = new FunctionInvocationCallASTGenerator($function);
        $statements   = $functionCall->generate($this->adviceNames[AspectContainer::FUNCTION_PREFIX][$function->name]);
        $printer      = new Standard();

        return $printer->prettyPrint($statements);
    }
}
