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
use ReflectionMethod;

/**
 * Prepares the definition of intercepted method
 */
final class InterceptedMethodGenerator
{
    private MethodGenerator $generator;

    /**
     * InterceptedMethod constructor.
     *
     * @param ReflectionMethod $reflectionMethod Instance of original method
     * @param string           $body             Method body
     * @param bool             $useTypeWidening  Should generator use parameter widening for PHP>=7.2
     */
    public function __construct(ReflectionMethod $reflectionMethod, string $body, bool $useTypeWidening = false)
    {
        $this->generator = MethodGenerator::fromReflection($reflectionMethod, $useTypeWidening);
        $this->generator->setBody($body);
    }

    public function generate(): string
    {
        return $this->generator->generate();
    }

    public function getBody(): string
    {
        return $this->generator->getBody();
    }

    public function setBody(string $body): void
    {
        $this->generator->setBody($body);
    }

    public function getName(): string
    {
        return $this->generator->getName();
    }

    public function getNode(): \PhpParser\Node\Stmt\ClassMethod
    {
        return $this->generator->getNode();
    }

    /**
     * Returns the underlying MethodGenerator for direct access.
     */
    public function getGenerator(): MethodGenerator
    {
        return $this->generator;
    }
}
