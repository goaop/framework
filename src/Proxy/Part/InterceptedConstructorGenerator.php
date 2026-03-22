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
     * @param string[]                        $interceptedProperties List of intercepted properties for the class
     * @param ReflectionMethod|null           $constructor           Instance of original constructor or null
     * @param InterceptedMethodGenerator|null $constructorGenerator  Constructor body generator (if present)
     * @param bool                            $useTypeWidening       Should generator use parameter widening for PHP>=7.2
     */
    public function __construct(
        array $interceptedProperties,
        ?ReflectionMethod $constructor = null,
        ?InterceptedMethodGenerator $constructorGenerator = null,
        bool $useTypeWidening = false
    ) {
        $constructorBody = count($interceptedProperties) > 0 ? $this->getConstructorBody($interceptedProperties) : '';
        if ($constructor !== null && $constructor->isPrivate()) {
            throw new LogicException(
                "Constructor in the class {$constructor->class} is declared as private. " .
                'Properties could not be intercepted.'
            );
        }
        if ($constructor !== null) {
            if ($constructorGenerator === null) {
                $callArguments  = new FunctionCallArgumentListGenerator($constructor);
                $splatPrefix    = $constructor->getNumberOfParameters() > 0 ? '...' : '';
                $parentCallBody = 'parent::__construct(' . $splatPrefix . $callArguments->generate() . ');';
                $generator      = MethodGenerator::fromReflection($constructor, $useTypeWidening);
                $generator->setBody($parentCallBody);
            } else {
                $generator = $constructorGenerator->getGenerator();
            }
            $existingBody           = $generator->getBody();
            $combinedBody           = $constructorBody . ($existingBody !== '' ? "\n" . $existingBody : '');
            $generator->setBody($combinedBody);
            $this->constructorGenerator = $generator;
        } else {
            $constructorGenerator = new MethodGenerator('__construct');
            $constructorGenerator->setBody($constructorBody);
            $this->constructorGenerator = $constructorGenerator;
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

    public function getNode(): \PhpParser\Node\Stmt\ClassMethod
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

    /**
     * Returns constructor code
     *
     * @param string[] $interceptedProperties List of properties to intercept
     */
    private function getConstructorBody(array $interceptedProperties): string
    {
        $assocProperties = [];
        $listProperties  = [];
        foreach ($interceptedProperties as $propertyName) {
            $assocProperties[] = "        '{$propertyName}' => &\$target->{$propertyName}";
            $listProperties[]  = "        \$target->{$propertyName}";
        }
        $lines = [
            '$accessor = function(array &$propertyStorage, object $target) {',
            '    $propertyStorage = [',
            implode(',' . PHP_EOL, $assocProperties),
            '    ];',
            '    unset(',
            implode(',' . PHP_EOL, $listProperties),
            '    );',
            '};',
            '($accessor->bindTo($this, parent::class))($this->__properties, $this);'
        ];

        return implode(PHP_EOL, $lines);
    }
}
