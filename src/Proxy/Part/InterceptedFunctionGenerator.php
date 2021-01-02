<?php
declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use ReflectionFunction;
use Laminas\Code\Generator\AbstractGenerator;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\TypeGenerator;
use Laminas\Code\Reflection\DocBlockReflection;
use ReflectionNamedType;

/**
 * Prepares the definition of intercepted function
 */
final class InterceptedFunctionGenerator extends AbstractGenerator
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var DocBlockGenerator|null
     */
    protected $docBlock;

    /**
     * @var ParameterGenerator[]
     */
    protected $parameters = [];

    /**
     * @var string
     */
    protected $body;

    /**
     * @var null|TypeGenerator
     */
    private $returnType;

    /**
     * @var bool
     */
    private $returnsReference;

    /**
     * InterceptedMethod constructor.
     *
     * @param ReflectionFunction $reflectionFunction Instance of original method
     * @param string             $body               Method body
     * @param bool               $useTypeWidening    Should generator use parameter widening for PHP>=7.2
     */
    public function __construct(ReflectionFunction $reflectionFunction, string $body, bool $useTypeWidening = false)
    {
        parent::__construct();

        if ($reflectionFunction->hasReturnType()) {
            $reflectionReturnType = $reflectionFunction->getReturnType();
            if ($reflectionReturnType instanceof ReflectionNamedType) {
                $returnTypeName = $reflectionReturnType->getName();
            } else {
                $returnTypeName = (string) $reflectionReturnType;
            }
            $this->returnType = TypeGenerator::fromTypeString($returnTypeName);
        }

        if ($reflectionFunction->getDocComment()) {
            $reflectionDocBlock = new DocBlockReflection($reflectionFunction->getDocComment());
            $this->docBlock     = DocBlockGenerator::fromReflection($reflectionDocBlock);
        }

        $this->returnsReference = $reflectionFunction->returnsReference();
        $this->name             = $reflectionFunction->getShortName();

        $parameterList    = new FunctionParameterList($reflectionFunction, $useTypeWidening);
        $this->parameters = $parameterList->getGeneratedParameters();
        $this->body       = $body;
    }

    /**
     * @return string
     */
    public function generate()
    {
        $output = '';
        $indent = $this->getIndentation();

        if ($this->docBlock !== null) {
            $this->docBlock->setIndentation($indent);
            $output .= $this->docBlock->generate();
        }

        $output .= 'function '
            . ($this->returnsReference ? '& ' : '')
            . $this->name . '(';

        if (!empty($this->parameters)) {
            $parameterOutput = [];
            foreach ($this->parameters as $parameter) {
                $parameterOutput[] = $parameter->generate();
            }

            $output .= implode(', ', $parameterOutput);
        }

        $output .= ')';

        if ($this->returnType) {
            $output .= ' : ' . $this->returnType->generate();
        }

        $output .= self::LINE_FEED . '{' . self::LINE_FEED;

        if ($this->body) {
            $output .= preg_replace('#^((?![a-zA-Z0-9_-]+;).+?)$#m', $indent . '$1', trim($this->body))
                . self::LINE_FEED;
        }

        $output .= '}' . self::LINE_FEED;

        return $output;
    }
}
