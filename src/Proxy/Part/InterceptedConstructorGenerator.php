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

use LogicException;
use ReflectionMethod;
use Zend\Code\Generator\MethodGenerator;
use function count;

/**
 * Prepares the definition of intercepted constructor
 */
final class InterceptedConstructorGenerator extends MethodGenerator
{

    /**
     * Constructor generator
     *
     * @var MethodGenerator
     */
    private $generatedConstructor;

    /**
     * InterceptedConstructor
     *
     * @param array            $interceptedProperties List of intercepted properties for the class
     * @param ReflectionMethod $constructor           Instance of original constructor or null
     * @param MethodGenerator  $generatedConstructor  Constructor body generator (if present)
     * @param bool             $useTypeWidening       Should generator use parameter widening for PHP>=7.2
     *
     * @throws LogicException if constructor is private
     */
    public function __construct(
        array $interceptedProperties,
        ReflectionMethod $constructor = null,
        MethodGenerator $generatedConstructor = null,
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
            if ($generatedConstructor === null) {
                $callArguments        = new FunctionCallArgumentListGenerator($constructor);
                $splatPrefix          = $constructor->getNumberOfParameters() > 0 ? '...' : '';
                $parentCallBody       = 'parent::__construct(' . $splatPrefix . $callArguments->generate() . ');';
                $generatedConstructor = new InterceptedMethodGenerator($constructor, $parentCallBody, $useTypeWidening);
            }
            $constructorBody .= PHP_EOL . $generatedConstructor->getBody();
            $generatedConstructor->setBody($constructorBody);
        } else {
            $generatedConstructor = new MethodGenerator('__construct', [], [], $constructorBody);
        }
        $this->generatedConstructor = $generatedConstructor;
    }

    /**
     * @inheritdoc
     */
    public function generate()
    {
        return $this->generatedConstructor->generate();
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
            $assocProperties[] = "    '{$propertyName}' => &\$this->{$propertyName}";
            $listProperties[]  = "    \$this->{$propertyName}";
        }
        $lines = [
            '$this->__properties = [',
            implode(',' . PHP_EOL, $assocProperties),
            '];',
            'unset(',
            implode(',' . PHP_EOL, $listProperties),
            ');'
        ];

        return implode(PHP_EOL, $lines);
    }
}
