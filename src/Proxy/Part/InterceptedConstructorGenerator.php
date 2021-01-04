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

use Laminas\Code\Generator\MethodGenerator;
use LogicException;
use ReflectionMethod;

use function count;

/**
 * Prepares the definition of intercepted constructor
 */
final class InterceptedConstructorGenerator extends MethodGenerator
{
    private MethodGenerator $constructorGenerator;

    /**
     * InterceptedConstructor
     *
     * @param array                 $interceptedProperties List of intercepted properties for the class
     * @param ReflectionMethod|null $constructor           Instance of original constructor or null
     * @param MethodGenerator|null  $constructorGenerator  Constructor body generator (if present)
     * @param bool                  $useTypeWidening       Should generator use parameter widening for PHP>=7.2
     */
    public function __construct(
        array $interceptedProperties,
        ReflectionMethod $constructor = null,
        MethodGenerator $constructorGenerator = null,
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
                $callArguments        = new FunctionCallArgumentListGenerator($constructor);
                $splatPrefix          = $constructor->getNumberOfParameters() > 0 ? '...' : '';
                $parentCallBody       = 'parent::__construct(' . $splatPrefix . $callArguments->generate() . ');';
                $constructorGenerator = new InterceptedMethodGenerator($constructor, $parentCallBody, $useTypeWidening);
            }
            $constructorBody .= PHP_EOL . $constructorGenerator->getBody();
            $constructorGenerator->setBody($constructorBody);
        } else {
            $constructorGenerator = new MethodGenerator('__construct', [], [], $constructorBody);
        }
        assert($constructorGenerator !== null, "Constructor generator should be initialized");
        $this->constructorGenerator = $constructorGenerator;
    }

    /**
     * @inheritdoc
     */
    public function generate(): string
    {
        return $this->constructorGenerator->generate();
    }

    /**
     * Returns constructor code
     *
     * @param array $interceptedProperties List of properties to intercept
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
