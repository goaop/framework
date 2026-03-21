<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use Go\ParserReflection\ReflectionAttribute;
use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt;
use ReflectionMethod;

/**
 * Prepares the definition of intercepted method
 */
readonly class InterceptedMethodASTGenerator
{
    private FunctionParameterListASTGenerator $parameterListGenerator;

    /**
     * InterceptedMethod constructor.
     *
     * @param ReflectionMethod $reflectionMethod Instance of original method
     * @param Stmt[] $bodyStatements Method body statements
     * @param bool $useTypeWidening Should generator use parameter widening for PHP>=7.2
     */
    public function __construct(
        private \ReflectionMethod $reflectionMethod,
        private array             $bodyStatements,
        bool                      $useTypeWidening = false
    ) {
        $this->parameterListGenerator = new FunctionParameterListASTGenerator($reflectionMethod, $useTypeWidening);
    }

    public function generate(): Stmt\ClassMethod
    {
        $builder          = new BuilderFactory();
        $reflectionMethod = $this->reflectionMethod;

        $methodToBuild = $builder->method($reflectionMethod->getName());

        if ($reflectionMethod->hasReturnType()) {
            $reflectionReturnType = $reflectionMethod->getReturnType();
            if (isset($reflectionReturnType)) {
                $typeGenerator = new ReflectionTypeToASTTypeGenerator($reflectionMethod->getDeclaringClass());
                $methodToBuild->setReturnType($typeGenerator->generate($reflectionReturnType));
            }
        }

        if ($reflectionMethod->getDocComment() !== false) {
            $methodToBuild->setDocComment($reflectionMethod->getDocComment());
        }

        foreach ($reflectionMethod->getAttributes() as $attribute) {
            if ($attribute instanceof ReflectionAttribute) {
                // This will generate attribute in the exact way it was defined in the original class
                $methodToBuild->addAttribute($attribute->getNode());
            } else {
                // Otherwise we try to do our best with attribute name and arguments pair
                $methodToBuild->addAttribute(
                    $builder->attribute(
                        '\\' . $attribute->getName(),
                        $attribute->getArguments()
                    )
                );
            }
        }

        if ($reflectionMethod->isFinal()) {
            $methodToBuild->makeFinal();
        }
        if ($reflectionMethod->isStatic()) {
            $methodToBuild->makeStatic();
        }
        if ($reflectionMethod->returnsReference()) {
            $methodToBuild->makeReturnByRef();
        }

        if ($reflectionMethod->isPrivate()) {
            $methodToBuild->makePrivate();
        } elseif ($reflectionMethod->isProtected()) {
            $methodToBuild->makeProtected();
        } else {
            $methodToBuild->makePublic();
        }

        $methodToBuild->addParams($this->parameterListGenerator->generate());
        $methodToBuild->addStmts($this->bodyStatements);

        return $methodToBuild->getNode();
    }
}
