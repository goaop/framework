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
use PhpParser\Node\Stmt;
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
     * @param ReflectionMethod   $reflectionMethod Instance of original method
     * @param string|list<Stmt> $body             Method body: AST statements, or PHP code which is parsed into them
     */
    public function __construct(ReflectionMethod $reflectionMethod, string|array $body)
    {
        $this->generator = MethodGenerator::fromReflection($reflectionMethod);
        if (is_string($body)) {
            $this->generator->body = $body;
        } else {
            $this->generator->stmts = $body;
        }
    }

    public function generate(): string
    {
        return $this->generator->generate();
    }

    public function getName(): string
    {
        return $this->generator->name;
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
