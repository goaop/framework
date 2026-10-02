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

use Go\Aop\Framework\GeneratedInterceptor;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Core\AspectContainer;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Proxy\Generator\FileGenerator;
use Go\Proxy\Generator\FunctionGenerator;
use Go\Proxy\Generator\InterceptorListGenerator;
use Go\Proxy\Generator\ProxyImports;
use Go\Proxy\Generator\TypeGenerator;
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
     * List of advices that are used for generation of child
     *
     * @var array<string, array<string, array<GeneratedInterceptor|string>>>
     */
    protected array $adviceNames = [];

    /**
     * Instance of file generator
     */
    protected FileGenerator $fileGenerator;

    /**
     * Imports of the generated file: generated code references classes through their aliases
     */
    protected ProxyImports $imports;

    /**
     * Constructs functions stub class from namespace Reflection
     *
     * @param ReflectionFileNamespace $namespace   Reflection of namespace
     * @param array<string, array<string, array<GeneratedInterceptor|string>>> $adviceNames List of function advices
     *
     * @throws ReflectionException If there is an advice for unknown function
     */
    public function __construct(
        ReflectionFileNamespace $namespace,
        array $adviceNames = [],
    ) {
        $this->adviceNames   = $adviceNames;
        $this->fileGenerator = new FileGenerator();
        $this->fileGenerator->namespace = $namespace->getName();
        $this->imports = new ProxyImports($namespace->getName());
        $this->imports->import(InterceptorInjector::class);
        $this->imports->import(Interceptor::class);
        $this->imports->import(The::class);
        $this->imports->import(FunctionInvocation::class);
        foreach ($this->collectAspectClasses($adviceNames) as $aspectClass) {
            $this->imports->import($aspectClass);
        }
        foreach ($this->imports->getUses() as $className => $alias) {
            $this->fileGenerator->addUse($className, $alias);
        }

        $functionsContent = [];
        $functionAdvices  = $adviceNames[AspectContainer::FUNCTION_PREFIX] ?? [];
        foreach (array_keys($functionAdvices) as $functionName) {
            $functionReflection = new ReflectionFunction($functionName);
            $functionBody       = $this->getJoinpointInvocationBody($functionReflection);
            $funcGenerator      = FunctionGenerator::fromReflection($functionReflection);
            $funcGenerator->body = $functionBody;
            $functionsContent[] = $funcGenerator->generate();
        }

        $this->fileGenerator->body = implode("\n", $functionsContent);
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
     *
     * The callable expression uses a fully-qualified global function reference (leading backslash)
     * to ensure that proceed() calls the original built-in function, not the proxy defined in
     * the current namespace.
     */
    protected function getJoinpointInvocationBody(ReflectionFunction $function): string
    {
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
        $advicesCode = (new InterceptorListGenerator(array_values($functionAdvices), $this->imports))->generate();
        $returnTypeString = $function->hasReturnType() ? '<' . TypeGenerator::renderTypeForPhpDoc($function->getReturnType()) . '>' : '';

        // Use a fully-qualified (global) callable so proceed() calls the original built-in
        // function rather than the proxy defined in this namespace.
        $callableExpression = '\\' . $function->getName() . '(...)';
        $injector           = $this->imports->import(InterceptorInjector::class);
        $joinPoint          = $this->imports->import(FunctionInvocation::class);

        return <<<BODY
        /** @var {$joinPoint}{$returnTypeString} \$__joinPoint */
        static \$__joinPoint = {$injector}::forFunction(
            '{$function->name}',
            {$advicesCode},
            {$callableExpression},
        );
        {$return}\$__joinPoint->__invoke($argumentCode);
        BODY;
    }

    /**
     * @param array<string, array<string, array<GeneratedInterceptor|string>>> $adviceNames
     * @return list<string>
     */
    private function collectAspectClasses(array $adviceNames): array
    {
        $interceptors = [];
        foreach ($adviceNames as $typedAdvices) {
            foreach ($typedAdvices as $concreteAdvices) {
                foreach ($concreteAdvices as $advice) {
                    if ($advice instanceof GeneratedInterceptor) {
                        $interceptors[] = $advice;
                    }
                }
            }
        }

        return InterceptorListGenerator::aspectClasses($interceptors);
    }
}
