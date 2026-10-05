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

use ReflectionFunctionAbstract;

/**
 * Prepares the function call argument list
 *
 * The original function receives exactly the passed arguments, so its func_num_args() and func_get_args() stay the
 * same: optional arguments that were not passed are left out, and extra arguments passed after the declared ones
 * are added from func_get_args() (a variadic function collects them in its variadic argument instead). The list is
 * chosen by the number of passed arguments with a match of array literals (a ternary for a function without optional
 * arguments), which costs little more than a plain array; beyond MAX_MATCHED_OPTIONALS optional arguments the list is
 * cut with array_slice() instead, to keep the generated code short.
 */
final class FunctionCallArgumentListGenerator
{
    /**
     * Largest number of optional arguments whose argument lists are chosen with a match
     */
    private const int MAX_MATCHED_OPTIONALS = 4;

    /**
     * List of function arguments
     *
     * @var list<string>
     */
    private array $arguments = [];

    /**
     * Number of leading arguments that are always passed
     */
    private int $requiredCount = 0;

    /**
     * Definition of variadic argument or null if function is not variadic
     */
    private ?string $variadicArgument = null;

    /**
     * FunctionCallArgumentList constructor.
     *
     * @param ReflectionFunctionAbstract $functionLike Instance of function or method to call
     */
    public function __construct(ReflectionFunctionAbstract $functionLike)
    {
        foreach ($functionLike->getParameters() as $parameter) {
            $byReference       = ($parameter->isPassedByReference() && !$parameter->isVariadic()) ? '&' : '';
            $this->arguments[] = $byReference . '$' . $parameter->name;
            if (!$parameter->isOptional()) {
                // Required parameters precede the optional ones
                $this->requiredCount++;
            }
        }
        if ($functionLike->isVariadic()) {
            // Variadic argument is last and should be handled separately
            $this->variadicArgument = array_pop($this->arguments);
        }
    }

    public function generate(): string
    {
        if ($this->variadicArgument === null) {
            // Without declared arguments every passed argument is an extra one
            return $this->arguments === [] ? '\\func_get_args()' : $this->generateArgumentList();
        }
        if ($this->arguments === []) {
            return $this->variadicArgument;
        }

        return $this->generateArgumentList() . ', ' . $this->variadicArgument;
    }

    /**
     * Generates the list of the passed non-variadic arguments
     */
    private function generateArgumentList(): string
    {
        $argumentCount = count($this->arguments);
        $allArguments  = '[' . implode(', ', $this->arguments) . ']';
        $optionalCount = $argumentCount - $this->requiredCount;
        // The union keeps the declared arguments (with their references) and adds the extra ones
        $withExtraArguments = $this->variadicArgument === null ? "$allArguments + \\func_get_args()" : null;
        if ($optionalCount === 0 || $optionalCount > self::MAX_MATCHED_OPTIONALS) {
            $passedArguments = $optionalCount === 0
                ? $allArguments
                : "\\array_slice($allArguments, 0, \\func_num_args())";

            // A ternary costs less than a match, which pays off for the common functions without optional arguments
            return $withExtraArguments === null
                ? $passedArguments
                : "\\func_num_args() > $argumentCount ? $withExtraArguments : $passedArguments";
        }

        $arms = [];
        for ($passedCount = $this->requiredCount; $passedCount < $argumentCount; $passedCount++) {
            $arms[] = $passedCount . ' => [' . implode(', ', array_slice($this->arguments, 0, $passedCount)) . ']';
        }
        if ($withExtraArguments === null) {
            // All arguments passed, the extra ones are collected by the variadic argument
            $arms[] = 'default => ' . $allArguments;
        } else {
            $arms[] = $argumentCount . ' => ' . $allArguments;
            $arms[] = 'default => ' . $withExtraArguments;
        }

        return 'match (\\func_num_args()) { ' . implode(', ', $arms) . ' }';
    }
}
