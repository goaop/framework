<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;
use ReflectionMethod;

/**
 * Trait proxy builder that is used to generate a trait from the list of joinpoints
 */
class TraitProxy extends ClassProxy
{

    /**
     * List of advices for traits
     *
     * @var array
     */
    protected static $traitAdvices = [];

    /**
     * Inject advices for given trait
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     *
     * @param string $className Aop child proxy class
     * @param array|\Go\Aop\Advice[] $traitAdvices List of advices to inject into class
     *
     * @return void
     */
    public static function injectJoinPoints($className, array $traitAdvices = [])
    {
        self::$traitAdvices[$className] = $traitAdvices;
    }

    public static function getJoinPoint($traitName, $className, $joinPointType, $pointName)
    {
        /** @var LazyAdvisorAccessor $accessor */
        static $accessor = null;

        if (!isset($accessor)) {
            $aspectKernel = AspectKernel::getInstance();
            $accessor     = $aspectKernel->getContainer()->get('aspect.advisor.accessor');
        }

        $advices = self::$traitAdvices[$traitName][$joinPointType][$pointName];

        $filledAdvices = [];
        foreach ($advices as $advisorName) {
            $filledAdvices[] = $accessor->$advisorName;
        }

        $joinpoint = new self::$invocationClassMap[$joinPointType]($className, $pointName . '➩', $filledAdvices);

        return $joinpoint;
    }

    /**
     * Creates definition for trait method body
     *
     * @param ReflectionMethod $method Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method)
    {
        $isStatic = $method->isStatic();
        $class    = '\\' . __CLASS__;
        $scope    = $isStatic ? self::$staticLsbExpression : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $args = $this->prepareArgsLine($method);
        $args = $scope . ($args ? ", $args" : '');

        $return = 'return ';
        if (PHP_VERSION_ID >= 70100 && $method->hasReturnType()) {
            $returnType = (string) $method->getReturnType();
            if ($returnType === 'void') {
                // void return types should not return anything
                $return = '';
            }
        }

        return <<<BODY
static \$__joinPoint = null;
if (!\$__joinPoint) {
    \$__joinPoint = {$class}::getJoinPoint(__TRAIT__, __CLASS__, '{$prefix}', '{$method->name}');
}
{$return}\$__joinPoint->__invoke($args);
BODY;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        $classCode = (
            $this->class->getDocComment() . "\n" . // Original doc-block
            'trait ' . // 'trait' keyword
            $this->name . "\n" . // Name of the trait
            "{\n" . // Start of trait body
            $this->indent(
                'use ' . join(', ', [-1 => $this->parentClassName] + $this->traits) .
                $this->getMethodAliasesCode()
            ) . "\n" . // Use traits and aliases section
            $this->indent(join("\n", $this->methodsCode)) . "\n" . // Method definitions
            "}" // End of trait body
        );

        return $classCode
            // Inject advices on call
            . PHP_EOL
            . '\\' . __CLASS__ . "::injectJoinPoints('"
                . $this->class->name . "',"
                . var_export($this->advices, true) . ");";
    }

    private function getMethodAliasesCode()
    {
        $aliasesLines = [];
        foreach (array_keys($this->methodsCode) as $methodName) {
            $aliasesLines[] = "{$this->parentClassName}::{$methodName} as protected {$methodName}➩;";
        }

        return "{\n " . $this->indent(join("\n", $aliasesLines)) . "\n}";
    }
}
