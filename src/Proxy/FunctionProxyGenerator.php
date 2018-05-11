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
use Go\Aop\Intercept\FunctionInvocation;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use Go\Proxy\Part\InterceptedFunctionGenerator;
use ReflectionFunction;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\ValueGenerator;

/**
 * Function proxy builder that is used to generate a proxy-function from the list of joinpoints
 */
class FunctionProxyGenerator
{
    /**
     * List of advices that are used for generation of child
     */
    protected $advices = [];

    /**
     * Instance of file generator
     */
    protected $fileGenerator;

    /**
     * Constructs functions stub class from namespace Reflection
     *
     * @param ReflectionFileNamespace $namespace            Reflection of namespace
     * @param string[][]              $advices              List of function advices
     * @param bool                    $useParameterWidening Enables usage of parameter widening feature
     *
     * @throws \ReflectionException If there is an advice for unknown function
     */
    public function __construct(
        ReflectionFileNamespace $namespace,
        array $advices = [],
        bool $useParameterWidening = false
    ) {
        $this->advices       = $advices;
        $this->fileGenerator = new FileGenerator();
        $this->fileGenerator->setNamespace($namespace->getName());

        $functionsContent = [];
        $functionAdvices  = $advices[AspectContainer::FUNCTION_PREFIX] ?? [];
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
     * @param array $advices List of advices
     */
    public static function getJoinPoint(string $functionName, array $advices): FunctionInvocation
    {
        static $accessor;

        if ($accessor === null) {
            $accessor = AspectKernel::getInstance()->getContainer()->get('aspect.advisor.accessor');
        }

        $filledAdvices = [];
        foreach ($advices as $advisorName) {
            $filledAdvices[] = $accessor->$advisorName;
        }

        return new ReflectionFunctionInvocation($functionName, $filledAdvices);
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
        $class = '\\' . __CLASS__;

        $argumentList = new FunctionCallArgumentListGenerator($function);
        $argumentCode = $argumentList->generate();

        $return = 'return ';
        if ($function->hasReturnType()) {
            $returnType = (string) $function->getReturnType();
            if ($returnType === 'void') {
                // void return types should not return anything
                $return = '';
            }
        }

        $functionAdvices = $this->advices[AspectContainer::FUNCTION_PREFIX][$function->name];
        $advicesArray    = new ValueGenerator($functionAdvices, ValueGenerator::TYPE_ARRAY_SHORT);
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
