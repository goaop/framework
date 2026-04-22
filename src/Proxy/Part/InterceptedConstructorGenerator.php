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

use Go\Proxy\Generator\MethodGenerator;
use LogicException;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;

use function count;

/**
 * Prepares the definition of intercepted constructor
 */
final class InterceptedConstructorGenerator
{
    private MethodGenerator $constructorGenerator;

    /**
     * InterceptedConstructor
     *
     * @param ReflectionMethod|null           $constructor           Instance of original constructor or null
     * @param InterceptedMethodGenerator|null $constructorGenerator  Constructor body generator (if present)
     * @param bool                            $useTypeWidening       Should generator use parameter widening for PHP>=7.2
     * @param bool                            $constructorIsInTrait  True when the original constructor is in the trait
     *                                                               (i.e. defined in the class itself, not inherited);
     *                                                               in that case the alias __aop____construct is used
     *                                                               instead of parent::__construct
     */
    public function __construct(
        ?ReflectionMethod $constructor = null,
        ?InterceptedMethodGenerator $constructorGenerator = null,
        bool $useTypeWidening = false,
        bool $constructorIsInTrait = false
    ) {
        if ($constructor !== null) {
            if ($constructorGenerator === null) {
                $callArguments = new FunctionCallArgumentListGenerator($constructor);
                $splatPrefix   = $constructor->getNumberOfParameters() > 0 ? '...' : '';
                if ($constructorIsInTrait) {
                    $constructorCallBody = '$this->__aop____construct(' . $splatPrefix . $callArguments->generate() . ');';
                } else {
                    $constructorCallBody = 'parent::__construct(' . $splatPrefix . $callArguments->generate() . ');';
                }
                $generator = MethodGenerator::fromReflection($constructor, $useTypeWidening);
                $generator->setBody($constructorCallBody);
            } else {
                $generator = $constructorGenerator->getGenerator();
            }
            $existingBody = $generator->getBody();
            $combinedBody = ($existingBody !== '' ? "\n" . $existingBody : '');
            $generator->setBody($combinedBody);
            $this->constructorGenerator = $generator;
        } else {
            $this->constructorGenerator = new MethodGenerator('__construct');
        }
    }

    public function generate(): string
    {
        return $this->constructorGenerator->generate();
    }

    public function getBody(): string
    {
        return $this->constructorGenerator->getBody();
    }

    public function setBody(string $body): void
    {
        $this->constructorGenerator->setBody($body);
    }

    public function getName(): string
    {
        return $this->constructorGenerator->getName();
    }

    public function getNode(): ClassMethod
    {
        return $this->constructorGenerator->getNode();
    }

    /**
     * Returns the underlying MethodGenerator for direct access.
     */
    public function getGenerator(): MethodGenerator
    {
        return $this->constructorGenerator;
    }
}
